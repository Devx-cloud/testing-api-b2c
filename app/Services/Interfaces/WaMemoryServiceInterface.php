<?php

namespace App\Services\Interfaces;

/**
 * Kontrak penyimpanan memori percakapan WhatsApp AI Agent.
 *
 * Semua data hidup di tabel `wa_*` pada DB bersama (dibuat manual lewat
 * database/sql/wa_memory_schema.sql). Agent Python hanya mengakses lewat
 * endpoint HTTP yang memanggil service ini; tidak ada koneksi DB langsung.
 */
interface WaMemoryServiceInterface
{
    /**
     * Cari/buat wa_customer dari nomor telepon dan/atau JID WhatsApp, lalu
     * tentukan thread percakapan aktif (buat baru / rollover kalau lama & idle).
     *
     * @param array $input {phone_number?: string, jid?: string, user_id?: int, display_name?: string}
     * @return array{customer: array, conversation: array}
     */
    public function resolveCustomer(array $input): array;

    /**
     * Rakit konteks untuk dikirim ke LLM: fakta pelanggan + ringkasan + giliran
     * terakhir dalam batas anggaran karakter (menjaga pasangan tool tetap utuh).
     *
     * @return array{conversation_id: int, summary: ?string, facts: array, recent_messages: array, unsummarized_messages?: array, budget: array}
     */
    public function getContext(int $conversationId, int $charBudget, int $recentTurns, bool $forSummary = false): array;

    /**
     * Simpan satu atau lebih pesan mentah ke transkrip.
     *
     * @param array $messages [{role, content?, tool_calls?, tool_call_id?, name?, token_estimate?}]
     * @return array{created: array, conversation: array}
     */
    public function appendMessages(int $conversationId, array $messages, bool $touchLastActive = true): array;

    /**
     * Tulis/replace ringkasan bergulir; tandai pesan <= upToMessageId sebagai sudah diringkas.
     *
     * @return array{conversation_id: int, summary_updated_at: ?string, marked_summarized: int}
     */
    public function putSummary(int $conversationId, string $summary, ?int $upToMessageId): array;

    /**
     * Upsert fakta terstruktur per pelanggan (idempoten per fact_key).
     *
     * @param array $facts [{key, value, source?}]
     * @return array daftar fakta terkini
     */
    public function upsertFacts(int $customerId, array $facts): array;

    /**
     * @return array [{key, value, source, updated_at}]
     */
    public function getFacts(int $customerId): array;

    /**
     * @return array|null blob state runtime, atau null kalau belum ada
     */
    public function getSessionState(int $customerId): ?array;

    /**
     * Replace penuh blob state runtime (sesi checkout, cache user, rate-limit).
     *
     * @return array{updated_at: string}
     */
    public function putSessionState(int $customerId, array $blob): array;

    /**
     * Simpan vektor embedding satu pesan/ringkasan.
     *
     * @param array $data {message_id: ?int, conversation_id: int, kind: string, model: string, dim: int, vector: float[], content_preview?: string}
     * @return array{id: int}
     */
    public function storeEmbedding(int $customerId, array $data): array;

    /**
     * Cari embedding paling mirip milik pelanggan ini (cosine, dihitung di PHP).
     *
     * @return array{results: array} results = [{message_id, kind, score, content_preview, created_at}]
     */
    public function queryEmbeddings(int $customerId, array $vector, string $model, int $topK, array $excludeMessageIds, float $minScore): array;

    /**
     * Hapus pesan lama yang sudah diringkas (di luar keepMin terakhir).
     * Hanya menyentuh tabel wa_message / wa_message_embedding.
     *
     * @return int jumlah baris wa_message yang dihapus
     */
    public function pruneMessages(int $days, int $keepMin): int;
}
