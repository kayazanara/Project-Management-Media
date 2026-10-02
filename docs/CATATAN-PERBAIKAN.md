# CATATAN PERBAIKAN — Project Media

Riwayat bug penting, **penyebabnya**, dan cara perbaikannya. Tujuannya: supaya
kesalahan yang sama tidak terulang. Kalau nanti ada angka yang terasa aneh,
baca dokumen ini dulu — mungkin kasusnya pernah terjadi.

Cara membaca: setiap entri berisi **gejala** (yang dikeluhkan), **sebab**, **perbaikan**,
dan **tes** yang menjaganya.

---

## 2 Oktober 2026

### 0. Pesan WA dobel — 4 celah ditutup (`…`)
- **Gejala:** "kenapa di notifikasi WA banyak pesan duplikat?"
- **Catatan jujur:** log di laptop berisi data DEMO dan **tidak menunjukkan duplikat**,
  jadi penyebab pasti di server belum bisa ditentukan dari situ. Yang bisa dilakukan:
  membaca kode lalu menutup **setiap celah yang memungkinkan** pesan terkirim dua kali.
- **Dugaan pertama yang SALAH (dikoreksi):** semula kusangka pesan ACC terkirim setiap kali
  ada pihak yang menyetujui. Setelah kode dibaca, ternyata pesan ACC **hanya dikirim saat
  KEDUA pihak sudah ACC** — jadi bukan itu penyebabnya.
- **Empat celah yang ditemukan & ditutup:**
  1. Percobaan **gagal** (timeout) dianggap belum terkirim, padahal bisa saja sudah sampai →
     dikirim lagi. Sekarang baris `failed` tetap dianggap bekas pengiriman selama **1 jam**.
  2. Anti-dobel per **akun**, bukan per **nomor** → dua akun bernomor sama menerima 2 pesan.
     Sekarang pemeriksaan memakai nomor.
  3. Pesan rapat/action item dicek dari teks persis → judul rapat diubah = tugas yang sama
     dikirim ulang. Sekarang memakai **kunci per tugas** (`notification_logs.dedupe_key`).
  4. Pengiriman ditunda sesudah respons → keputusan anti-dobel terjadi saat kirim, dua
     permintaan cepat bisa sama-sama lolos. Sekarang baris **dicadangkan** dulu
     (status `pending`) lewat `siapkan()`, baru dikirim lewat `kirim()`.
- **Tambahan:** pesan ACC = sekali per project (sesuai permintaan), dan **reopen menghapus
  catatan ACC lama** supaya ACC setelah dibuka ulang tetap dikabari (tidak hilang diam-diam).
- **Alat diagnosa:** `php artisan notif:cek-duplikat` (hanya baca) untuk dijalankan di server.
- **Tes:** `NotifikasiDobelTest` (9 tes) — 4 di antaranya **gagal dulu** sebelum diperbaiki.
- **Pelajaran tes:** dua tes pertama versi awalku **lolos secara palsu** karena `publish()`
  menolak rapat yang sudah dipublikasikan (jadi percobaan kedua tidak menjalankan kode sama
  sekali). Tes sekarang membatalkan status ke `draft` dulu supaya benar-benar memicu ulang.

### 1. Streak login mandek di 1 (`d15c6b1`)
- **Gejala:** "streak-nya stuck di 1 terus". Poin 2/hari tetap masuk, jadi tidak kelihatan.
- **Reproduksi:** login 5 hari berturut-turut lewat jalur login asli → streak tetap 1.
- **Sebab:** di `AuthController::login()` urutannya salah:
  `trackDailyLogin()` **menulis** `users.last_login_date = HARI INI` lebih dulu,
  baru `claim()` **memeriksa** "apakah last_login_date = KEMARIN?". Pemeriksaan itu
  selalu gagal → streak di-reset ke 1 setiap hari.
- **Perbaikan:** streak dihitung dari **riwayat klaim** (`login_rewards`) — sumber yang
  tidak bisa ditimpa oleh urutan pemanggilan, sekaligus otomatis membetulkan data lama.
  Urutan di `AuthController` juga dibalik sebagai lapis kedua.
  Ditambah perintah `php artisan login:hitung-ulang-streak [--user=…]`.
- **Tes:** `StreakLoginTest` (7 tes) + 1 tes baru di `DailyLoginRewardTest`.
- **Catatan:** tes lama lolos karena memanggil service langsung, bukan lewat `POST /login`
  — urutannya berbeda sehingga bugnya tidak terdeteksi.

