# KATALOG FITUR — Project Media

Setiap fitur: **apa gunanya**, **file kunci**, dan **aturan penting** yang harus dijaga
kalau nanti direvisi.

---

## 1. Login, bonus harian, dan profil

| Bagian | File kunci |
|---|---|
| Login/logout | `app/Http/Controllers/AuthController.php`, `resources/views/auth/login.blade.php` |
| Bonus login + streak | `app/Services/DailyLoginReward.php`, `app/Console/Commands/HitungUlangStreakLogin.php` |
| Animasi bonus | `resources/views/layouts/app.blade.php` (4 babak: bergulir → melambat → meledak → poin, lalu **memudar** ke dashboard) |
| Profil | `app/Http/Controllers/ProfileController.php`, `resources/views/profile/` |

Aturan penting:
- Bonus **2 poin, hanya sekali per hari**. Dijamin `UNIQUE(user_id, reward_date)` di
  `login_rewards`. Klaim kedua → `null` (overlay tidak muncul).
- **Streak dihitung dari RIWAYAT klaim** (`login_rewards`), bukan dari
  `users.last_login_date`. Riwayat berakhir kemarin → lanjut; bolong → mulai 1.
  Streak yang sudah bolong = `0` (jujur, bukan dikarang).
- Keluar-masuk lagi di hari yang sama tidak menambah poin, dan overlay tidak muncul.

## 2. Dashboard utama

File: `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard/index.blade.php`.

Isi: 4 kartu KPI, grafik produktivitas, **peringkat anggota** (kartu emas/perak/perunggu +
popup hover), rangkuman aktivitas divisi, daftar project berjalan, dan daftar task selesai
per hari (dikelompokkan per hari).

Aturan: poin memakai aturan di ARSITEKTUR.md §7 (bobot ÷ kontributor, per `completed_at`,
status `Completed`) dan **ditambah** poin bonus login pada periode yang sama.

## 3. Project Management (inti aplikasi)

File: `app/Http/Controllers/ProjectController.php`, `app/Services/ProjectWorkflowService.php`,
`resources/views/projects/`.

- **9 tahap** beserta transisinya → lihat `docs/ARSITEKTUR.md` §6.
- **Dua ACC**: `approved_by_pembina_at` dan `approved_by_ketua_at`. Project lanjut ke
  `Upload` hanya kalau keduanya terisi.
- ⚡ **Setujui & Lanjut Sekarang (override Pembina)**: hanya Pembina, hanya saat status
  `Review`, **wajib alasan ≥ 10 karakter**. `approved_by_ketua_at` **sengaja dibiarkan
  kosong** dan halaman menampilkan "Ketua Media: Belum ACC" + penanda "Dilanjutkan oleh
  Pembina" + alasan + siapa & kapan. Jejak masuk `project_revisions` (type
  `approve_override`, `resolved_at` terisi agar tidak dihitung revisi menggantung).
- **Tandai Selesai** butuh minimal **1 konten** (raw/stock/activity) pada project itu.
- **Reopen** (batalkan selesai) hanya ke `Review`/`Upload`, wajib alasan ≥ 10 karakter.
- Angka **progress** dihitung dari task selesai, dan `is_overdue` dihitung dari deadline
  vs **akhir hari** (bukan tengah malam).

## 4. Task & bobot

File: `ProjectController` (`storeTask`, `updateTask`, `destroyTask`, `toggleTask`,
`submitScoring`), `resources/views/projects/show.blade.php`.

- Checklist task ditandai dengan `status = Completed` + `completed_at`.
- **Bobot** diisi Pembina pada tahap `Task Review`; poin dibagi ke seluruh kontributor.
- Kontributor bisa dipilih; kalau belum ada kontributor, PIC (`assigned_to`) yang dihitung.

## 5. Workdesk (khusus Pembina & Ketua Media)

File: `app/Http/Controllers/WorkdeskController.php`, `resources/views/workdesk/index.blade.php`.

11 widget: ringkasan, daftar tindakan, peringatan, **performa divisi**, **tren harian**,
anggota teratas/terbawah, kesehatan project, rapat mendatang, rapat terakhir, kesehatan
sistem (khusus Pembina), dan grafik.

Aturan penting (baru diperbaiki 2 Okt 2026):
- **Tren harian = JUMLAH task selesai per hari** (grafik batang). Bukan persentase
  kumulatif — versi lama selalu naik 0→100% dan hari terakhir selalu 100%.
- **Performa Divisi mengikuti filter 7/30/90 hari.** Pembanding tren = periode
  sebelumnya **sama panjang** dan **satu basis** (hanya yang benar-benar selesai
  di dalam periode).
- Naik dari nol ditulis **"baru"**, bukan 0%.
- Divisi tanpa task ditulis **"—"**, bukan 0%.
- Indikator **bobot** yang belum diisi dilewati; bobot nilainya dibagi proporsional
  (40/40 atau 40/40/20) sehingga skor tidak tertekan tanpa sebab.
