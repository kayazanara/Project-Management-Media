# 📄 PRD FINAL v2.1 — WORKFLOW + WORKDESK + DIVISION ROLE

**Status:** ✅ Final — Ready to Copy & Execute
**Tanggal:** 26 September 2026
**Versi:** 2.1 (dengan Division Role)

---

## 📋 PART 0 — RINGKASAN EKSEKUSIF

**Apa yang dibangun:**
1. **Workflow Project 9 tahap** — dari Planning sampai Completed, dengan Review-Revisi loop
2. **Workdesk** — meja kerja Pembina & Ketua Media dengan 11 widget + Performa Divisi (doughnut + bar)
3. **Rapat & Notulen** — menu baru untuk catat hasil rapat + action items
4. **Division Role** — pemisahan Kepala Divisi & Anggota untuk hak akses granular
5. **Upload 3 Jenis** — raw, content_stock, activity
6. **Tema Global** — gradient biru-ungu di semua halaman

**Estimasi:** ~25 hari kerja (5 minggu)

---

## 🎯 PART 1 — STRUKTUR USER (DIVISION ROLE)

### 1.1 Struktur Field `users`

| Field | Tipe | Nilai | Keterangan |
|---|---|---|---|
| `role` | enum | `Ustadz`, `Santri` | Jenis user |
| `division` | string | `Pembina Media`, `Ketua Media`, `Lapangan`, `Editing`, `Broadcast`, `AI Research` | Divisi/posisi |
| 🆕 `division_role` | string | `pembina`, `ketua_media`, `kepala_divisi`, `anggota` | **Peran struktural** |

### 1.2 Contoh Data

| Nama | `role` | `division` | `division_role` |
|---|---|---|---|
| Zevana | Ustadz | Pembina Media | `pembina` |
| Fachri | Ustadz | Ketua Media | `ketua_media` |
| Nufail | Santri | Lapangan | `kepala_divisi` |
| Anas | Santri | Lapangan | `anggota` |
| Davin | Santri | Editing | `kepala_divisi` |
| Malik | Santri | Editing | `anggota` |

### 1.3 Aturan
- ✅ **1 user = 1 divisi** (tidak multi-divisi)
- ✅ **1 kepala divisi per divisi** (strict)
- ✅ **Pembina & Ketua Media** tidak masuk divisi operasional
- ✅ **Backfill otomatis** berdasarkan `division` existing

### 1.4 Middleware Update

`CheckDivisionAccess` handle parameter:
- `divisi.access:pembina` → `division_role = pembina`
- `divisi.access:ketua_media` → `division_role = ketua_media`
- `divisi.access:manager` → `pembina` OR `ketua_media`
- `divisi.access:kepala_divisi` → `division_role = kepala_divisi`
- `divisi.access:anggota` → `division_role = anggota`
- `divisi.access:broadcast` → `division = Broadcast` (existing)

---

## 🎯 PART 2 — WORKFLOW PROJECT (9 STATUS)

### 2.1 Diagram Transisi

```
[Planning]
    ↓ (Ketua Media susun task)
[Task Review]
    ↓ (Pembina mulai scoring)
[Scoring]
    ↓ (Pembina klik "Rilis Project")
[On Progress] ←─────────────────────┐
    ↓ (Kepala Divisi ajukan review)  │
[Review]                              │
    ├─ [Revisi] → [Revision] ────────┘
    │              (revisi selesai)
    │
    └─ [ACC] → [Approved]
                  ↓
              [Upload]
                  ↓
              [Completed]
```

**Overdue** = flag overlay (`is_overdue` boolean), bukan status.

### 2.2 Detail Per Tahap

