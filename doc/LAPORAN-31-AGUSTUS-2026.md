# Laporan Pekerjaan

## API Memori Percakapan untuk WhatsApp AI Agent

**Tanggal:** 31 Agustus 2026 (satu hari kerja)
**Repositori:** `C:\kerja\api_tokodaring\api_testing` (API Laravel)
**Cakupan:** Perubahan sejak commit `b3aa827` (`feat: implement CheckoutService for order creation and shipping calculation with RajaOngkir integration`) hingga seluruh perubahan yang saat ini masih menunggu commit & push.

> Pekerjaan di sisi AI Agent (Python/Node) untuk memakai API ini dilaporkan terpisah
> di `C:\kerja\AI-Agent-Baliyoni\doc\LAPORAN-31-AGUSTUS-2026.md`.

---

## 1. Ringkasan Tugas

### Latar belakang

Bot WhatsApp "gampang lupa" percakapan: riwayat hanya di memori proses, hilang tiap restart; percakapan panjang melewati kapasitas model sehingga bagian awal terlupakan; sesi checkout yang sedang berjalan tidak selamat dari restart. Solusinya butuh **penyimpanan memori percakapan yang durable**.

Keputusan arah: penyimpanan diletakkan di **tabel MySQL baru di belakang endpoint API baru**, memakai proteksi API key (`Token`) yang sudah ada — sehingga AI Agent tetap murni klien HTTP dan **tidak pernah mengakses database langsung**. Laporan ini mencakup pembangunan API tersebut.

### Batasan penting

Database `testing_tokodaring_b2c` **dimiliki aplikasi lain**. Karena itu:

- **Tidak** dibuat berkas migrasi Laravel dan **tidak** dijalankan `php artisan migrate`.
- Skema diberikan sebagai **berkas SQL** untuk dijalankan manual oleh DBA.
- Hanya `CREATE TABLE` untuk tabel baru berprefiks `wa_` — tidak ada `ALTER` pada tabel yang sudah ada, tidak ada foreign key ke `user`/`order` (hanya tautan lunak lewat kolom `user_id`). Aman dijalankan kapan saja di database bersama; `down` cukup men-`DROP` tabel `wa_*`.
- Model Eloquent menunjuk tabel-tabel ini lewat `protected $table`, persis pola model `order`/`user`/`product` yang juga tidak dikelola migrasi di repo ini.

Total **5 pekerjaan** diselesaikan.

| # | Pekerjaan | Status |
|---|---|---|
| 1.1 | Skema 6 tabel `wa_*` sebagai berkas SQL manual | Selesai, menunggu dijalankan DBA |
| 1.2 | Model Eloquent untuk tabel `wa_*` | Selesai |
| 1.3 | `WaMemoryService` — perakitan konteks, cosine, rollover thread, prune | Selesai |
| 1.4 | `WaMemoryController` + 10 endpoint `/api/wa/*` + registrasi route | Selesai |
| 1.5 | Konfigurasi + perintah artisan retensi (`wa:prune-messages`) | Selesai |

---

### 1.1 Skema 6 tabel `wa_*`

**File baru:** `database/sql/wa_memory_schema.sql`, `database/sql/wa_memory_drop.sql`

| Tabel | Fungsi |
|---|---|
| `wa_customer` | Identitas kanonik pengirim WhatsApp (kunci: nomor lokal `0…` bila diketahui, kalau tidak `jid:<jid>`). Tautan lunak ke `user.id`. |
| `wa_conversation` | Satu thread aktif per pelanggan + kolom `summary` (ringkasan bergulir) + `last_active_at` (dasar rollover). Thread lama diarsipkan. |
| `wa_message` | Transkrip penuh, diurutkan `id`. Peran: `user` / `assistant` / `tool` / `system_note`. |
| `wa_customer_fact` | Fakta terstruktur jangka panjang (nama, bahasa, kurir favorit, ringkasan pesanan terakhir). Unik per `(customer_id, fact_key)`. |
| `wa_message_embedding` | Vektor embedding pesan/ringkasan sebagai JSON + `content_preview` (agar recall selamat walau pesan sumbernya dipangkas). |
| `wa_session_state` | Satu baris per pelanggan: cadangan durable untuk state runtime panas agent (sesi checkout, cache identitas, rate-limit). |

