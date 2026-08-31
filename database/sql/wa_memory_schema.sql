-- ============================================================================
-- WhatsApp AI Agent — Conversation Memory schema
-- ============================================================================
-- Target DB: testing_tokodaring_b2c (SHARED with another application).
--
-- Run this MANUALLY. Do NOT use `php artisan migrate` — this repo is only a
-- separate API for the AI agent's checkout/memory and must not own migrations
-- for a database it does not control.
--
--   mysql -u <user> -p testing_tokodaring_b2c < database/sql/wa_memory_schema.sql
--
-- All new tables are prefixed `wa_`. No ALTER on existing tables, no foreign
-- keys toward `user` / `order` (soft links only). Safe to run on the live DB.
-- Rollback: database/sql/wa_memory_drop.sql
--
-- Engine/collation match the existing tables (InnoDB, utf8mb4_unicode_ci).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- wa_customer — identity: one row per WhatsApp person (by phone, else by JID)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_customer` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lookup_key`      VARCHAR(191)    NOT NULL COMMENT 'normalized local phone "0..." or "jid:<full-jid>"',
    `phone_number`    VARCHAR(32)     NULL     COMMENT 'normalized local form, null for phone-less @lid',
    `user_id`         BIGINT UNSIGNED NULL     COMMENT 'soft link to user.id (no FK)',
    `display_name`    VARCHAR(255)    NULL,
    `jids`            JSON            NULL     COMMENT 'array of every WhatsApp JID seen for this person',
    `last_matched_at` TIMESTAMP       NULL,
    `created_at`      TIMESTAMP       NULL,
    `updated_at`      TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wa_customer_lookup_key_unique` (`lookup_key`),
    KEY `wa_customer_phone_number_index` (`phone_number`),
    KEY `wa_customer_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- wa_conversation — one active thread per customer; older threads archived
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_conversation` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`                 BIGINT UNSIGNED NOT NULL,
    `thread_no`                   INT UNSIGNED    NOT NULL DEFAULT 1,
    `summary`                     LONGTEXT        NULL,
    `summary_updated_at`          TIMESTAMP       NULL,
    `last_summarized_message_id`  BIGINT UNSIGNED NULL,
    `last_active_at`              TIMESTAMP       NULL,
    `status`                      VARCHAR(16)     NOT NULL DEFAULT 'active' COMMENT 'active | archived',
    `created_at`                  TIMESTAMP       NULL,
    `updated_at`                  TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wa_conversation_customer_thread_unique` (`customer_id`, `thread_no`),
    KEY `wa_conversation_customer_id_index` (`customer_id`),
    KEY `wa_conversation_last_active_at_index` (`last_active_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- wa_message — full transcript, ordered by id
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_message` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `customer_id`     BIGINT UNSIGNED NOT NULL COMMENT 'denormalized for embedding scope + prune',
    `role`            VARCHAR(16)     NOT NULL COMMENT 'user | assistant | tool | system_note',
    `content`         LONGTEXT        NULL,
    `tool_calls`      JSON            NULL,
    `tool_call_id`    VARCHAR(64)     NULL,
    `name`            VARCHAR(64)     NULL     COMMENT 'tool name (role=tool)',
    `token_estimate`  INT UNSIGNED    NULL     COMMENT 'len(content) proxy for budgeting',
    `summarized`      TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP       NULL,
    `updated_at`      TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `wa_message_conversation_id_id_index` (`conversation_id`, `id`),
    KEY `wa_message_conversation_summarized_id_index` (`conversation_id`, `summarized`, `id`),
    KEY `wa_message_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- wa_customer_fact — durable structured facts, injected every turn
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_customer_fact` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `fact_key`    VARCHAR(64)     NOT NULL,
    `fact_value`  TEXT            NOT NULL,
    `source`      VARCHAR(24)     NULL COMMENT 'sync | order | address | courier | llm | manual',
    `created_at`  TIMESTAMP       NULL,
    `updated_at`  TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wa_customer_fact_customer_key_unique` (`customer_id`, `fact_key`),
    KEY `wa_customer_fact_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- wa_message_embedding — JSON vectors; cosine similarity done in PHP per customer
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_message_embedding` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `customer_id`     BIGINT UNSIGNED  NOT NULL,
    `conversation_id` BIGINT UNSIGNED  NOT NULL,
    `message_id`      BIGINT UNSIGNED  NULL COMMENT 'null when kind=summary',
    `kind`            VARCHAR(16)      NOT NULL DEFAULT 'message' COMMENT 'message | summary',
    `model`           VARCHAR(64)      NOT NULL,
    `dim`             SMALLINT UNSIGNED NOT NULL,
    `vector`          JSON             NOT NULL,
    `norm`            DOUBLE           NULL COMMENT 'precomputed L2 norm for cosine',
    `content_preview` VARCHAR(500)     NULL COMMENT 'kept so recall survives message prune',
    `created_at`      TIMESTAMP        NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wa_message_embedding_message_model_unique` (`message_id`, `model`),
    KEY `wa_message_embedding_customer_id_index` (`customer_id`),
    KEY `wa_message_embedding_conversation_id_index` (`conversation_id`),
    KEY `wa_message_embedding_message_id_index` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- wa_session_state — one row per customer; durable fallback for Redis hot state
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_session_state` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`            BIGINT UNSIGNED NOT NULL,
    `conversation_id`        BIGINT UNSIGNED NULL,
    `checkout_state`         JSON            NULL COMMENT 'serialized CheckoutSession',
    `matched_user_cache`     JSON            NULL COMMENT 'sync result or {"matched": false}',
    `matched_user_cached_at` TIMESTAMP       NULL,
    `rate_limit`             JSON            NULL COMMENT 'array of epoch seconds within window',
    `extra`                  JSON            NULL,
    `updated_at`             TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wa_session_state_customer_id_unique` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
