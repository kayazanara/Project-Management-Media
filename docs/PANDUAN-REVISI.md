# PANDUAN REVISI — Project Media

Resep siap pakai untuk menambah/mengubah fitur, plus daftar jebakan yang **sudah pernah
membuat bug nyata** di proyek ini.

---

## A. Checklist wajib sebelum & sesudah mengubah apa pun

1. **Backup dulu**: `php artisan db:backup` → buka file backup, pastikan isinya ada.
2. Buat perubahan.
3. **Tes baru ditulis LEBIH DULU** dan harus **gagal** sebelum kode diperbaiki.
4. Jalankan `php artisan test` → **semua tes lama juga harus tetap lulus**.
5. Uji nyata di browser (bukan hanya tes): buka halamannya, cek angkanya, ambil tangkapan layar.
6. Periksa isi yang akan di-commit: **tidak boleh** ada `.env`, `*.sqlite`, `database/backups/`,
   atau file uji sementara di `public/`.
7. Commit + push: `git add -A && git commit && git push origin main`.
8. Di server: `db:backup` → `git pull` → `migrate` (kalau ada) → `view:clear`/`config:clear`
   → `php artisan test` → **Ctrl+Shift+R**.

## B. Resep: menambah menu di sidebar

1. Tambahkan blok di `resources/views/layouts/app.blade.php`, **di dalam** kondisi peran
   yang sesuai (mis. `@if($user && $user->isPembina())`).
2. Pola yang dipakai:

```blade
<li class="nav-item {{ request()->routeIs('namafitur.*') ? 'active' : '' }}">
    <a class="nav-link" href="{{ route('namafitur.index') }}">
        <i class="fas fa-fw fa-ikon"></i>
        <span>Judul Menu</span>
    </a>
</li>
```

3. Menu **hanya untuk tampilan**. Pengamannya tetap middleware/`abort(403)` di controller.

## C. Resep: menambah halaman baru

1. Controller di `app/Http/Controllers/`, view di `resources/views/<nama>/index.blade.php`.
2. Route di `routes/web.php` di dalam grup `auth`, beri `name` yang rapi.
3. Kalau khusus peran tertentu, tambahkan middleware, mis. `divisi.access:pembina`.
4. Tambahkan tes: buka halamannya sebagai peran yang berhak (+ pastikan peran lain 403).
   `SmokeRoutesTest` akan ikut membuka route baru secara otomatis.

## D. Resep: menambah kolom database

1. `php artisan make:migration add_kolom_baru_to_tabel_table`.
2. **Aditif**: kolom baru harus boleh NULL atau punya default; **jangan** mengubah
   kolom lama yang sudah berisi data.
3. Tambahkan cast di model kalau berupa tanggal. Untuk tanggal murni (tanpa jam)
   **wajib** memakai `App\Casts\DateOnly` (bukan `date`).
4. `php artisan migrate`, lalu pastikan data lama utuh.

## E. Resep: menambah notifikasi WhatsApp

1. Buat template di `app/Services/WhatsappNotifier.php` (ikuti pola 13 template yang ada).
2. Tentukan **penerimanya** — patuhi aturan di [NOTIFIKASI-WA.md](NOTIFIKASI-WA.md):
   anggota project + Ketua Media; kepala divisi hanya kalau memang anggota;
   pengguna non-`Aktif` dikecualikan; pelaku aksi dikecualikan.
3. Kirim lewat `sendAndLog()` (otomatis dicatat di `notification_logs` + anti-dobel).
4. Untuk jalur yang dipicu pengguna (tombol), bungkus dengan
   `WhatsappNotifier::kirimSetelahResponse(...)` supaya halaman tidak menunggu.
5. Tulis tesnya dengan `Http::fake()` + `config(['services.waha.enabled' => true])`.

## F. Resep: menambah widget/angka di Workdesk

1. Buat method `buildXxx()` di `app/Http/Controllers/WorkdeskController.php`.
2. **Hindari query di dalam perulangan** (dulu grafik tren menjalankan 1 query per
   divisi per hari → 368 query). Ambil data sekali, olah di PHP.
3. Kalau menampilkan angka yang juga muncul di tempat lain, **pakai fungsi yang sama**
   (prinsip satu sumber angka).
4. Tanpa data → tampilkan `—`, bukan 0%.

## G. Resep: menyesuaikan penilaian (ambang/skor)

- Kinerja Ketua Media: konstanta `TARGET`, `PENALTI`, `TURUN_PER_HARI` di
  `app/Services/KetuaMediaPerformance.php`.
- Performa Divisi Workdesk: bobot indikator ada di `buildDivisionPerformance()`
  (`40` = completion, `40` = on-time, `20` = bobot).
- Poin: aturan ada di `MonitoringController::calculatePointsInRange()` dan
  `DailyLoginReward::POINTS_PER_DAY`.

