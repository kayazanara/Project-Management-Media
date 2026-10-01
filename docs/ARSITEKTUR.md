# ARSITEKTUR — Project Media

Dokumen ini menjelaskan **bagaimana aplikasi ini disusun**: alur permintaan, siapa boleh
mengakses apa, di mana aturan bisnisnya, dan bagaimana alur kerja project 9 tahap berjalan.

---

## 1. Alur satu permintaan (request)

```
Browser
  │  GET/POST ke route (routes/web.php)
  ▼
Middleware 'auth'  ── belum login? → /login
  │
Middleware 'divisi.access:<peran>'  ── tidak berhak? → 403
  │  (lihat CheckDivisionAccess, bagian 2)
  ▼
Controller (app/Http/Controllers)
  │  • validasi input
  │  • panggil Service untuk aturan bisnis
  │  • simpan lewat Model (Eloquent)
  ▼
Service (app/Services)  ← SEMUA aturan bisnis berat ada di sini
  │  • ProjectWorkflowService   (perubahan status project, revisi, kesiapan rilis)
  │  • KetuaMediaPerformance    (skor kinerja Ketua Media 4 pilar)
  │  • DailyLoginReward         (bonus login 2 poin + streak)
  │  • WhatsappNotifier         (template + pengiriman notifikasi WA)
  ▼
View (resources/views)  ← layout utama: layouts/app.blade.php
  │
  ▼
Respons HTML/JSON  → browser
  (notifikasi WA dikirim SETELAH respons terkirim, lihat NOTIFIKASI-WA.md)
```

## 2. Peran & hak akses

Peran tersimpan di `users.division_role`:

| Nilai `division_role` | Artinya |
|---|---|
| `pembina` | Pembina Media (akses paling luas) |
| `ketua_media` | Ketua Media |
| `kepala_divisi` | Kepala Divisi |
| `anggota` | Anggota tim |

Nilai `divisi.access:<peran>` yang dikenali (`CheckDivisionAccess`):

| Parameter | Boleh masuk |
|---|---|
| `pembina` | hanya Pembina |
| `ketua_media` | hanya Ketua Media |
| `kepala_divisi` | hanya Kepala Divisi |
| `anggota` | hanya Anggota |
| `manager` | **Pembina atau Ketua Media** |
| `broadcast` / `lapangan` / `editing` / `ai_research` | berdasarkan teks `users.division` (kompatibilitas lama) |
| `pembina\|ketua_media` | gabungan dengan tanda `\|` (OR) |

Contoh pemakaian di `routes/web.php`:

| Halaman | Middleware |
|---|---|
| Workdesk | `divisi.access:manager` |
| Monitoring Tim | `divisi.access:pembina` |
| Kinerja Ketua Media | `divisi.access:pembina` |
| Halaman lain (project, konten, rapat, tim, laporan) | `auth` + pengecekan di dalam controller/menu |

Catatan: **menu di sidebar juga difilter** (`layouts/app.blade.php`) memakai
`$user->isPembina()`, `$user->isManager()`, dan sejenisnya. Menyembunyikan menu bukan
pengaman — pengamannya tetap middleware + `abort(403)` di controller.

## 3. Pengendali (controller) & tanggung jawabnya

| Berkas | Baris | Tugas |
|---|---|---|
| `ProjectController` | ~1051 | Project, task, revisi, ACC, rilis, override, complete |
| `MonitoringController` | ~1131 | Pantauan tim: workload, ketepatan waktu, direktori anggota, riwayat poin |
| `ContentController` | ~938 | Stok konten, bahan raw, activity, ambil/lepas, post/unpost, metadata broadcast |
| `WorkdeskController` | ~911 | 11 widget dashboard kerja + tren harian + performa divisi |
| `MeetingController` | ~374 | Rapat & notulen, action items, publish, export PDF/Word |
| `DivisionController` | ~253 | Halaman per divisi + laporan harian divisi |
| `TeamController` | ~204 | Kelola anggota tim |
| `ReportController` | ~203 | Cetak laporan (tampilan khusus cetak) |
| `DashboardController` | ~202 | Dashboard utama (KPI, grafik, peringkat) |
| `KetuaMediaPerformanceController` | ~184 | Halaman Kinerja Ketua Media + catatan evaluasi |
| `ProfileController` | ~152 | Profil, latar belakang, jabatan |
| `AuthController` | ~94 | Login/logout, bonus login harian, foto profil |
| `PreferenceController` | ~48 | Simpan pilihan tema (mode gelap/terang) |

## 4. Service (aturan bisnis)

| Berkas | Isi |
|---|---|
| `KetuaMediaPerformance` | Skor 0–100 empat pilar (Kesehatan Divisi 40%, Kecepatan Alur 20%, Keterlibatan 20%, Ketuntasan 20%), daftar "Menunggu Tindakan", papan divisi, tren 6 bulan. Ambang ada di konstanta `TARGET`, `PENALTI`, `TURUN_PER_HARI`. |
| `ProjectWorkflowService` | `syncOverdueFlags()`, `resolveOpenRevisions()`, `hasOpenRevisions()`, `isReadyToRelease()`, `releaseReadiness()`, `progress()`, `canUserChangeStatus()`, `getAvailableActions()` |
| `DailyLoginReward` | `claim()` (2 poin/hari), `streakSekarang()`, `sinkronStreak()`, `pointsPerUser()`, `pointsPerUserPerMonth()`, `history()` |
| `WhatsappNotifier` | 13 template pesan + `sendAndLog()` (anti-dobel) + `kirimSetelahResponse()` |