| # | Status | Siapa | Aksi | Status Berikutnya | Notif WA ke |
|---|---|---|---|---|---|
| 1 | **Planning** | Pembina | Buat project | Task Review | Ketua Media |
| 2 | **Task Review** | Ketua Media | Susun task | Scoring | Pembina |
| 3 | **Scoring** | Pembina | Bobot 1-10 + ACC task | On Progress (via Rilis) | Semua anggota |
| 4 | **On Progress** | Tim | Kerja + upload raw | Review | Editing (saat upload raw) |
| 5 | **Review** | Kepala Divisi | Ajukan review | Revision / Approved | Pembina + Ketua Media |
| 6 | **Revision** | Pembina/Ketua Media | Tulis catatan revisi | Review | Kepala Divisi + Anggota |
| 7 | **Approved** | Pembina + Ketua Media | Keduanya ACC | Upload | Kepala Divisi |
| 8 | **Upload** | Berbagai role | Upload 3 jenis | Completed | Broadcast (stock) |
| 9 | **Completed** | Sistem | Auto | — | Kepala Divisi + Pembina |

### 2.3 Matriks Hak Akses (9 Aksi)

| Aksi | Pembina | Ketua Media | Kepala Divisi | Anggota | Broadcast |
|---|:-:|:-:|:-:|:-:|:-:|
| Buat project | ✅ | ❌ | ❌ | ❌ | ❌ |
| Susun task | ❌ | ✅ | ❌ | ❌ | ❌ |
| Scoring + Rilis | ✅ | ❌ | ❌ | ❌ | ❌ |
| Upload raw | ❌ | ❌ | ❌ | ✅ Lapangan | ❌ |
| Ajukan review | ❌ | ❌ | ✅ | ❌ | ❌ |
| Buat Daily Report | ❌ | ❌ | ✅ | ❌ | ❌ |
| Catatan revisi | ✅ | ✅ | ❌ | ❌ | ❌ |
| Approve/ACC | ✅ | ✅ | ❌ | ❌ | ❌ |
| Upload content stock | ❌ | ❌ | ❌ | ✅ tertentu | ❌ |
| Metadata konten | ❌ | ❌ | ❌ | ❌ | ✅ |
| Upload activity | ❌ | ❌ | ✅ | ❌ | ❌ |

### 2.4 Aturan Overdue

**Definisi:** `deadline < today` DAN `status != Completed`

**Perilaku:**
- ✅ Flag `is_overdue = true` di database
- ✅ Badge merah muncul di semua tampilan
- ✅ Status **tetap bisa diubah** (tidak terkunci)
- ✅ **Pembina/Ketua Media**: bebas ubah ke status apapun
- ✅ **Kepala Divisi**: terbatas (hanya transisi normal: On Progress → Review)
- ✅ **Anggota**: hanya lihat, tidak bisa ubah status

### 2.5 Aturan Transisi Status

```php
// State machine
$allowedTransitions = [
    'Planning'    => ['Task Review'],       // via Ketua Media
    'Task Review' => ['Scoring'],           // via Pembina
    'Scoring'     => ['On Progress'],       // via Rilis (Pembina)
    'On Progress' => ['Review'],            // via Kepala Divisi
    'Review'      => ['Revision', 'Approved'],
    'Revision'    => ['Review'],            // ajukan review ulang
    'Approved'    => ['Upload'],            // auto atau manual
    'Upload'      => ['Completed'],         // auto setelah metadata
    'Completed'   => [],                    // final, tidak bisa diubah
];
```

---

## 🎯 PART 3 — WORKDESK

### 3.1 Akses
**HANYA** Pembina Media + Ketua Media.

### 3.2 Layout 3 Kolom

```
┌────────────────────────────────────────────────────────┐
│ TOPBAR — gradient biru-ungu + greeting dinamis         │
├────────┬──────────────────────┬───────────────────────┤
│        │ 🎯 Aksi Saya          │ 🔔 Alert (4)          │
│ SIDE   │ 📊 Overview Cepat     │ 📅 Agenda Rapat        │
│        │ 📈 Performa Divisi    │ 📝 Notulen Terakhir    │
│ WORK   │ 👥 Top 5 Anggota      │                       │
│ DASH   │ 📁 Project Health     │                       │
└────────┴──────────────────────┴───────────────────────┘
```