### 2. Tren Workdesk & Performa Divisi salah hitung (`70d78ab`)
- **Gejala:** "trend itu perhitungannya bisa diperbaiki, terutama di bagian performa devisi".
- **Sebab (5 hal):**
  1. Grafik "tren" sebenarnya **kumulatif** → selalu naik 0→100%, hari terakhir selalu 100%.
     Terbukti dari tes: *Failed asserting that 100 is greater than 100*.
  2. Grafiknya menjalankan **1 query per divisi per hari** → 368 query (90 hari) hanya untuk
     satu grafik. Diukur dengan replikasi algoritma lama: 36 / 128 / 368 query untuk 7/30/90 hari.
  3. Performa divisi **selalu minggu ini** → filter 7/30/90 hari tidak berpengaruh,
     dan tren membandingkan dua basis berbeda (bukan apple-to-apple).
  4. Minggu lalu 0 task → ditulis **0%**, padahal divisi baru naik dari nol.
  5. Divisi tanpa task → semua **0%** (kelihatan buruk), padahal artinya tidak ada data.
     Ditambah: indikator **bobot** yang belum diisi Pembina ikut menekan skor.
- **Perbaikan:**
  - Tren = **jumlah task selesai per hari** (grafik batang, satuan "task").
  - Performa divisi mengikuti filter halaman, pembanding = periode sebelumnya **sama panjang**
    dan **satu basis** (hanya yang benar-benar selesai di dalam periode).
  - Naik dari nol ditulis **"baru"**; tanpa data ditulis **"—"**.
  - Indikator tanpa data **dilewati**, bobotnya dibagi proporsional (40/40 atau 40/40/20).
  - Efisiensi: seluruh halaman Workdesk kini **44 query** di semua periode (diukur).
- **Tes:** `WorkdeskTrendTest` (8) + `WorkdeskPerformaDivisiTest` (10), keduanya
  **gagal dulu** sebelum diperbaiki (6 dan 9 kegagalan).

## 29 September 2026

### 3. Notifikasi WA salah sasaran (`4c3949b`)
- **Gejala:** "ada pesan yang seharusnya tidak terkirim ke anggota tersebut malah terkirim".
- **Bukti:** dari `notification_logs`, project #21 (anggota hanya Broadcast & AI Research)
  juga dikirimi **Kepala Lapangan** dan **Kepala Editing**.
- **Sebab:** `SendDeadlineReminders::getRecipients()` menambahkan **semua** `kepala_divisi`,
  dan `ProjectController::approve()` juga mengirim ke semua kepala divisi tanpa memeriksa
  keanggotaan project.
- **Perbaikan:** semua jalur memakai daftar penerima yang sama —
  **anggota project + Ketua Media**; kepala divisi hanya kalau memang anggota;
  pengguna non-`Aktif` dan pelaku aksi dikecualikan. Ditambah aturan anti-dobel
  berjendela (30 menit untuk kejadian, permanen untuk reminder) supaya revisi/reopen
  kedua tetap terkirim.
- **Tes:** `NotifikasiWaTest` (12). Setelah perbaikan, 12 dari 12 project aktif berubah penerimanya.

### 4. Angka "Menunggu Tindakan" tidak sama dengan daftarnya (`7fac6d5`)
- **Gejala:** kartu pilar menulis `0`, tabelnya menulis 8 baris; "MEME Afrika" muncul 3×;
  kolom "Lama" kosong.
- **Sebab:** kartu dan tabel menghitung hal yang sama dengan syarat berbeda.
- **Perbaikan:** satu sumber angka (`menungguTindakan()`); revisi pada project yang sama
  dikelompokkan jadi satu baris; umur menunggu diambil berurutan dari `review_requested_at`
  → riwayat revisi → perubahan terakhir project (ditandai `≥N hari*`).
- **Tes:** 5 tes regresi (gagal dulu), lalu `KinerjaKetuaMediaTest` bertambah.

### 5. Daftar Kinerja tidak memuat ACC Pembina & project overdue (`749b9d9`)
- **Perbaikan:** daftar kini memuat semua yang menunggu tindakan **dan** project lewat
  deadline, dengan kolom **"Ditunggu oleh"** (Ketua Media / Pembina). Antrian milik Pembina
  **tidak** menurunkan skor Ketua Media.