MySQL 8 / Laravel 8 tidak punya tipe vektor, jadi pencarian kemiripan dilakukan di PHP (lihat 1.3).

Charset/engine mengikuti tabel yang sudah ada (`InnoDB`, `utf8mb4_unicode_ci`).

### 1.2 Model Eloquent untuk tabel `wa_*`

**File baru:** `app/Models/WaCustomer.php`, `WaConversation.php`, `WaMessage.php`, `WaCustomerFact.php`, `WaMessageEmbedding.php`, `WaSessionState.php`

Mengikuti konvensi model yang ada: `protected $table` eksplisit, `protected $guarded = ['id']`, relasi bertipe dengan foreign key eksplisit. `WaMessageEmbedding` & `WaSessionState` memakai `$timestamps = false` (pola sama seperti `OrderProduct`). Perbedaan yang disengaja & terbatas: keenam model baru memakai `$casts` untuk kolom JSON-nya (lebih bersih daripada `json_decode` manual berulang) — model bisnis lama tidak diubah.

### 1.3 `WaMemoryService` — inti logika

**File baru:** `app/Services/WaMemoryService.php`, `app/Services/Interfaces/WaMemoryServiceInterface.php` · **File diubah:** `app/Providers/AppServiceProvider.php` (binding interface → implementasi)

- **`resolveCustomer`** — cari/buat pelanggan dari nomor telepon dan/atau JID; isi otomatis `user_id` dengan memakai `WhatsappUserService` yang sudah ada (di-inject lewat konstruktor, pola sama seperti `CheckoutService` menerima `RajaOngkirService`); pilih thread aktif, atau buka thread baru bila thread lama sudah lama tak aktif (default 12 jam) dan tidak sedang di tengah checkout.
- **`getContext`** — rakit konteks: seluruh fakta + ringkasan + pesan terakhir yang dikumpulkan sampai batas anggaran karakter, menjaga keutuhan pasangan `assistant(tool_calls)` ↔ `tool` (jendela tidak boleh mulai dari pesan `tool` yatim).
- **`appendMessages` / `putSummary`** — dibungkus `DB::transaction` (pola sama seperti `CheckoutService::createOrder`). `putSummary` sekaligus menandai pesan ≤ watermark sebagai sudah diringkas.
- **`upsertFacts` / `getFacts` / `getSessionState` / `putSessionState`** — CRUD idempoten.
- **`storeEmbedding` / `queryEmbeddings`** — simpan vektor + norma L2; pencarian **cosine dihitung di PHP** hanya atas baris milik pelanggan tersebut (jumlahnya kecil), dengan filter skor minimum dan daftar pengecualian.
- **`pruneMessages`** — hapus pesan lama yang sudah diringkas di luar N pesan terakhir; hanya menyentuh `wa_message` / `wa_message_embedding`. Ringkasan & fakta tidak pernah dihapus.

Error domain dilempar sebagai `InvalidArgumentException` / `NotFoundHttpException`, sejalan dengan penanganan di controller yang ada.

### 1.4 `WaMemoryController` + 10 endpoint

**File baru:** `app/Http/Controllers/Api/WaMemoryController.php` · **File diubah:** `routes/api.php`

Semua di dalam grup `Route::middleware('auth.apikey')` yang sudah ada (header `Token` = `SECRET_API_KEY`). Pola sama seperti controller lain: inject interface lewat konstruktor, `$request->validate()` inline, tangga `try/catch` (422 untuk `InvalidArgumentException`, 500 + `Log::error` untuk sisanya), balasan lewat `response()->json()`.

| Verb & path | Fungsi |
|---|---|
| `POST /api/wa/customers/resolve` | identitas + thread aktif |
| `GET  /api/wa/conversations/{conversation}/context` | ambil konteks (fakta + ringkasan + giliran terakhir, dengan anggaran) |
| `POST /api/wa/conversations/{conversation}/messages` | simpan pesan / catatan sistem |
| `PUT  /api/wa/conversations/{conversation}/summary` | tulis ringkasan bergulir |
| `GET  /api/wa/customers/{customer}/facts` | baca fakta |
| `POST /api/wa/customers/{customer}/facts` | upsert fakta |
| `GET  /api/wa/customers/{customer}/session-state` | baca cadangan state runtime |
| `PUT  /api/wa/customers/{customer}/session-state` | tulis-tembus state runtime |
| `POST /api/wa/customers/{customer}/embeddings` | simpan 1 vektor embedding |
| `POST /api/wa/customers/{customer}/embeddings/query` | cari embedding termirip (top-k) |