- Efisiensi: seluruh halaman **44 query** di semua periode. Versi lama butuh
  368 query (90 hari) hanya untuk grafik tren.

## 6. Monitoring Tim (khusus Pembina)

File: `app/Http/Controllers/MonitoringController.php`, `resources/views/monitoring/index.blade.php`.

Isi: workload balance, ketepatan waktu, peringatan deadline, ringkasan per divisi,
direktori tim dengan popup profil + **riwayat poin**.

Aturan penting:
- Ketepatan waktu dibandingkan **per tanggal** (`completed_at->toDateString() <= task_date`).
  Membandingkan waktu penuh dengan tanggal pernah membuat angkanya selalu 0%.
- Poin hanya untuk task **`Completed`** dan `completed_at` **di dalam periode**, ditambah bonus login.
- Hanya anggota **`Aktif`** yang dihitung.

## 7. Kinerja Ketua Media (khusus Pembina)

File: `app/Services/KetuaMediaPerformance.php`, `app/Http/Controllers/KetuaMediaPerformanceController.php`,
`resources/views/kinerja/ketua-media.blade.php`.

Empat pilar: **Kesehatan Divisi 40%**, **Kecepatan Alur 20%**, **Keterlibatan 20%**,
**Ketuntasan 20%**. Ambang & penalti tersimpan di konstanta (`TARGET`, `PENALTI`,
`TURUN_PER_HARI`) — kalau mau menyesuaikan penilaian, ubah di situ.

Aturan penting:
- Pilar tanpa data **dikeluarkan**, bobotnya dibagi proporsional ke pilar lain.
  Periode tanpa jejak data → skor `—`, bukan 0.
- Metrik "kondisi sekarang" hanya dihitung kalau periode mencakup hari ini
  (supaya tren bulan lampau tidak berubah tiap kali halaman dibuka).
- Daftar **"Menunggu Tindakan"** = satu fungsi `menungguTindakan()`; angka di kartu,
  badge, dan jumlah baris tabel **harus sama**. Antrian milik Pembina ditampilkan
  tapi **tidak** menurunkan skor Ketua Media.
- Project lama tanpa penanda waktu memakai perkiraan yang **ditandai** `≥N hari*`.
- Project tanpa anggota dilaporkan dengan peringatan (tidak bisa dipetakan ke divisi).
- Ada **Catatan Evaluasi Pembina** per periode (tersimpan + riwayat + hapus).

## 8. Rapat & Notulen

File: `app/Http/Controllers/MeetingController.php`, `resources/views/meetings/`.

- CRUD rapat, notulen, keputusan, **action items** (dengan PIC + deadline + tanda selesai).
- **Publish** → mengirim notifikasi WA ke semua pemimpin berstatus **Aktif**.
- Action item → notifikasi WA ke PIC-nya.
- Export **PDF** & **Word**, plus halaman cetak berdiri sendiri.

## 9. Konten: Stock, Bahan Raw, dan Activity

File: `app/Http/Controllers/ContentController.php`, `resources/views/contents/`.

| Halaman | `upload_type` | Kegunaan |
|---|---|---|
| Stock Management | `content_stock` | Konten siap pakai |
| Bahan Raw | `raw` | Bahan mentah, diarahkan ke divisi tujuan (Editing/Broadcast/AI Research/Lapangan) |
| Activity | `activity` | Dokumentasi kegiatan (mis. live kajian) |

Aksi lain: **ambil/lepas** bahan raw oleh divisi, **post/unpost** konten, isi
**metadata broadcast**, ubah status, hapus (dengan pengecekan relasi).

## 10. Tim, Divisi, dan Laporan

| Fitur | File |
|---|---|
| Kelola anggota | `app/Http/Controllers/TeamController.php`, `resources/views/team/` |
| Halaman per divisi + laporan harian | `app/Http/Controllers/DivisionController.php`, `resources/views/divisions/` |
| Cetak laporan | `app/Http/Controllers/ReportController.php`, `resources/views/reports/` |

Halaman cetak (`reports/print.blade.php`) punya tampilan sendiri dan **selalu terang**
walau mode gelap aktif.

## 11. Notifikasi WhatsApp

Lihat dokumen khusus: [NOTIFIKASI-WA.md](NOTIFIKASI-WA.md).

## 12. Mode gelap / terang

File: `public/css/tema-gelap.css`, `resources/views/partials/tema.blade.php`,
`app/Http/Controllers/PreferenceController.php`.

- Switch ada di **topbar, tepat di samping menu profil** (ikon 🌙/🌞). Berubah tanpa reload.
- Pilihan disimpan **per akun** (`users.theme_mode`) + **localStorage** (anti-kedip).
- **Semua** aturan CSS dikunci `html[data-theme="dark"]` → mode terang tidak berubah.
- Halaman cetak dipaksa terang lewat `@media print`.
- Mode gelap sudah diaudit kontras WCAG di 14 halaman: **tidak ada masalah baru**.
