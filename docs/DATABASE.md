# DATABASE — Project Media

Database: **SQLite** di `database/project_media.sqlite`.
Schema dibuat lewat migrasi di `database/migrations/`. **Semua migrasi harus aditif**
(kolom baru boleh NULL) supaya data lama tidak rusak.

Total **21 tabel** (13 tabel aplikasi + 8 tabel bawaan Laravel).

---

## 1. Tabel aplikasi

### `users` (23 kolom)
Akun pengguna (Pembina, Ketua Media, Kepala Divisi, Anggota).
`id`, `name`, `email`, `email_verified_at`, `password`, `role` (Ustadz/Santri),
`division` (mis. "Tim Lapangan"), **`division_role`** (pembina/ketua_media/kepala_divisi/anggota
— inilah yang dipakai hak akses), `status` (`Aktif` = dihitung di laporan; nilai lain = diabaikan),
`remember_token`, `created_at`, `updated_at`, `avatar`, `phone` (format 62… dipakai WA),
`bio`, `jabatan`, `background_image`, `theme_color`, **`theme_mode`** (light/dark),
`social_links`, `total_login_days`, `last_login_date`, **`login_streak`**.

> ⚠️ `last_login_date` **bukan** sumber kebenaran streak (pernah jadi biang bug:
> ditimpa jadi hari ini sebelum pemeriksaan "kemarin"). Streak dihitung dari `login_rewards`.

### `projects` (21 kolom)
`id`, `name`, `start_date`, `deadline`, `status` (9 tahap), `reference_link`, `brief`,
`created_at`, `updated_at`, `completed_at`, `scored_at`, `released_at`,
`approved_by_pembina_at`, `approved_by_ketua_at`, `is_overdue`,
`task_submitted_at`, `task_submitted_by`, `review_requested_at` (penanda waktu untuk
mengukur kecepatan alur), `approved_override_by`, `approved_override_at`,
`approved_override_note` (jejak "Setujui & Lanjut Sekarang").

> Tidak ada kolom `division`. Divisi sebuah project **diturunkan dari anggotanya**
> (`project_user`) — satu project bisa masuk beberapa divisi.

### `project_tasks` (13 kolom)
`id`, `project_id`, `assigned_to` (PIC), `title`, `description`, **`task_date`**,
`status` (`Pending`/`Completed`), `completed_at`, `created_at`, `updated_at`,
`weight` (bobot poin), `is_approved`, `scored_at`.

> ⚠️ `task_date` memakai cast `App\Casts\DateOnly`. Jangan membandingkannya dengan
> waktu penuh (`Carbon::now()`), selalu `->toDateString()`.

### `project_task_contributors` (5 kolom)
`id`, `project_task_id`, `user_id`, `created_at`, `updated_at`.
Dipakai untuk membagi poin bobot task.

### `project_user` (5 kolom)
`id`, `project_id`, `user_id`, `created_at`, `updated_at`. Anggota project.

### `project_revisions` (9 kolom)
`id`, `project_id`, `user_id`, `note`, `resolved_at`, `created_at`, `updated_at`,
`type` (`review_request`, `reopen`, `approve_override`, dll), `file_url`.
Catatan: baris dengan `resolved_at` terisi = sudah tuntas (tidak dihitung sebagai
"revisi menggantung").

### `contents` (21 kolom)
Konten/stok/aktivitas. `id`, `content_code`, `project_id`, `title`, `type`,
`platform`, `file_path`, `file_url`, `caption`, `status`, `uploaded_at`,
`created_at`, `updated_at`, **`upload_type`** (`raw`/`content_stock`/`activity`),
**`target_division`**, `category`, `content_type`, `broadcast_filled_at`,
`taken_at`, `taken_by`, `created_by`.

### `meetings` (17 kolom)
`id`, `title`, `meeting_date`, **`start_time`**, **`end_time`**, `location`,
`attendees` (JSON), `agenda`, `discussion`, `decisions` (JSON), `action_items` (JSON),
`notes`, `created_by`, `status` (`draft`/`published`), `published_at`,
`created_at`, `updated_at`.

