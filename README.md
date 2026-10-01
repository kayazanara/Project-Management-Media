# Project Media — Sistem Manajemen Project Tim Media Hubbulkhoir

Aplikasi internal untuk mengatur **project, task, rapat, konten, dan kinerja** tim media
Hubbulkhoir. Dipakai Pembina Media, Ketua Media, Kepala Divisi, dan Anggota.

> 📚 **Mau revisi / menambah fitur?** Baca dulu:
> [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md) → [docs/FITUR.md](docs/FITUR.md) →
> [docs/PANDUAN-REVISI.md](docs/PANDUAN-REVISI.md). Jebakan-jebakan yang pernah
> bikin bug ada di [docs/CATATAN-PERBAIKAN.md](docs/CATATAN-PERBAIKAN.md).

---

## 1. Teknologi

| Bagian | Dipakai |
|---|---|
| Framework | Laravel 11 (PHP 8.3) |
| Database | SQLite (`database/project_media.sqlite`) |
| Tampilan | Blade + tema SB Admin 2 (Bootstrap 4) + Chart.js |
| Notifikasi | WhatsApp lewat **WAHA** (`WAHA_URL`, default `http://localhost:3000`) |
| Uji | PHPUnit (`php artisan test`), DB uji di memori |

## 2. Jalan di laptop (Laragon)

```bash
cd C:/laragon/www/project-media

# 1. kalau baru clone
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# 2. akun contoh untuk uji (JANGAN di server produksi)
php artisan db:seed --class=TestAccountSeeder

# 3. jalankan
php artisan serve --port=8123
```

Buka `http://127.0.0.1:8123`. **Perhatikan port**: kalau port 8000 sedang dipakai
aplikasi lain, gunakan `--port=8123`.

**Akun uji** (semua password `password`):

| Email | Peran |
|---|---|
| `pembina@test.com` | Pembina Media |
| `ketua@test.com` | Ketua Media |
| `kepala.lapangan@test.com`, `kepala.editing@test.com`, `kepala.broadcast@test.com`, `kepala.ai@test.com` | Kepala Divisi |
| `anggota.lapangan1@test.com`, `anggota.editing1@test.com`, `anggota.broadcast1@test.com`, `anggota.ai1@test.com` | Anggota |

## 3. Deploy ke server

```bash
cd /home/maskhoir/project-media
php artisan db:backup          # WAJIB sebelum tarik perubahan
git pull origin main
php artisan migrate            # WAJIB kalau ada migrasi baru (cek pesan commit)
php artisan config:clear && php artisan view:clear
php artisan test               # harus semua lulus sebelum dibuka ke pengguna
composer dump-autoload         # kalau ada file job/listener baru atau dihapus
```

Tekan **Ctrl+Shift+R** di browser sesudahnya (file CSS/JS lama sering tersimpan di cache).

## 4. Perintah penting

| Perintah | Kegunaan |
|---|---|
| `php artisan db:backup` | Backup DB (aman walau aplikasi sedang dipakai) |
| `php artisan reminders:send` | Kirim reminder WhatsApp H-3, H-1, dan lewat deadline (otomatis tiap hari 08:00) |
| `php artisan login:hitung-ulang-streak` | Hitung ulang streak login dari riwayat (opsi `--user=email`) |
| `php artisan login:reset-reward tes.animasi@test.com` | Untuk uji: hapus bonus login hari ini supaya animasi bisa dilihat lagi |
| `php artisan simulate:workflow` | Simulasi 9 tahap workflow (dry-run, otomatis dibatalkan) |
| `php artisan test` | Jalankan semua tes |

Backup tersimpan di `database/backups/`. **Jangan pernah** commit file `.sqlite`,
`.env`, atau folder backup.

## 5. Peta folder

```
app/
  Console/Commands/     perintah artisan (backup, reminder, streak, simulasi)
  Http/Controllers/     semua pengendali halaman & aksi
  Http/Middleware/      CheckDivisionAccess (hak akses per peran)
  Models/               Project, ProjectTask, User, Content, Meeting, ...
  Services/             aturan bisnis: ProjectWorkflowService, WhatsappNotifier,
                        KetuaMediaPerformance, DailyLoginReward
  Jobs/, Listeners/     pengiriman notifikasi antrean
resources/views/
  layouts/app.blade.php layout utama (sidebar, topbar, mode gelap)
  partials/tema.blade.php switch mode gelap/terang
  <fitur>/index.blade.php halaman tiap fitur
public/css/tema-gelap.css  seluruh aturan mode gelap
docs/                   dokumentasi (lihat daftar di bawah)
tests/Feature/          tes per fitur (190 tes)
```

## 6. Daftar dokumentasi

| Berkas | Isi |
|---|---|
| [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md) | Alur request, peran & hak akses, alur 9 tahap project, di mana aturan bisnis disimpan |
| [docs/FITUR.md](docs/FITUR.md) | Katalog fitur + file kunci + aturan pentingnya |
| [docs/DATABASE.md](docs/DATABASE.md) | Semua tabel & kolom, relasi, catatan kolom khusus |
| [docs/NOTIFIKASI-WA.md](docs/NOTIFIKASI-WA.md) | Siapa menerima notifikasi apa, aturan anti-dobel, cara uji |
| [docs/PANDUAN-REVISI.md](docs/PANDUAN-REVISI.md) | Resep menambah menu/halaman/kolom/notifikasi + checklist sebelum deploy |
| [docs/TES.md](docs/TES.md) | Daftar tes & cara menjalankannya |
| [docs/CATATAN-PERBAIKAN.md](docs/CATATAN-PERBAIKAN.md) | Riwayat bug, penyebab, dan perbaikannya (jangan sampai terulang) |

## 7. Kalau ada masalah

| Gejala | Cek dulu |
|---|---|
| Halaman error 500 sesudah pull | `php artisan migrate` sudah dijalankan? |
| Tampilan tidak berubah | `php artisan view:clear` lalu Ctrl+Shift+R |
| Tindakan terasa lama | Notifikasi WA memang dikirim setelah halaman pindah; kalau tetap berat, cek `WAHA_TIMEOUT` (default 5 detik) |
| Notifikasi WA tidak terkirim | `WAHA_ENABLED` di `.env`, status WAHA, dan tabel `notification_logs` |
| Mode gelap tidak muncul | Pastikan file `public/css/tema-gelap.css` ada dan ikon 🌙 di samping profil terlihat |
| Angka aneh di laporan | Buka [docs/CATATAN-PERBAIKAN.md](docs/CATATAN-PERBAIKAN.md) — mungkin kasus yang sama pernah terjadi |
