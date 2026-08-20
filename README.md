# NotulensiAI

Aplikasi Laravel untuk mentranskripsi rekaman rapat tanpa batas durasi, lalu mengubah transkrip
mentah menjadi notulensi rapat yang rapi.

Versi sebelumnya berupa satu berkas HTML statis yang menyimpan API key di `localStorage` dan
mengirimkannya langsung dari browser. Versi ini memindahkan seluruh pemanggilan API ke sisi server,
menyimpan hasilnya di basis data, dan merender Markdown notulensi di server.

---

## Cara kerja

```
Browser                                  Laravel                         Layanan
───────                                  ───────                         ───────
decode audio (Web Audio API)
  │
  ├─ potong per segmen + resample ──►  POST /api/recordings
  │   16 kHz mono WAV                    (buat catatan rekaman)
  │
  └─ kirim segmen satu per satu ─────►  POST /api/recordings/{id}/chunks ──► Groq Whisper
                                          simpan segmen, hitung progres
                                                    │
                                        POST /api/recordings/{id}/minutes ──► Claude
                                          simpan notulensi (Markdown → HTML)
```

- **Pemotongan di browser.** Berkas audio didekode, dipotong, dan di-*resample* menjadi WAV 16 kHz
  mono sebelum diunggah. Ukuran unggahan jadi ±1,9 MB per menit audio, sehingga berkas rapat berjam-jam
  tetap bisa diproses tanpa menyentuh batas ukuran unggahan.
- **Daftar ringan, detail sesuai kebutuhan.** Halaman awal hanya memuat ringkasan tiap rekaman;
  transkrip, segmen, dan notulensi diambil lewat `GET /api/recordings/{id}` saat rekaman dibuka.
- **API key hanya di server.** Browser tidak pernah memegang API key. Bila pengguna mengisi key
  sendiri di halaman Pengaturan, key itu dienkripsi dengan `APP_KEY` dan disimpan di session server —
  bukan di `localStorage`.
- **Tanpa login.** Tiap browser mendapat kunci acak di session; rekaman hanya bisa diakses dari
  session yang membuatnya.
- **Tahan rate limit.** Saat Groq membalas `429`, server meneruskan `Retry-After` ke browser dan
  browser mengulang segmen yang sama setelah menunggu — proses tidak diulang dari awal.

---

## Beberapa pengguna sekaligus

Aplikasi ini tidak memakai login, tetapi tiap browser tetap terpisah penuh:

- **Kepemilikan.** Setiap browser mendapat kunci acak (UUID) di session server. Rekaman diikat ke
  kunci itu, dan pencarian rekaman selalu dibatasi kunci milik pemanggil — membuka id rekaman orang
  lain menghasilkan 404, bukan transkripnya.
- **Preferensi dan API key** (bahasa, durasi segmen, key milik pengguna) juga per session.
- **Penulisan bersamaan.** Tiap segmen menempati barisnya sendiri dan ditulis dengan satu pernyataan
  atomik, sehingga dua permintaan yang tumpang tindih tidak saling menghapus hasil.
- **SQLite disetel untuk banyak penulis** (`WAL`, `busy_timeout`, transaksi `IMMEDIATE` — lihat
  `config/database.php`). Tanpa setelan ini, unggahan segmen dari dua orang serentak gagal dengan
  `database is locked`.
- **Cache memakai berkas, bukan basis data** (`CACHE_STORE=file`). Penghitung rate limit menulis ke
  cache setiap permintaan; bila cache-nya ikut di SQLite, penghitung itu berebut kunci tulis dengan
  unggahan segmen.

Tiap segmen menempati satu baris di tabel `recording_segments` dengan indeks unik
`(recording_id, position)` dan ditulis dengan satu pernyataan *upsert*. Tidak ada pola
baca-ubah-tulis, sehingga dua unggahan yang bersamaan tidak pernah saling menimpa maupun
saling mengunci.

Uji yang dijalankan: dua sesi browser mengunggah berkas berbeda dan mentranskripsikannya bersamaan —
masing-masing hanya melihat rekamannya sendiri; lalu 10 unggahan segmen paralel ke satu rekaman —
seluruh 10 segmen tersimpan, tanpa satu pun permintaan gagal, termasuk saat transaksi dipaksa ke
mode `DEFERRED` (perilaku SQLite di PHP 8.2/8.3).