## 5. Model & relasi utama

```
User ──(project_user)── Project ──(project_tasks)── ProjectTask ──(project_task_contributors)── User
 │                        │                              │
 │                        ├──(project_revisions)── ProjectRevision
 │                        └──(contents)───────── Content
 ├──(login_rewards)── LoginReward
 ├──(ketua_media_evaluations)── KetuaMediaEvaluation
 └──(workdesk_alerts)── WorkdeskAlert
```

| Model | Catatan penting |
|---|---|
| `Project` | 9 konstanta `STATUS_*`, `allowedTransitions()`, `canTransitionTo()`, aksesori `progress`, `isActuallyOverdue`, `statusBadge`, `isFullyApproved` |
| `ProjectTask` | `task_date` & `completed_at` di-cast (lihat DATABASE.md), relasi `contributors` |
| `User` | `isPembina()`, `isManager()`, `isKepalaDivisi()`, `isInDivision()`, `trackDailyLogin()` |
| `Content` | 3 jenis upload: `raw`, `content_stock`, `activity` |
| `NotificationLog` | `alreadySent($type,$projectId,$userId,$menitTerakhir)` + `pesanSamaSudahTerkirim()` |

## 6. Alur kerja project — 9 tahap

| # | Status | Siapa yang bertindak | Aksi yang tersedia |
|---|---|---|---|
| 1 | `Planning` | Ketua Media | Susun task, lalu **Ajukan Task Review** |
| 2 | `Task Review` | Pembina | **Beri bobot setiap task** (scoring) |
| 3 | `Scoring` | Pembina | **Rilis project** (mulai dikerjakan) |
| 4 | `On Progress` | Anggota | Checklist task masing-masing |
| 5 | `Review` | Ketua Media **dan** Pembina | **Approve** (dua-duanya) · ⚡ Pembina juga bisa **Setujui & Lanjut Sekarang** tanpa menunggu Ketua Media |
| 6 | `Revision` | Anggota + Ketua Media | Perbaiki, lalu ajukan review lagi (kembali ke `Review`) |
| 7 | `Approved` | Ketua Media | Dorong divisi mengunggah konten |
| 8 | `Upload` | Anggota | Unggah konten, lalu **Tandai Selesai** |
| 9 | `Completed` | — | Selesai. Bisa **dibuka lagi** (reopen) dengan alasan |

Transisi yang diizinkan (`Project::allowedTransitions()`):

```
Planning → Task Review → Scoring → On Progress → Review → Revision
                                          Review → Approved → Upload → Completed
                                   Revision → Review
```

Aturan tambahan:
- `markComplete` butuh **minimal 1 konten** pada project itu (raw/stock/activity).
- Reopen hanya ke `Review` atau `Upload`, wajib alasan minimal 10 karakter, dan
  menghapus ACC kalau kembali ke `Review`.
- `approved_by_pembina_at` dan `approved_by_ketua_at` terpisah; project lanjut ke
  `Upload` hanya kalau **keduanya** terisi — kecuali lewat jalur override (lihat FITUR.md).

## 7. Sistem poin (dipakai Dashboard, Workdesk, Monitoring)

| Sumber poin | Aturan |
|---|---|
| Task selesai | `bobot task ÷ jumlah kontributor`, dihitung pada tanggal `completed_at`, hanya status `Completed` |
| Bonus login | 2 poin pada login pertama setiap hari (`login_rewards`) |

Aturan siapa yang dianggap mengerjakan sebuah task (**dipakai berulang di banyak
tempat, jadi harus konsisten**):

> Pakai **kontributor** (`project_task_contributors`). Kalau task belum punya
> kontributor, pakai **PIC** (`assigned_to`).

## 8. Prinsip yang dipegang di aplikasi ini

1. **Satu sumber angka.** Kalau satu angka muncul di dua tempat (mis. "menunggu
   tindakan" di kartu dan di tabel), keduanya harus memanggil fungsi yang sama.
2. **Jujur soal data kosong.** Tidak ada data → tampilkan `—`, bukan `0%`.
   Perkiraan diberi tanda (`≥N hari*`).
3. **Jangan mengubah data lama.** Migrasi selalu aditif (kolom baru boleh NULL).
   Data anomali dilewati, tidak dipaksa jadi angka.
4. **Aturan bisnis di Service**, bukan tersebar di view.
5. **Setiap perbaikan bug ditulis tesnya lebih dulu** (tes harus GAGAL dulu).

## 9. Struktur tampilan

| Berkas | Isi |
|---|---|
| `resources/views/layouts/app.blade.php` | Sidebar (menu per peran), topbar (profil + **switch mode gelap**), animasi bonus login, modal profil |
| `resources/views/partials/tema.blade.php` | Skrip anti-kedip + logika switch tema |
| `public/css/tema-gelap.css` | Seluruh aturan mode gelap (semua dikunci `html[data-theme="dark"]`) |
| `resources/views/<fitur>/*.blade.php` | Halaman per fitur (34 berkas blade) |
| `resources/views/reports/print.blade.php` | Halaman cetak berdiri sendiri (selalu terang) |
