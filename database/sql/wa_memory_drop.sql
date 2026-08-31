-- ============================================================================
-- Rollback for wa_memory_schema.sql — drops ONLY the wa_* tables it created.
--   mysql -u <user> -p testing_tokodaring_b2c < database/sql/wa_memory_drop.sql
-- ============================================================================

DROP TABLE IF EXISTS `wa_session_state`;
DROP TABLE IF EXISTS `wa_message_embedding`;
DROP TABLE IF EXISTS `wa_customer_fact`;
DROP TABLE IF EXISTS `wa_message`;
DROP TABLE IF EXISTS `wa_conversation`;
DROP TABLE IF EXISTS `wa_customer`;
