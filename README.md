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
- **API key hanya di server.** Browser tidak pernah memegang API key. Bila pengguna mengisi key
  sendiri di halaman Pengaturan, key itu dienkripsi dengan `APP_KEY` dan disimpan di session server —
  bukan di `localStorage`.
- **Tanpa login.** Tiap browser mendapat kunci acak di session; rekaman hanya bisa diakses dari
  session yang membuatnya.
- **Tahan rate limit.** Saat Groq membalas `429`, server meneruskan `Retry-After` ke browser dan
  browser mengulang segmen yang sama setelah menunggu — proses tidak diulang dari awal.

---

## Kebutuhan

| Komponen | Versi |
|---|---|
| PHP | 8.3+ (`ext-sqlite3` bila memakai SQLite) |
| Composer | 2.x |
| Node.js | 20+ |

## Instalasi

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
php artisan test          # 38 pengujian: HTTP, integrasi Groq & Anthropic, model, prompt
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

---

## Lisensi

MIT.