### 3.3 Widget Detail (11 Widget)

#### Widget 1: 🎯 Aksi Saya (Beda per Role)

**Ketua Media:**
- Project butuh susunan task
- Task menunggu review Anda
- Revisi belum dibaca

**Pembina Media:**
- Project butuh scoring
- Task menunggu ACC
- Project siap dirilis
- Project overdue

#### Widget 2: 🔔 Alert & Notifikasi

Kategori:
- 🚨 Project overdue
- ⚠️ Anggota tidak aktif > 3 hari
- 📉 Divisi performa turun > 20%
- ⚠️ Task tanpa PIC

#### Widget 3: 📊 Overview Cepat

```
Project: 12 (5 ✅ 7 🚀)
Task: 47/68 (69%)
Konten Ready: 8
Anggota Aktif: 10/12
```

#### Widget 4: 📈 **Performa Divisi (Doughnut + Bar)**

**Metrics:**
```php
completion_rate = task_selesai / total_task × 100
on_time_rate = task_on_time / task_selesai × 100
weighted_score = Σ(task_done × weight) / Σ(task × weight) × 100
performance_score = (completion × 0.4) + (on_time × 0.4) + (weighted × 0.2)
```

**Visual:**
- 🍩 **Doughnut Chart.js** — 4 segmen, center text rata-rata
- 📊 **Bar Chart.js** — grouped: project/task/on-time/konten
- 📈 **Trend Indicator** — ↗️ +12% / ↘️ -8% / → 0%
- 🎨 **Color:** hijau ≥ 85%, kuning 70-84%, merah < 70%

#### Widget 5: 👥 Top 5 & Bottom Performers

**Top 5:**
```
🥇 Nufail Anas      14 task │ ⭐ 96%
🥈 Fachri Arizqi    12 task │ ⭐ 92%
🥉 Malik Abdurrazaq  8 task │ ⭐ 88%
```

**Bottom (perlu perhatian):**
```
⚠️ Ahmad Zaki   0 task │ ⭐ 40% │ tidak login 5 hari
```

#### Widget 6: 📁 Project Health

**3 Kategori:**
- 🚀 **On Track** — progress ≥ 50% & deadline jauh
- ⚠️ **At Risk** — progress < 50% & deadline < 3 hari
- 🚨 **Overdue** — deadline lewat

#### Widget 7: 📅 Agenda Rapat (7 hari ke depan)

#### Widget 8: 📝 Notulen Terakhir

- Ringkasan + 3 keputusan + 5 action items
- Tombol: Lihat Lengkap, Export PDF

#### Widget 9: ⚙️ Kesehatan Sistem (Pembina Only)

- Server status, DB backup, WAHA, n8n, Queue, Storage, Memory

#### Widget 10: 👥 Manajemen User (Pembina Only)

- User baru menunggu approve
- User inactive > 30 hari

#### Widget 11: 📢 Broadcast Pengumuman (Pembina Only)

### 3.4 Real-time Update
**Polling AJAX tiap 30 detik** → endpoint `/workdesk/actions`, `/workdesk/alerts`, `/workdesk/performance`.

### 3.5 Routes Workdesk

```php
Route::middleware(['divisi.access:manager'])->prefix('workdesk')->group(function () {
    Route::get('/', [WorkdeskController::class, 'index'])->name('workdesk');
    Route::get('/actions', [WorkdeskController::class, 'getActions'])->name('workdesk.actions');
    Route::get('/alerts', [WorkdeskController::class, 'getAlerts'])->name('workdesk.alerts');
    Route::get('/performance', [WorkdeskController::class, 'getPerformance'])->name('workdesk.performance');
    Route::post('/alerts/{id}/read', [WorkdeskController::class, 'markRead']);
});
```

---

## 🎯 PART 4 — RAPAT & NOTULEN

