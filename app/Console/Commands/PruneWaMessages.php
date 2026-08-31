<?php

namespace App\Console\Commands;

use App\Services\Interfaces\WaMemoryServiceInterface;
use Illuminate\Console\Command;

/**
 * Pangkas transkrip percakapan WhatsApp yang sudah lama & sudah diringkas.
 * Hanya menyentuh tabel wa_message / wa_message_embedding (data milik AI Agent),
 * tidak pernah tabel aplikasi lain. Ringkasan & fakta pelanggan tidak dihapus.
 */
class PruneWaMessages extends Command
{
    protected $signature = 'wa:prune-messages
        {--days= : Umur minimum pesan (hari) sebelum boleh dihapus}
        {--keep-min= : Jumlah pesan terakhir per percakapan yang selalu dipertahankan}
        {--dry-run : Hanya tampilkan jumlah yang akan dihapus tanpa menghapus}';

    protected $description = 'Hapus pesan wa_message lama yang sudah diringkas (di luar N pesan terakhir).';

    public function handle(WaMemoryServiceInterface $waMemoryService): int
    {
        $days = (int) ($this->option('days') ?? config('services.wa_memory.prune_days', 45));
        $keepMin = (int) ($this->option('keep-min') ?? config('services.wa_memory.prune_keep_min', 40));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run belum didukung service; jalankan tanpa flag untuk benar-benar memangkas.');

            return self::SUCCESS;
        }

        $deleted = $waMemoryService->pruneMessages($days, $keepMin);

        $this->info("Selesai. {$deleted} baris wa_message dihapus (days>={$days}, keep-min={$keepMin}).");

        return self::SUCCESS;
    }
}