## H. Jebakan yang SUDAH PERNAH jadi bug (baca sebelum mengubah!)

| # | Jebakan | Akibatnya | Cara menghindar |
|---|---|---|---|
| 1 | Cast tanggal memakai `date` untuk tanggal murni | `reward_date` tersimpan `2026-09-29 00:00:00` → filter periode tidak cocok | Pakai `App\Casts\DateOnly` untuk `task_date`, `reward_date` |
| 2 | Membandingkan `deadline` (datetime) dengan `now()` | Task/project yang jatuh tempo **hari ini** ikut dihitung telat | Bandingkan `->toDateString()` atau pakai `->endOfDay()->isPast()` |
| 3 | Memakai `users.last_login_date` sebagai dasar streak | Streak selalu kembali ke 1 (bug 2 Okt 2026) | Hitung streak dari riwayat `login_rewards` |
| 4 | Menulis urutan pemanggilan yang tidak jelas (catat dulu, periksa kemudian) | Pemeriksaan memakai nilai yang sudah ditimpa | Periksa dulu, baru menulis — atau jangan bergantung pada kolom yang bisa ditimpa |
| 5 | Query di dalam perulangan (per divisi × per hari) | Halaman berat (ratusan query) | Ambil sekali, olah di PHP |
| 6 | Angka kumulatif tanpa label | Grafik "tren" selalu naik dan selalu berakhir 100% | Kalau kumulatif, tulis "kumulatif"; kalau tren, hitung per hari |
| 7 | Dua tempat menghitung angka yang sama dengan syarat berbeda | Kartu menulis 0 item, tabel menulis 8 | Satu fungsi untuk satu angka |
| 8 | Membandingkan dua periode dengan basis berbeda | Tren bisa salah tanda | Periode sama panjang, basis sama (hanya yang benar-benar selesai) |
| 9 | Menulis 0% untuk "tidak ada data" | Divisi yang belum mulai tampak buruk | Tampilkan `—` |
| 10 | Mengirim WA di dalam request tanpa batas | Halaman terasa lambat (9 detik untuk 6 penerima) | Kirim setelah respons + `WAHA_TIMEOUT` 5 detik |
| 11 | `dispatch()->afterResponse()` padahal `QUEUE_CONNECTION=database` | Job menunggu worker → notifikasi tidak terkirim | Pakai `app()->terminating()` lewat `kirimSetelahResponse()` |
| 12 | Menambah aturan CSS mode gelap tanpa cakupan `html[data-theme="dark"]` | Tampilan mode terang ikut berubah | Selalu kunci dengan selector itu (ada tes yang menjaga) |
| 13 | Mengandalkan kelas CSS saja untuk elemen dengan `style="..."` (inline) | Warna terang tetap muncul di mode gelap | Pakai selector `[style*="..."]` atau ubah file tampilannya |
| 14 | Membuat beberapa anggota uji dengan email sama di tes | Tes mengubah penerima yang sama → hasil menipu | Pastikan email unik per anggota |
| 15 | Mengklaim "sudah diperbaiki" tanpa menjalankan tes | Bug baru ketahuan belakangan | Jalankan `php artisan test` dan uji nyata di browser |

| 16 | Tes "lolos" karena kode yang diuji tidak benar-benar berjalan (mis. `publish()` menolak rapat yang sudah published → percobaan kedua tidak terjadi) | Tes terlihat hijau tapi tidak menjaga apa pun | Pastikan jalur yang diuji benar-benar memicu kode — cek status prasyaratnya (dan buktikan tesnya GAGAL dulu) |
| 17 | Anti-dobel yang terlalu ketat | Pesan yang seharusnya terkirim malah hilang diam-diam | Sediakan selalu tes "penjaga" yang memastikan pesan berbeda/berulang TETAP terkirim |
| 18 | Menganggap "gagal kirim" = "tidak sampai" | Pesan yang timeout lalu dicoba ulang → orangnya menerima 2 pesan | Setelah gagal, tahan dulu (1 jam) sebelum mencoba lagi |

## I. Gaya penulisan kode di proyek ini

- Komentar & penamaan boleh Bahasa Indonesia (memang begitu sejak awal).
- Perbaikan bug diberi penanda `⚡ FIX (tanggal)` + penjelasan **kenapa**, bukan sekadar apa.
- Satu fungsi = satu tanggung jawab; aturan bisnis di `app/Services/`, bukan di blade.
- Jangan menambah komentar yang mengulang isi kode.

## J. Kalau harus memperbaiki bug di server produksi

1. `php artisan db:backup` **di server**, catat nama berkasnya.
2. Kalau perlu mengecek data langsung, gunakan mode hanya-baca dulu.
3. Perubahan struktur lewat migrasi (jangan edit DB manual).
4. Kalau ada data yang perlu dihitung ulang, buat **perintah artisan** (seperti
   `login:hitung-ulang-streak`) supaya bisa diulang dan diaudit.
