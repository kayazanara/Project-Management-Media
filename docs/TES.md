# TES — Project Media

```bash
php artisan test                    # semua tes
php artisan test --filter=StreakLoginTest     # satu berkas
php artisan test --filter=nama_tes            # satu tes
```

Tes memakai database **di memori** (`phpunit.xml` → `DB_DATABASE=:memory:`), jadi
**database asli tidak pernah tersentuh**.

Jumlah saat dokumen ini ditulis: **190 tes / 766 assertions**, semuanya lulus.

---

## 1. Daftar berkas tes

| Berkas | Fokus |
|---|---|
| `SmokeRoutesTest` | Membuka **semua route GET** sebagai 4 peran (pembina, ketua media, kepala divisi, anggota) — memastikan tidak ada halaman yang error ≥ 500 |
| `HalamanSmokeTest` | Pemeriksaan asap halaman-halaman utama |
| `ProjectWorkflowTest` | Alur project 9 tahap, transisi status, ACC dua pihak, rilis, complete, reopen |
| `ApproveOverrideTest` (12) | ⚡ "Setujui & Lanjut Sekarang": `approved_by_ketua_at` tetap kosong, jejak + alasan, hak akses, notifikasi khusus |
| `KinerjaKetuaMediaTest` (27+) | 4 pilar, bobot, "menunggu tindakan" (angka = jumlah baris), revisi dikelompokkan, project overdue & menunggu ACC Pembina masuk daftar, periode tanpa data = `—` |
| `MonitoringTest` | Regresi Monitoring: on-time per tanggal, workload ikut filter periode, ringkasan divisi |
| `WorkdeskTest` | Widget Workdesk & perhitungan dasarnya |
| `WorkdeskTrendTest` (8) | ⚡ Tren = jumlah task selesai **per hari**, bisa naik-turun, abaikan anggota non-aktif, dipisah per divisi, ikut filter hari, tidak membanjiri query |
| `WorkdeskPerformaDivisiTest` (10) | ⚡ Performa divisi: ikut filter hari, tren apple-to-apple, naik dari nol = "baru", tanpa data = `—`, bobot kosong tidak menekan skor |
| `NotifikasiWaTest` (12) | Penerima tiap jalur (termasuk yang **tidak boleh** menerima), anti-dobel, kejadian berulang tetap terkirim, job mati sudah dihapus |
| `StreakLoginTest` (7) | ⚡ Bug streak: 5 hari berturut = streak 5, bolong = reset, data lama dihitung ulang, perintah artisan, tidak dobel sehari |
| `DailyLoginRewardTest` | Bonus 2 poin, sekali sehari, riwayat, tampil di total poin |
| `ContentStockTest` | Stok konten, bahan raw, activity, ambil/lepas |
| `DashboardPointsTest` | Poin di Dashboard (termasuk gabungan bonus login) |
| `TemaGelapTest` (15) | Simpan pilihan tema, tolak nilai tak dikenal, `data-theme` sesuai akun, anti-kedip, posisi tombol sebelum profil, **semua aturan CSS dikunci mode gelap**, cetak tetap terang |
| `WahaStatusTest` | Status/konfigurasi WAHA |
| `ExampleTest` (Feature & Unit) | Bawaan Laravel |

## 2. Kebiasaan menulis tes di proyek ini

1. **Tes ditulis lebih dulu dan harus GAGAL** sebelum kode diperbaiki. Kalau tesnya
   sudah lulus sebelum diperbaiki, tesnya tidak membuktikan apa pun.
2. Satu bug = satu tes yang menyebut gejalanya, mis.
   `test_streak_naik_walau_last_login_date_sudah_diisi_hari_ini`.
3. Untuk tanggal, pakai `Carbon::setTestNow('2026-10-02 09:00:00')` supaya tes
   tidak bergantung jam komputer dan hasilnya selalu sama.
4. Data uji dibuat sendiri di dalam tes (jangan bergantung data asli), dan setiap
   anggota uji memakai **email unik**.
5. Jalur yang diuji sebaiknya **jalur yang benar-benar dipakai pengguna**
   (mis. `POST /login`), bukan memanggil service langsung — bug streak dulu lolos
   justru karena tes memanggil service langsung sehingga urutannya berbeda.
6. Nama tes Bahasa Indonesia dan menjelaskan **perilaku yang diharapkan**.

## 3. Perintah bantu saat memeriksa

```bash
php artisan db:seed --class=TestAccountSeeder    # akun uji
php artisan login:reset-reward tes.animasi@test.com   # ulangi animasi bonus login
php artisan login:hitung-ulang-streak            # betulkan streak dari riwayat
php artisan simulate:workflow                    # simulasi 9 tahap (dry-run)
```

## 4. Kalau tes gagal padahal kode tidak diubah

| Gejala | Kemungkinan |
|---|---|
| Gagal di `meetings.start_time` | Ada rapat uji yang belum mengisi `start_time`/`end_time` (NOT NULL) |
| Gagal karena tanggal | Tes memakai tanggal tetap sementara kode memakai `now()`; samakan dengan `Carbon::setTestNow` |
| Gagal karena jumlah query | Widget baru menambah query di dalam perulangan |
| Gagal di SmokeRoutesTest | Ada route baru yang belum ditangani placeholder ID-nya |