### 4.1 Akses
- **Create/Edit/Delete:** Pembina + Ketua Media
- **Read:** Semua user

### 4.2 Struktur Data `meetings`

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `title` | varchar | Judul rapat |
| `meeting_date` | date | Tanggal |
| `start_time` | time | Jam mulai |
| `end_time` | time | Jam selesai |
| `location` | varchar | Ruang Media / Zoom |
| `attendees` | json | List user_id |
| `agenda` | text | Agenda |
| `discussion` | longtext | Pembahasan (rich text) |
| `decisions` | json | Array keputusan |
| `action_items` | json | Array task |
| `notes` | text | Catatan |
| `created_by` | bigint | FK users |
| `status` | varchar | `draft`, `published` |
| `published_at` | timestamp | |

**Format `action_items`:**
```json
[
    {
        "task": "Koordinasi rekrut anggota",
        "assigned_to": 4,
        "deadline": "2026-10-01",
        "status": "pending"
    }
]
```

**Format `decisions`:**
```json
[
    {"decision": "Approve PSB 2027 → Upload"},
    {"decision": "Rekrut 2 anggota Editing"}
]
```

### 4.3 Frekuensi
**Custom** — bisa buat kapan saja (preset: Pekanan Sabtu 08:00).

### 4.4 Fitur

- ✅ CRUD lengkap
- ✅ Rich text editor untuk pembahasan
- ✅ Action items dengan checkbox
- ✅ Export PDF & Word
- ✅ Search & filter
- ✅ Notif WA saat publish

### 4.5 Notifikasi WA

**Trigger 1:** Notulen dipublish → Ketua Media + Pembina + Kepala Divisi
**Trigger 2:** Action item assigned → PIC

### 4.6 Routes

```php
Route::middleware(['divisi.access:manager'])->group(function () {
    Route::resource('meetings', MeetingController::class);
    Route::patch('/meetings/{id}/publish', [MeetingController::class, 'publish']);
    Route::patch('/meetings/action-items/{itemId}/toggle', [MeetingController::class, 'toggleActionItem']);
    Route::get('/meetings/{id}/export-pdf', [MeetingController::class, 'exportPdf']);
});
```

---

## 🎯 PART 5 — UPLOAD 3 JENIS

### 5.1 Jenis Upload

| Jenis | Deskripsi | Siapa | Flow |
|---|---|---|---|
| **Raw** | Bahan mentah (Lapangan → Editing) | Anggota Lapangan | Upload → notif Editing |
| **Content Stock** | Konten siap posting | Anggota tertentu | Upload → notif Broadcast → metadata → Complete |
| **Activity** | Kegiatan (live, event) | Kepala Divisi | Upload → Complete |

### 5.2 Fitur Raw — Sewaktu-waktu

Raw **bisa diupload kapan saja**, tidak nunggu tahap Upload.

```
Tim Lapangan shooting
    ↓
Upload file raw
    ↓
Pilih project + target divisi (default: Editing)
    ↓
Notif WA ke Divisi Editing
```

### 5.3 Form Broadcast Metadata

Isi: kategori, content_type, platform, caption → setelah itu status auto `Completed`.

### 5.4 Update `contents` Table

| Kolom Baru | Tipe | Keterangan |
|---|---|---|
| `upload_type` | varchar(20) | `raw`, `content_stock`, `activity` |
| `target_division` | varchar | Untuk raw |
| `category` | varchar | Diisi Broadcast |
| `content_type` | varchar | Diisi Broadcast |
| `broadcast_filled_at` | timestamp | |

---

## 🎯 PART 6 — NOTIFIKASI WA (9 EVENT)