Catatan penerapan:

| Hal | Anjuran |
|---|---|
| Server | `php artisan serve` hanya melayani satu permintaan pada satu waktu. Untuk dipakai bersama, jalankan di belakang nginx + PHP-FPM, atau setel `PHP_CLI_SERVER_WORKERS`. |
| Basis data | SQLite cukup untuk satu tim kecil. Untuk puluhan pengguna serentak, pindah ke MySQL/PostgreSQL — cukup ganti `DB_CONNECTION`. |
| Masa session | `SESSION_LIFETIME` menentukan berapa lama riwayat rekaman tetap bisa dibuka (bawaan repo ini 14 hari). |
| Kuota API | Bila memakai satu key server, seluruh pengguna berbagi kuota Groq/Anthropic yang sama. |

## Antarmuka

Tiap bagian layar dibuat untuk menjawab pertanyaan yang muncul saat menunggu proses panjang:

| Bagian | Yang ditampilkan |
|---|---|
| Kepala berkas | durasi, ukuran, bahasa, segmen selesai/total, jumlah kata, waktu ditambahkan |
| Kartu progres | segmen ke berapa, persentase, waktu berjalan, perkiraan sisa, rata-rata per segmen |
| Statistik transkrip | jumlah kata, karakter, jumlah × durasi segmen, waktu selesai |
| Tampilan per segmen | penanda waktu `00:00–02:00` untuk mencocokkan hasil dengan audio asli |
| Daftar periksa | langkah yang sudah beres dan yang belum, beserta petunjuk tindakannya |
| Jejak notulensi | model yang dipakai, waktu pembuatan, panjang hasil |
| Pengaturan | sumber tiap API key, model aktif, ukuran per segmen, permintaan per jam audio, batas unggah server |

Tema mengikuti sistem secara bawaan dan bisa dikunci ke terang atau gelap.

## Kebutuhan

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | **8.2 – 8.4** | Ekstensi `sqlite3`, `mbstring`, `curl`, `openssl`, `fileinfo` — semuanya aktif secara bawaan di XAMPP |
| Composer | 2.x | |
| Node.js | 20+ | Hanya untuk membangun aset (CSS/JS) |

Cek versi PHP Anda dengan `php -v`. Bila di bawah 8.2, perbarui PHP (mis. XAMPP 8.2 ke atas)
sebelum melanjutkan — `composer install` akan menolak memasang.

## Instalasi

### Linux / macOS

```bash
git clone <repo-ini>
cd SpeechtotextAI

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

npm install
npm run build

php artisan serve
```

### Windows (PowerShell)

Perintah `cp` dan `touch` tidak ada di PowerShell, jadi pakai padanannya:

```powershell
git clone <repo-ini>
cd SpeechtotextAI

composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item database\database.sqlite -ItemType File -Force
php artisan migrate

npm.cmd install
npm.cmd run build

php artisan serve
```

Dua hal yang sering menghadang di Windows:

- **`npm : ... cannot be loaded because running scripts is disabled on this system`.**
  PowerShell memblokir skrip `npm.ps1`. Cara tercepat: panggil `npm.cmd` seperti di atas.
  Alternatif permanen (sekali saja, tidak butuh hak admin):
  `Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned`
- **`php` tidak dikenali.** Tambahkan folder PHP XAMPP (mis. `C:\xampp\php`) ke PATH, atau jalankan
  perintahnya lewat `C:\xampp\php\php.exe`.

Buka `http://localhost:8000`, lalu isi API key di tab **Pengaturan** (atau isi `.env`, lihat di bawah).

Untuk pengembangan dengan hot reload: `npm run dev` di terminal terpisah.

---

## Konfigurasi

Semua opsi ada di `config/notulensi.php` dan bisa ditimpa lewat `.env`:

| Variabel | Default | Keterangan |
|---|---|---|
| `GROQ_API_KEY` | — | Key transkripsi. Gratis di [console.groq.com/keys](https://console.groq.com/keys) |
| `GROQ_MODEL` | `whisper-large-v3-turbo` | Model Whisper di Groq |
| `ANTHROPIC_API_KEY` | — | Key untuk pembuatan notulensi |
| `ANTHROPIC_MODEL` | `claude-opus-5` | Model Claude |
| `ANTHROPIC_EFFORT` | `medium` | `low` … `max`; makin tinggi makin teliti dan makin mahal |
| `NOTULENSI_ALLOW_USER_KEYS` | `true` | Bila `false`, pengguna wajib memakai key dari server |
| `NOTULENSI_DEFAULT_LANGUAGE` | `id` | Bahasa audio default |
| `NOTULENSI_CHUNK_SECONDS` | `60` | Durasi tiap segmen |
| `NOTULENSI_MAX_RECORDINGS` | `50` | Batas rekaman tersimpan per session |

API key boleh diisi lewat `.env` (dipakai semua pengguna) atau lewat halaman Pengaturan (berlaku untuk
session pengguna itu saja dan menimpa key server).

### Durasi segmen dan batas unggahan PHP

Satu detik audio = 32 KB (16 kHz × 16 bit mono), jadi segmen 60 detik ≈ 1,9 MB. Bawaan PHP
(`upload_max_filesize = 2M`) hanya cukup untuk sekitar 65 detik; aplikasi membaca batas tersebut,
menurunkan pilihan durasi segmen secara otomatis, dan menampilkan angka maksimumnya di halaman
Pengaturan. Untuk segmen yang lebih panjang (lebih sedikit permintaan API), naikkan di `php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 24M
```

---

## Pengujian

```bash
php artisan test          # 44 pengujian: HTTP, integrasi Groq & Anthropic, model, prompt
./vendor/bin/pint         # format kode
npm run build             # kompilasi aset
```

Pengujian tidak menyentuh jaringan: panggilan Groq dipalsukan dengan `Http::fake()`
(plus `Http::preventStrayRequests()`), sedangkan SDK Anthropic memakai transport PSR-18 palsu
(`tests/Support/FakeTransporter.php`) sehingga bentuk permintaan dan penanganan penolakan model
tetap teruji.

---

## Struktur

```
app/
├── Enums/RecordingStatus.php            status rekaman
├── Exceptions/                          error transkripsi & notulensi (punya render() sendiri)
├── Http/
│   ├── Controllers/                     Home, Recording, TranscriptionChunk, Minutes, Settings
│   └── Requests/                        aturan validasi tiap endpoint
├── Models/Recording.php                 rekaman + segmen transkrip
├── Services/
│   ├── Minutes/                         prompt + integrasi Claude (SDK resmi)
│   └── Transcription/GroqTranscriber    pengirim segmen ke Groq
└── Support/                             kredensial, preferensi, batas unggah, Markdown
resources/
├── css/app.css                          token tema terang/gelap + komponen Tailwind
├── js/
│   ├── audio/                           dekode, pemotongan, resample, encoder WAV
│   ├── api.js                           pembungkus fetch + CSRF + penanganan error
│   └── notulensi.js                     state UI (Alpine)
└── views/                               halaman utama + partial
tests/
├── Feature/                             endpoint HTTP dan integrasi layanan
└── Unit/                                model & penyusunan prompt
```

---

## Perubahan dari versi statis

| Sebelumnya | Sekarang |
|---|---|
| API key di `localStorage`, dikirim dari browser | Key di server (`.env` atau session terenkripsi) |
| Markdown dirender dengan rangkaian regex | Dirender server-side dengan CommonMark, HTML mentah dibuang |
| Nama berkas dimasukkan ke DOM lewat `innerHTML` | Template Alpine (teks selalu di-escape) |
| Segmen 20–25 detik, WAV stereo penuh | Segmen sampai menit, WAV 16 kHz mono (±6× lebih kecil) |
| `cancel()` hanya menandai variabel; `fetch` tetap jalan | `AbortController` membatalkan permintaan dan jeda tunggu |
| Durasi segmen tidak ikut tersimpan | Seluruh preferensi tersimpan di session |
| Hasil hilang saat halaman dimuat ulang | Transkrip & notulensi tersimpan di basis data |
| Tab tanpa peran ARIA, dropzone tak bisa diakses keyboard | `role="tab"`, dropzone berupa `<button>`, live region, fokus terlihat |
| Hanya tema gelap | Tema terang/gelap/ikut sistem |
| Status berupa ikon emoji tanpa keterangan | Lencana status, metadata berkas, statistik, dan perkiraan waktu selesai |
| Transkrip hanya satu blok teks | Bisa dilihat per segmen dengan penanda waktu |

---

## Lisensi

MIT.