> ⚠️ `start_time` dan `end_time` **NOT NULL** (pernah bikin tes gagal).

### `daily_reports` (13 kolom)
Laporan harian divisi: `id`, `user_id`, `division`, `project_id`, `report_date`,
`summary`, `completed_work`, `unfinished_work`, `problems`, `next_plan`, `notes`,
`created_at`, `updated_at`.

### `notification_logs` (11 kolom)
Riwayat pengiriman WhatsApp: `id`, `user_id`, `project_id`, `type` (mis. `reminder_h3`,
`overdue`, `project_approved`), `phone`, `message`, `status` (`sent`/`failed`),
`response`, `sent_at`, `created_at`, `updated_at`.

> Dipakai untuk **anti-dobel**: `NotificationLog::alreadySent()` dan
> `pesanSamaSudahTerkirim()`. Ada indeks pada `type+created_at` dan `status`.

### `login_rewards` (7 kolom)
Bonus login harian: `id`, `user_id`, **`reward_date`**, `points`, `streak`,
`created_at`, `updated_at`. Ada **UNIQUE(user_id, reward_date)** → mustahil dobel sehari.
Kolom `streak` di sini adalah nilai **lama** (pernah salah karena bug 2 Okt 2026);
angka streak yang ditampilkan dihitung ulang dari riwayat.

### `ketua_media_evaluations` (10 kolom)
Catatan evaluasi Pembina untuk Ketua Media per periode: `id`, `user_id`,
`evaluator_id`, `period_start`, `period_end`, `score` (snapshot skor), `rating`,
`note`, `created_at`, `updated_at`.

### `workdesk_alerts` (12 kolom)
Peringatan di Workdesk: `id`, `user_id`, `type`, `title`, `message`, `priority`,
`is_read`, `action_url`, `related_id`, `related_type`, `created_at`, `updated_at`.

## 2. Tabel bawaan Laravel (jangan diubah)

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`,
`password_reset_tokens`, `sessions`.

> `sessions` dipakai untuk kolom "terakhir aktif" di Monitoring Tim, jadi
> `SESSION_DRIVER` harus `database`.

## 3. Relasi ringkas

```
users ─┬─ project_user ────────── projects
       ├─ project_task_contributors ─ project_tasks ─ projects
       ├─ project_revisions ────── projects
       ├─ contents ─────────────── projects
       ├─ login_rewards
       ├─ ketua_media_evaluations
       └─ workdesk_alerts
```

## 4. Kolom yang perlu perhatian khusus

| Kolom | Kenapa penting |
|---|---|
| `users.login_streak` | **Hasil hitung**, bukan penentu. Diperbarui `DailyLoginReward`. Ada perintah `login:hitung-ulang-streak` untuk membetulkan. |
| `users.last_login_date` | Hanya penanda "sudah login hari ini". **Jangan dipakai** sebagai dasar streak. |
| `users.theme_mode` | `light` (default) / `dark`. User lama otomatis terang. |
| `project_tasks.task_date` | Cast `DateOnly`. Bandingkan per tanggal, jangan per waktu. |
| `login_rewards.reward_date` | Cast `DateOnly` + UNIQUE dengan `user_id`. |
| `projects.review_requested_at` | Dasar mengukur "lama menunggu ACC". Kalau kosong (project lama) → dipakai perkiraan bertanda `≥N hari*`. |
| `projects.approved_override_*` | Jejak Pembina melanjutkan tanpa ACC Ketua Media. `approved_by_ketua_at` sengaja **dibiarkan kosong**. |
| `meetings.start_time` / `end_time` | NOT NULL — isi selalu. |
| `contents.upload_type` | Menentukan halaman mana yang menampilkan konten (raw/stock/activity). |

## 5. Cadangan (backup)

`php artisan db:backup` → `database/backups/project_media-YYYYMMDD-HHMMSS.sqlite`
(memakai `VACUUM INTO`, aman walau aplikasi sedang dipakai). Kebiasaan yang dipakai
di proyek ini: **backup dulu, buktikan isinya bisa dibuka, baru mengubah apa pun.**