| # | Event | Penerima | Trigger |
|---|---|---|---|
| 1 | Project dibuat | Ketua Media | `ProjectController@store` |
| 2 | Task disusun | Pembina | `ProjectController@submitTaskReview` |
| 3 | Project dirilis | Semua anggota + Kepala Divisi | `ProjectController@release` |
| 4 | Ajukan review | Pembina + Ketua Media | `ProjectController@requestReview` |
| 5 | Revisi | Kepala Divisi + Anggota | `ProjectController@requestRevision` |
| 6 | ACC | Kepala Divisi | `ProjectController@approve` |
| 7 | Upload raw | Divisi Editing | `ContentController@uploadRaw` |
| 8 | Stok konten baru | Divisi Broadcast | `ContentController@uploadStock` |
| 9 | Project complete | Kepala Divisi + Pembina | `ContentController@complete` |

**Update:** `WhatsappNotifier.php` — tambah 9 method template.

---

## 🎯 PART 7 — DATABASE SCHEMA (7 MIGRATION)

### Migration #1: `add_division_role_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('division_role', 30)->default('anggota')->after('division');
});

// Backfill
DB::table('users')->where('division', 'like', '%Pembina%')->update(['division_role' => 'pembina']);
DB::table('users')->where('division', 'like', '%Ketua Media%')->update(['division_role' => 'ketua_media']);
```

### Migration #2: `add_workflow_fields_to_projects_table.php`

```php
Schema::table('projects', function (Blueprint $table) {
    $table->timestamp('scored_at')->nullable()->after('completed_at');
    $table->timestamp('released_at')->nullable()->after('scored_at');
    $table->timestamp('approved_by_pembina_at')->nullable()->after('released_at');
    $table->timestamp('approved_by_ketua_at')->nullable()->after('approved_by_pembina_at');
    $table->boolean('is_overdue')->default(false)->after('approved_by_ketua_at');
});
```

### Migration #3: `add_scoring_to_project_tasks_table.php`

```php
Schema::table('project_tasks', function (Blueprint $table) {
    $table->integer('weight')->default(0)->after('title');
    $table->boolean('is_approved')->default(false)->after('weight');
    $table->timestamp('scored_at')->nullable()->after('is_approved');
});
```

### Migration #4: `create_project_revisions_table.php`

```php
Schema::create('project_revisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('note');
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});
```

### Migration #5: `add_upload_fields_to_contents_table.php`

```php
Schema::table('contents', function (Blueprint $table) {
    $table->string('upload_type', 20)->default('content_stock')->after('project_id');
    $table->string('target_division')->nullable()->after('upload_type');
    $table->string('category')->nullable()->after('type');
    $table->string('content_type')->nullable()->after('category');
    $table->timestamp('broadcast_filled_at')->nullable()->after('uploaded_at');
});
```

### Migration #6: `create_meetings_table.php`

```php
Schema::create('meetings', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->date('meeting_date');
    $table->time('start_time');
    $table->time('end_time');
    $table->string('location')->nullable();
    $table->json('attendees')->nullable();
    $table->text('agenda')->nullable();
    $table->longText('discussion')->nullable();
    $table->json('decisions')->nullable();
    $table->json('action_items')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->string('status', 20)->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

### Migration #7: `create_workdesk_alerts_table.php`

```php
Schema::create('workdesk_alerts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('type', 50);
    $table->string('title');
    $table->text('message');
    $table->string('priority', 20)->default('medium');
    $table->boolean('is_read')->default(false);
    $table->string('action_url')->nullable();
    $table->bigInteger('related_id')->nullable();
    $table->string('related_type')->nullable();
    $table->timestamps();
});
```

---

## 🎯 PART 8 — FILE YANG DIBUAT/DIUBAH (30 FILE)

### Migration (7):
1. `add_division_role_to_users_table.php`
2. `add_workflow_fields_to_projects_table.php`
3. `add_scoring_to_project_tasks_table.php`
4. `create_project_revisions_table.php`
5. `add_upload_fields_to_contents_table.php`
6. `create_meetings_table.php`
7. `create_workdesk_alerts_table.php`

### Controller Baru (2):
8. `WorkdeskController.php`
9. `MeetingController.php`

### Service Baru (1):
10. `ProjectWorkflowService.php`

### Model Baru (2):
11. `Meeting.php`
12. `ProjectRevision.php`

### Model Update (4):
13. `Project.php` — `canTransitionTo()` helper
14. `ProjectTask.php` — kolom weight
15. `Content.php` — upload_type
16. `User.php` — `division_role` di fillable

### Controller Update (4):
17. `ProjectController.php` — 7 method workflow
18. `ContentController.php` — 3 jenis upload
19. `TeamController.php` — form division_role
20. `AuthController.php` — tidak berubah

### Middleware Update (1):
21. `CheckDivisionAccess.php`

### View Baru (5):
22. `workdesk/index.blade.php`
23. `meetings/index.blade.php`
24. `meetings/show.blade.php`
25. `meetings/form.blade.php`
26. `contents/upload-raw.blade.php`, `upload-stock.blade.php`, `broadcast-edit.blade.php`

### View Update (5):
27. `layouts/app.blade.php` — gradient + menu baru
28. `projects/show.blade.php` — UI per tahap
29. `dashboard/index.blade.php` — gradient
30. `contents/index.blade.php` — filter type
31. `team/create.blade.php` + `edit.blade.php` — division_role

### Routes Update (1):
32. `routes/web.php`

### Notifier Update (1):
33. `WhatsappNotifier.php` — 9 template baru

---

## 🎯 PART 9 — ROADMAP EKSEKUSI (8 SPRINT)

| Sprint | Fokus | Hari | Deliverable |
|---|---|---|---|
| **1** | Database & Model | 1-3 | 7 migration + 4 model + service |
| **2** | Division Role & Middleware | 4-5 | Update middleware + form Team |
| **3** | Workflow Project | 6-9 | 7 method ProjectController + view |
| **4** | Review & Revisi Loop | 10-12 | Form + riwayat revisi |
| **5** | Workdesk | 13-16 | Controller + 11 widget + chart |
| **6** | Rapat & Notulen | 17-19 | CRUD + export |
| **7** | Upload 3 Jenis | 20-22 | 3 form + broadcast metadata |
| **8** | Notif WA + Polish | 23-25 | 9 template + gradient global + testing |

---

## 🎯 PART 10 — DEFINITION OF DONE

### Fungsional
- [ ] 7 migration jalan tanpa error
- [ ] `division_role` berfungsi di middleware
- [ ] Workflow 9 tahap end-to-end
- [ ] Review-Revisi loop bisa berkali-kali
- [ ] Workdesk akses via Pembina + Ketua Media only
- [ ] Performa divisi tampil doughnut + bar chart
- [ ] Rapat & Notulen CRUD + export PDF
- [ ] Upload 3 jenis dengan flow berbeda
- [ ] 9 event notifikasi WA terkirim
- [ ] Overdue flag muncul, status tetap editable

### UI/UX
- [ ] Gradient biru-ungu di semua halaman
- [ ] Workdesk 3 kolom responsive
- [ ] Modal/notif konsisten
- [ ] Chart.js warna palette biru-ungu
- [ ] Mobile-friendly semua halaman

### Teknis
- [ ] Real-time Workdesk (30 detik)
- [ ] Semua role sesuai matriks
- [ ] Testing semua role lulus
- [ ] Tidak ada error di log
- [ ] Dokumentasi updated

---

## 🎯 PART 11 — CATATAN PENTING

### 11.1 Backup Sebelum Migrate
**WAJIB** backup DB di VPS sebelum migrate:
```bash
cp /home/maskhoir/project-media/database/database.sqlite \
   /home/maskhoir/backup-db/db-pre-workflow-$(date +%Y%m%d).sqlite
```

### 11.2 Push Strategy
**Per sprint** — commit + push setiap sprint selesai, biar bisa rollback kalau ada masalah.

### 11.3 Testing Setiap Sprint
Setiap sprint selesai, test di laptop dulu, baru push + deploy ke VPS.

### 11.4 Backward Compatibility
Query lama `where division like '%Lapangan%'` **tetap jalan** karena `division` tidak diubah. `division_role` adalah kolom tambahan.

### 11.5 Theme Global
Semua halaman diupdate ke gradient biru-ungu:
```css
background: linear-gradient(135deg, #4e73df 0%, #5b5dd8 35%, #6f5bcf 65%, #8b5cf6 100%);
```

---

## 🎯 PART 12 — FITUR: KINERJA KETUA MEDIA (29 Sep 2026)

**Menu:** `Kinerja Ketua Media` (sidebar, **khusus Pembina Media**) · **Route:** `/kinerja-ketua-media`

### 12.1 Tujuan
Membantu Pembina memantau & mengukur apakah **Ketua Media** benar-benar menjalankan
tugasnya: **memastikan semua tugas & project tiap divisi berjalan lancar**.
Karena itu penilaiannya bukan "berapa task yang dia kerjakan sendiri", tapi 4 pilar.

### 12.2 Empat Pilar & Bobot

| Pilar | Bobot | Isi |
|---|---|---|
| 🏥 Kesehatan Divisi | 40% | per divisi: rasio task terlambat, project lewat deadline, ketepatan waktu |
| ⚡ Kecepatan Alur | 20% | lama menyusun task (Planning → diajukan), lama tunggu ACC, jumlah yang menunggu tindakannya |
| 🤝 Keterlibatan | 20% | rapat/notulen yang dia buat, catatan revisi yang dia tulis, alert Workdesk yang dibuka, hari aktif login |
| 🎯 Ketuntasan | 20% | project selesai tepat deadline, rata-rata progress project aktif, panjang siklus project |

### 12.3 Aturan agar angkanya jujur
- **Semua pilar dihitung per periode** (7/30/90 hari) — tren bulan lampau tidak berubah tiap halaman dibuka.
- **Pilar tanpa data dikeluarkan** dari penilaian; bobotnya dibagikan proporsional ke pilar lain
  (tidak dianggap 0 karena dihukum, tidak dianggap 100 karena "kembung").
- **Periode tanpa jejak data = skor kosong (`—`)**, bukan 0.
- **Data anomali dilewati** (mis. `completed_at` lebih awal dari `created_at` pada data demo).
- Ambang & penalti terpusat di konstanta `KetuaMediaPerformance::TARGET`, `::PENALTI`, `::TURUN_PER_HARI` — gampang dikalibrasi.

### 12.4 Sumber data (tanpa tabel baru untuk penilaian)
- Kolom baru di `projects` (aditif, NULL untuk data lama): `task_submitted_at`, `task_submitted_by`, `review_requested_at`
  — dipakai mengukur kecepatan alur secara **jujur** (bukan menebak dari `updated_at`).
- Tabel baru `ketua_media_evaluations` = **Catatan Evaluasi Pembina** per periode,
  menyimpan **snapshot skor** saat catatan dibuat.
- Divisi **tidak di-hardcode**: ditarik dari user (`kepala_divisi`/`anggota`), jadi divisi baru otomatis ikut.
- Pemetaan project → divisi memakai anggota project (`project_user`), jadi 1 project bisa masuk beberapa divisi.

### 12.5 Hak akses
Hanya Pembina Media — dijaga **dua lapis**: middleware `divisi.access:pembina` di route +
pemeriksaan ulang di controller (`abort(403)`). Ketua Media/Kepala Divisi/Anggota = 403.
Menu di sidebar hanya tampil untuk Pembina.

### 12.6 Uji
`tests/Feature/KinerjaKetuaMediaTest.php` — 27 tes (hak akses, 4 pilar, penurunan skor karena
task telat/project lewat deadline, pembagian bobot, tren kosong, penanda waktu workflow,
bottleneck, catatan evaluasi + regresi tanggal `Y-m-d`).