### 1.5 Konfigurasi + perintah retensi

**File baru:** `app/Console/Commands/PruneWaMessages.php` · **File diubah:** `config/services.php`, `app/Console/Kernel.php`, `.env.example`

Blok `services.wa_memory.*` di `config/services.php` (pola sama seperti `rajaongkir` / `invoice`): `gap_hours`, `default_char_budget`, `default_recent_turns`, `prune_days`, `prune_keep_min` — semuanya `env('WA_...', <default>)`, jadi **`.env` tidak perlu diubah** kecuali mau menyetel. Tidak ada secret baru — otentikasi tetap `services.api.key`.

Perintah `php artisan wa:prune-messages {--days=} {--keep-min=}` memanggil `pruneMessages()`. Penjadwalannya di `app/Console/Kernel.php` sengaja dibiarkan **nonaktif (dikomentari)** sampai diputuskan apakah scheduler dijalankan untuk deployment ini.

---

## 2. Pengujian yang sudah dilakukan

Bersifat *smoke test* dari sisi pengembang.

- `php -l` seluruh berkas baru: bersih (peringatan *deprecated* yang muncul berasal dari framework Laravel 8 di PHP 8.4, bukan dari kode ini).
- `php artisan route:list`: 10 route `/api/wa/*` teregistrasi, seluruhnya membawa middleware `ApiKeyMiddleware`.
- *Dependency injection*: `WaMemoryServiceInterface` ter-resolve ke `WaMemoryService` (dengan `WhatsappUserServiceInterface` ikut ter-inject otomatis).
- Config default terbaca: `gap_hours=12`, `default_char_budget=6000`.
- Endpoint menolak permintaan tanpa header `Token` → `401`.
- Endpoint `resolve` dengan body valid → `500` dengan pesan log `Base table or view not found: ... 'wa_customer' doesn't exist` — **wajar, akan menjadi `200` setelah skema SQL dijalankan.**

---

## 3. Status Terbuka / Langkah Lanjutan

1. **Wajib — jalankan skema SQL** (bukan lewat `artisan migrate`):
   ```
   mysql -u <user> -p testing_tokodaring_b2c < database/sql/wa_memory_schema.sql
   ```
   Verifikasi: `SHOW TABLES LIKE 'wa\_%';` → 6 baris. Rollback: `database/sql/wa_memory_drop.sql`.
2. Setelah tabel ada, lakukan verifikasi curl tiap endpoint (resolve → messages → context → facts → session-state → summary → embeddings) dengan header `Token`.
3. **Belum di-commit / push** — seluruh perubahan masih lokal.
4. **Utang teknis:** semua endpoint `/api/wa/*` memakai satu API key statis bersama — belum ada otorisasi per-pelanggan (konsisten dengan endpoint `user-addresses` / `orders` / `whatsapp/sync-user` yang sudah ada). Dicatat untuk diperbaiki bersama temuan analisis keamanan API yang disusun terpisah pada hari yang sama (dan sedang ditunda tindak lanjutnya).

### Perubahan lokal
```
M  .env.example
M  app/Console/Kernel.php
M  app/Providers/AppServiceProvider.php
M  config/services.php
M  routes/api.php
?? app/Console/Commands/PruneWaMessages.php
?? app/Http/Controllers/Api/WaMemoryController.php
?? app/Models/WaConversation.php
?? app/Models/WaCustomer.php
?? app/Models/WaCustomerFact.php
?? app/Models/WaMessage.php
?? app/Models/WaMessageEmbedding.php
?? app/Models/WaSessionState.php
?? app/Services/Interfaces/WaMemoryServiceInterface.php
?? app/Services/WaMemoryService.php
?? database/sql/wa_memory_schema.sql
?? database/sql/wa_memory_drop.sql
?? doc/LAPORAN-31-AGUSTUS-2026.md
```