### 6. Notifikasi WA membuat halaman terasa lambat (`ed5aaa6`)
- **Gejala:** "checklist, review, approve kok lama banget prosesnya".
- **Sebab:** WA dikirim **sinkron, satu per satu per penerima, di dalam request**.
  Diukur: WAHA 1,5 detik/pesan × 6 penerima = **9,2 detik**. Kalau WAHA menggantung dan
  timeout-nya 15 detik → sampai 90 detik. Database bukan penyebab (1–22 query, 0–5 ms).
- **Perbaikan:** pengiriman dipindahkan ke **setelah respons terkirim**
  (`kirimSetelahResponse()` memakai `app()->terminating()`), dan `WAHA_TIMEOUT` 15 → 5 detik.
  Hasil ukur: **9.231 ms → 61 ms** yang dirasakan pengguna, WA tetap terkirim.
- **Catatan:** **tidak** memakai `dispatch()->afterResponse()` karena `QUEUE_CONNECTION=database`
  akan membuat job menunggu worker dan notifikasi malah tidak terkirim.

### 7. Mode gelap (`ed5aaa6`)
- Permintaan Zev: switch di samping profil. Pilihan disimpan per akun + localStorage.
- Ditemukan & dibetulkan saat audit kontras 14 halaman: warna tautan menimpa warna tombol,
  `.text-*` terlalu gelap, papan peringkat, kotak berwarna inline, dan **sub-menu sidebar**
  (tulisan gelap di atas latar gelap).
- Hasil audit: **0 masalah kontras baru**; mode gelap justru menurunkan temuan
  (Kinerja 215 → 63, divisi 397 → 111).

### 8. "Setujui & Lanjut Sekarang" tanpa ACC Ketua Media (`17da96c`)
- Pembina bisa melanjutkan project yang mandek di `Review`.
- **Prinsip kejujuran:** `approved_by_ketua_at` **sengaja dibiarkan kosong**, halaman tetap
  menulis "Ketua Media: Belum ACC" + penanda "Dilanjutkan oleh Pembina" + alasan.

### 9. Perbaikan lain di tanggal yang sama
- `0c371cb` — user biasa bisa mengubah jabatan siapa pun; laporan divisi bisa ditambah/dihapus
  siapa pun (dua lubang hak akses).
- `642d59a` — N+1 pada grafik tren poin Monitoring (~150 query/halaman → 1 query).
- `2751e93`, `0ad5855` — kolom hitungan task di Monitoring memakai basis tanggal selesai;
  ringkasan divisi; pengaman `toggleTask`/`storeTask`.
- `d1d297e` — hapus anggota tim diperiksa relasinya dulu (mencegah data yatim).
- `3beaece` — audit: pengaman seeder, hapus project bersih, path storage, uji asap 33 halaman.

## 28 September 2026 dan sebelumnya

- `6fffc77` — Daily Login Reward (2 poin/hari) + animasi streak.
  Bug ikutan: `reward_date` tersimpan dengan jam (`2026-09-29 00:00:00`) sehingga filter
  periode tidak cocok → dibetulkan dengan cast `DateOnly`.
- `4764276`, `4f8aa26`, `0a1c82e` — animasi bonus login 4 babak, lalu diperbaiki karena
  tidak terlihat (pemudar bawaan overlay), ditambah transisi memudar ke dashboard.
- `6c65597` (27 Sep) — notifikasi rilis/reopen/revisi/complete diarahkan hanya ke anggota project.
  Saat itu **`approve` terlewat** dan baru diperbaiki 29 Sep di `4c3949b` — pelajaran:
  periksa **semua** jalur, bukan hanya yang disebutkan.
- `8a7bbcc1`/`3735adb` — pengaman: project tidak bisa "selesai" tanpa minimal 1 konten.

---

## Pola yang bisa dipetik

1. **Kebanyakan bug di proyek ini berasal dari "dua tempat menghitung hal yang sama
   dengan syarat berbeda".** Solusinya selalu: satu fungsi untuk satu angka.
2. **Tanggal adalah sumber bug berulang:** cast yang salah, membandingkan datetime dengan
   date, dan urutan menulis-vs-memeriksa.
3. **Tes yang memanggil service langsung bisa menyembunyikan bug** karena urutan
   pemanggilannya berbeda dengan jalur asli pengguna.
4. **Tanpa data jangan ditulis 0** — itu membuat laporan menyesatkan.
5. **Lambat ≠ database.** Ukur dulu (query, waktu), jangan menebak.
