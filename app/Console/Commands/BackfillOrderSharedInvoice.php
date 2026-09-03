<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Support\InvoiceNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi ulang kolom `shared_invoice` untuk order lama yang terlanjur dibuat tanpa
 * nomor transaksi (semua order hasil checkout AI agent sebelum perbaikan ini).
 *
 * Order tersebut tetap tampil di riwayat transaksi nusantaramall-b2c, tapi nomor
 * transaksinya kosong dan tombol "Detail Transaksi"-nya mati (`javascript:void(0);`),
 * jadi pembeli tidak pernah bisa membukanya. Perintah ini menyembuhkannya tanpa
 * menyentuh order yang nomornya sudah benar.
 *
 * Setara dengan SetOrderSharedInvoiceEntityListener run_type "batch" di Symfony:
 * satu nomor per grup `shared_id`, di-encode dari seluruh order id dalam grup itu.
 */
class BackfillOrderSharedInvoice extends Command
{
    protected $signature = 'orders:backfill-shared-invoice
                            {--dry-run : Tampilkan rencana perubahan tanpa menyimpan}
                            {--shared-id= : Batasi ke satu shared_id saja}';

    protected $description = 'Isi shared_invoice untuk order yang kolomnya masih NULL.';

    public function handle(): int
    {
        $alphabet = config('services.invoice.hashids_alphabet');
        $dryRun = (bool) $this->option('dry-run');

        $query = Order::query()
            ->whereNull('shared_invoice')
            ->whereNotNull('shared_id')
            ->where('shared_id', '<>', '');

        if ($sharedId = $this->option('shared-id')) {
            $query->where('shared_id', $sharedId);
        }

        $sharedIds = $query->distinct()->pluck('shared_id');

        if ($sharedIds->isEmpty()) {
            $this->info('Tidak ada order yang perlu diperbaiki.');

            return 0;
        }

        $this->info(sprintf('%d transaksi akan diproses%s.', $sharedIds->count(), $dryRun ? ' (dry run)' : ''));

        $fixed = 0;

        foreach ($sharedIds as $sharedId) {
            // Seluruh order dalam grup ikut di-encode, termasuk yang shared_invoice-nya
            // sudah terisi, supaya hash-nya mewakili transaksi yang utuh.
            $orders = Order::where('shared_id', $sharedId)->orderBy('id')->get();

            if ($orders->isEmpty()) {
                continue;
            }

            // Kalau sebagian grup sudah punya nomor (mis. dibuat lewat web B2C lalu
            // ditambah dari API), pakai nomor yang sudah ada. Menghitung ulang akan
            // mengubah nomor yang mungkin sudah dirujuk data pembayaran (Midtrans,
            // Doku, virtual account) dan justru merusak transaksi yang tadinya sehat.
            $existing = $orders->pluck('shared_invoice')->filter()->unique();

            if ($existing->count() > 1) {
                $this->warn(sprintf('  %s dilewati: ada %d nomor transaksi berbeda dalam satu grup.', $sharedId, $existing->count()));
                continue;
            }

            $orderIds = $orders->pluck('id')->all();
            // Satu nomor untuk satu grup, dibangun dari invoice order pertama - bukan
            // dari invoice masing-masing order. Kalau order dalam satu grup ternyata
            // beda bulan, nomor per-order akan berbeda dan halaman detail transaksi
            // hanya menemukan sebagian pesanan.
            $sharedInvoice = $existing->first()
                ?: InvoiceNumber::shared($orderIds, $orders->first()->invoice, $alphabet);

            $this->line(sprintf('  %s -> %s (order: %s)', $sharedId, $sharedInvoice, implode(', ', $orderIds)));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($orders, $sharedInvoice, &$fixed) {
                foreach ($orders as $order) {
                    if ($order->shared_invoice === $sharedInvoice) {
                        continue;
                    }

                    $order->shared_invoice = $sharedInvoice;
                    $order->save();
                    $fixed++;
                }
            });
        }

        $this->info($dryRun ? 'Dry run selesai, tidak ada yang disimpan.' : sprintf('Selesai. %d order diperbarui.', $fixed));

        return 0;
    }
}
