# Laporan Progress: API Sinkronisasi WhatsApp, Sistem B2C, dan AI Agent

**Periode pengerjaan**: 15 – 22 Juli 2026 (hari kerja saja, Sabtu 18 & Minggu 19 Juli tidak dihitung)
**Proyek terkait**:
- `C:\kerja\api_tokodaring\api_testing` (Laravel API)
- `C:\kerja\AI-Agent-Baliyoni` (AI Agent WhatsApp)
- `C:\kerja\tokoDaring\tokodaring-pdc` & `C:\kerja\nusantaramall-b2c` (referensi sistem produksi)

Struktur mengikuti pola *work breakdown*: setiap **Summary Task** berisi beberapa **Task**, ditutup satu **Milestone** per fase.

> **Catatan status**: Task berstatus Closed berarti implementasi/kode sudah selesai ditulis dan sempat dicoba jalan (curl/skrip) oleh pengembang. Ini **bukan** pengujian penerimaan oleh Anda di lingkungan/WhatsApp sungguhan — karena itu, kolom Status pada tiap **Milestone** masih ditandai *Menunggu Validasi* sampai Anda sendiri yang mengonfirmasi hasil ujinya.

---

## Work Packages

| ID | Subject | Type | Mulai | Selesai | Status |
|---|---|---|---|---|---|
| **1** | **Sinkronisasi User & Produk (API Dasar)** | **Summary Task** | 15 Jul | 15 Jul | ✅ Closed |
| 1.1 | Analisis arsitektur referensi (Tokodaring-MPstore_API) — pola Service/Interface/Resource, middleware API key | Task | 15 Jul | 15 Jul | ✅ Closed |
| 1.2 | Implementasi endpoint sinkronisasi nomor WhatsApp → user (`/api/whatsapp/sync-user`), normalisasi format nomor telepon | Task | 15 Jul | 15 Jul | ✅ Closed |
| 1.3 | Implementasi endpoint data produk (`/api/products`, `/search`, `/search-category`) | Task | 15 Jul | 15 Jul | ✅ Closed |
| **1.4** | **API Sinkronisasi User & Produk Siap** | **Milestone** | — | 15 Jul | 🟡 Menunggu Validasi |
| **2** | **Dokumentasi & Kompatibilitas Platform** | **Summary Task** | 15 Jul | 16 Jul | ✅ Closed |
| 2.1 | Pembuatan halaman dokumentasi API interaktif (uji endpoint langsung dari browser) | Task | 15 Jul | 15 Jul | ✅ Closed |
| 2.2 | Perbaikan inkompatibilitas Laravel 8 dengan PHP 8.4 (deprecation notice bocor ke response JSON) | Task | 16 Jul | 16 Jul | ✅ Closed |
| **2.3** | **Dokumentasi API & Kompatibilitas Platform Siap** | **Milestone** | — | 16 Jul | 🟡 Menunggu Validasi |
| **3** | **Personalisasi Respons AI Agent** | **Summary Task** | 16 Jul | 16 Jul | ✅ Closed |
| 3.1 | Sapaan personalisasi berdasarkan nama user terdaftar (bukan "Kak") | Task | 16 Jul | 16 Jul | ✅ Closed |
| 3.2 | Alur arahan registrasi untuk user belum terdaftar saat mencoba checkout | Task | 16 Jul | 16 Jul | ✅ Closed |
| **3.3** | **Personalisasi Respons AI Agent Aktif** | **Milestone** | — | 16 Jul | 🟡 Menunggu Validasi |
| **4** | **Analisis & Perancangan Checkout B2C** | **Summary Task** | 16 Jul | 17 Jul | ✅ Closed |
| 4.1 | Analisis alur checkout B2C vs B2G dari sistem referensi (`tokodaring-pdc`) | Task | 16 Jul | 17 Jul | ✅ Closed |
| 4.2 | Identifikasi field & tabel yang dibutuhkan (order, order_product, alamat, ongkir, pembayaran) | Task | 17 Jul | 17 Jul | ✅ Closed |
| 4.3 | Perancangan scope: B2C/non-B2G saja untuk versi ini (disetujui) | Task | 17 Jul | 17 Jul | ✅ Closed |
| **4.4** | **Rancangan Checkout B2C Disetujui** | **Milestone** | — | 17 Jul | ✅ Closed |
| **5** | **Implementasi API Order/Checkout B2C** | **Summary Task** | 17 Jul | 20 Jul | ✅ Closed |
| 5.1 | Model & service alamat pengguna (`UserAddress`) | Task | 17 Jul | 17 Jul | ✅ Closed |
| 5.2 | Integrasi ongkir nyata (`RajaOngkirService`) — pencarian kota & perhitungan biaya kirim | Task | 17 Jul | 20 Jul | ✅ Closed |
| 5.3 | Perbaikan bug: `city_id`/`province_id` tersimpan tidak sinkron dengan skema RajaOngkir asli — diresolusi ulang dari nama kota+provinsi | Task | 20 Jul | 20 Jul | ✅ Closed |
| 5.4 | Implementasi pembuatan order multi-toko dengan validasi ulang harga & stok di server | Task | 20 Jul | 20 Jul | ✅ Closed |
| **5.5** | **API Order/Checkout B2C Siap** | **Milestone** | — | 20 Jul | 🟡 Menunggu Validasi |
| **6** | **Integrasi Checkout ke AI Agent** | **Summary Task** | 20 Jul | 21 Jul | ✅ Closed |
| 6.1 | Desain state machine checkout percakapan (`COLLECTING_ITEMS → NEED_ADDRESS → NEED_SHIPPING → CONFIRMING`) | Task | 20 Jul | 20 Jul | ✅ Closed |
| 6.2 | Implementasi ekstraksi data terstruktur via LLM (bukan tool-calling, agar kompatibel semua provider) | Task | 20 Jul | 21 Jul | ✅ Closed |
| 6.3 | Alur pengumpulan alamat baru dari percakapan (resolve nama kota → city_id) | Task | 21 Jul | 21 Jul | ✅ Closed |
| **6.4** | **Alur Checkout Percakapan Terintegrasi** | **Milestone** | — | 21 Jul | 🟡 Menunggu Validasi |
| **7** | **Penyempurnaan Tanya-Jawab & Sinkronisasi Real-time** | **Summary Task** | 21 Jul | 21 Jul | ✅ Closed |
| 7.1 | Perbaikan: tanya-jawab produk dari snapshot statis (25 dari 1000+ produk) menjadi live search per pertanyaan | Task | 21 Jul | 21 Jul | ✅ Closed |
| 7.2 | Penanganan error sistem vs "produk tidak ada" (`ProductSearchError`) agar bot tidak menyesatkan user saat backend down | Task | 21 Jul | 21 Jul | ✅ Closed |
| 7.3 | Perbaikan caching: user yang baru mendaftar langsung dikenali tanpa restart bot | Task | 21 Jul | 21 Jul | ✅ Closed |
| **7.4** | **AI Agent Live-Search & Sinkronisasi Real-time Siap** | **Milestone** | — | 21 Jul | 🟡 Menunggu Validasi |
| **8** | **Migrasi Skema B2C Murni & Analisis Ulang** | **Summary Task** | 22 Jul | 22 Jul | ✅ Closed |
| 8.1 | Analisis dampak migrasi database ke `testing_tokodaring_b2c` | Task | 22 Jul | 22 Jul | ✅ Closed |
| 8.2 | Perbaikan bug kritis: kolom `order.type_order` sudah dihapus di skema baru, menyebabkan seluruh pembuatan order gagal | Task | 22 Jul | 22 Jul | ✅ Closed |
| 8.3 | Analisis sistem acuan sesungguhnya (`nusantaramall-b2c`) — ditemukan sistem masih menjalankan logika B2G aktif | Task | 22 Jul | 22 Jul | ✅ Closed |
| 8.4 | Penambahan validasi penolakan buyer B2G (`ROLE_USER_GOVERNMENT`) di API & AI Agent | Task | 22 Jul | 22 Jul | ✅ Closed |
| **8.5** | **Sistem Menyesuaikan Skema B2C Baru Siap** | **Milestone** | — | 22 Jul | 🟡 Menunggu Validasi |

---

## Catatan Terbuka / Belum Diselesaikan

- Seluruh **Milestone** menunggu Anda uji langsung di lingkungan/WhatsApp sungguhan sebelum ditandai selesai — pengujian yang sudah dilakukan sejauh ini baru dari sisi pengembang (curl/skrip), bukan pemakaian riil.
- **Kuota API Gemini** yang tersedia sangat terbatas (tier gratis, 20 request/hari) — perlu upgrade sebelum pemakaian produksi.
- **Endpoint submit bukti pembayaran manual** belum dibangun — order tersimpan berstatus `pending`, proses pembayaran belum ada tindak lanjutnya.
- **Voucher, payment gateway otomatis (QRIS/Doku/Midtrans), dan invoice PDF** masih di luar scope (sengaja ditunda, bukan terlewat).
- Server Laravel (`php artisan serve`) masih dijalankan manual selama pengujian — perlu di-hosting sebagai service untuk pemakaian jangka panjang.
