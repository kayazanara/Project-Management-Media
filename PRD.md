# PRODUCT REQUIREMENTS DOCUMENT (PRD)

# MEDIA DIVISION MANAGEMENT SYSTEM
## PP HUBBUL KHOIR SUKOHARJO

**Version:** 2.0  
**Status:** Development  
**Platform:** Web Application  
**Framework:** Laravel 13  
**Database:** SQLite  
**Frontend:** SB Admin 2 / Bootstrap 4  
**PHP:** 8.3+  
**Target User:** Tim Media PP Hubbul Khoir Sukoharjo

---

# 1. PRODUCT OVERVIEW

Media Division Management System adalah sistem informasi internal untuk mengelola, memonitor, dan mendokumentasikan aktivitas Divisi Media PP Hubbul Khoir Sukoharjo.

Sistem berfokus pada:

1. Project Management
2. Content Stock Management
3. Team Management
4. Division Monitoring
5. Daily Reporting
6. Automatic Reporting

Sistem dirancang agar aktivitas Media memiliki alur data yang terhubung:

PROJECT
↓
DIVISION
↓
DAILY TASK
↓
CHECKLIST
↓
PROJECT PROGRESS
↓
REVIEW
↓
REVISION
↓
UPLOAD
↓
CONTENT STOCK
↓
COMPLETE
↓
AUTOMATIC REPORT

Sistem tidak dibuat sebagai project management system generik, tetapi sebagai sistem monitoring operasional Divisi Media.

---

# 2. PROBLEM STATEMENT

Pengelolaan aktivitas Media membutuhkan pencatatan mengenai:

- Project yang sedang berjalan
- Anggota yang terlibat
- Pekerjaan yang harus dilakukan
- Progress project
- Aktivitas setiap divisi
- Laporan harian
- Proses review
- Catatan revisi
- Konten yang sudah siap upload
- Project yang sudah selesai

Tanpa sistem terpusat, data tersebut berpotensi tersebar dan sulit digunakan untuk monitoring.

Sistem ini dibuat untuk menjadikan seluruh aktivitas tersebut sebagai satu sumber data terpusat.

---

# 3. PRODUCT GOAL

## Primary Goal

Membangun satu sistem yang dapat digunakan Ketua Media dan Kepala Divisi untuk mengetahui kondisi operasional Media secara cepat dan berbasis data.

## Secondary Goals

Sistem harus mampu:

- Mengelola project Media.
- Membagi project kepada anggota/divisi.
- Membuat task berdasarkan project.
- Membuat checklist pekerjaan harian.
- Menghitung progress project secara otomatis.
- Mengelola proses review dan revisi.
- Menyimpan konten yang siap upload.
- Menampilkan aktivitas setiap divisi.
- Menyimpan laporan harian Kepala Divisi.
- Menghasilkan laporan otomatis berdasarkan data sistem.
- Menghasilkan laporan yang dapat dicetak.

---

# 4. PRODUCT PRINCIPLES

## 4.1 Single Source of Truth

Data project, task, laporan, progress, dan content stock berasal dari database yang sama.

## 4.2 Automatic Calculation

Statistik dan progress tidak boleh bergantung pada input manual jika datanya dapat dihitung dari database.

## 4.3 Minimal Menu

Sistem hanya memiliki beberapa menu utama.

Kompleksitas sistem berada di dalam halaman dan workflow, bukan dengan menambah banyak menu.

## 4.4 Traceable

Aktivitas penting harus dapat ditelusuri berdasarkan:

- Project
- User
- Divisi
- Task
- Tanggal
- Status

## 4.5 Division-Based Monitoring

Monitoring utama dilakukan berdasarkan divisi Media.

---

# 5. SYSTEM MENU

Sidebar utama:

DASHBOARD

MENU UTAMA
- Project Management
- Stock Management
- Team Management

MONITORING DIVISI
- Tim & Laporan
- Cetak Laporan

---

# 6. USER ROLE

Sistem memiliki beberapa role.

## 6.1 Ketua Media

Ketua Media memiliki akses monitoring dan pengelolaan keseluruhan sistem.

Hak akses:

- Dashboard
- Project Management
- Stock Management
- Team Management
- Semua Divisi
- Semua laporan
- Review project
- Memberikan catatan revisi
- Melihat progress
- Mencetak laporan

---

## 6.2 Kepala Divisi

Kepala Divisi bertanggung jawab terhadap operasional divisinya.

Hak akses:

- Melihat project divisi
- Membuat task project
- Membuat checklist pekerjaan
- Mengisi laporan harian
- Mengedit laporan harian
- Melihat laporan sebelumnya
- Melihat progress project
- Mengajukan project ke review
- Melihat status project

Kepala Divisi hanya dapat mengelola data sesuai divisinya, kecuali diberikan akses khusus.

---

## 6.3 Anggota Divisi

Anggota dapat:

- Melihat project yang ditugaskan
- Melihat task
- Melihat checklist
- Memperbarui status pekerjaan
- Menandai pekerjaan selesai

Anggota tidak membuat laporan harian divisi.

---

## 6.4 Pembina Media

Pembina Media memiliki akses monitoring dan review.

Hak akses:

- Melihat project
- Melihat progress
- Melihat laporan
- Memberikan catatan revisi
- Menyetujui hasil project
- Mengembalikan project ke tahap revisi

---

# 7. DIVISION

Divisi Media yang digunakan dalam sistem:

1. Tim Lapangan
2. Tim Editing
3. Tim Broadcast
4. Tim AI Research

Struktur divisi menggunakan data `division` yang sudah tersedia pada user.

---

# 8. DASHBOARD

Dashboard merupakan halaman utama sistem.

Dashboard tidak digunakan sebagai tempat input utama.

Dashboard mengambil data dari seluruh modul dan menyajikannya sebagai rangkuman.

---

# 9. DASHBOARD — KPI

Dashboard menampilkan:

## Project

- Total Project
- Project Aktif
- Project Planning
- Project Proses
- Project Under Review
- Project Revisi
- Project Upload
- Project Complete

## Task

- Task Hari Ini
- Task Selesai Hari Ini
- Task Belum Selesai
- Task Overdue

## Team

- Total Anggota
- Anggota Aktif
- Total Divisi

## Content

- Content Ready to Upload
- Content Uploaded
- Content Complete

---

# 10. DASHBOARD — TIME FILTER

Dashboard menyediakan filter:

- Hari Ini
- Minggu Ini
- Bulan Ini
- Tahun Ini
- Custom Date Range

Statistik mengikuti periode yang dipilih.

---

# 11. DASHBOARD — PROJECT BERJALAN

Dashboard menampilkan project aktif.

Informasi:

- Nama Project
- Divisi
- Progress
- Status
- Deadline

Contoh:

Project PSB 2027
Progress: 75%
Status: Proses
Deadline: 25 September 2026

---

# 12. DASHBOARD — TASK HARI INI

Dashboard menampilkan seluruh task yang memiliki tanggal hari ini.

Data:

- Task
- Project
- PIC
- Divisi
- Status

Status:

- Pending
- In Progress
- Completed

---

# 13. DASHBOARD — AKTIVITAS DIVISI

Dashboard menampilkan aktivitas setiap divisi.

Contoh:

Tim Lapangan
- 5 anggota
- 2 project aktif
- 7 task hari ini

Tim Editing
- 4 anggota
- 3 project aktif
- 8 task hari ini

Tim Broadcast
- 3 anggota
- 2 project aktif
- 4 task hari ini

Tim AI Research
- 3 anggota
- 1 project aktif
- 3 task hari ini

---

# 14. DASHBOARD — CONTENT STOCK

Dashboard menampilkan jumlah konten yang siap upload.

Contoh:

READY TO UPLOAD
12 CONTENT

UPLOADED
8 CONTENT

---

# 15. PROJECT MANAGEMENT

Project Management digunakan untuk mengelola project Media.

Fitur existing yang dipertahankan:

- Project List
- Create Project
- Edit Project
- Delete Project
- Assign Members
- Project Brief
- Reference Link
- Start Date
- Deadline

Project Management tidak digunakan untuk mengubah status project secara manual.

Status project mengikuti workflow monitoring.

---

# 16. PROJECT DATA

Setiap project memiliki:

- ID
- Name
- Brief
- Reference Link
- Start Date
- Deadline
- Status
- Created By
- Created At
- Updated At

Relasi:

Project
→ Project Members
→ Users

---

# 17. PROJECT WORKFLOW

Project memiliki workflow:

PLANNING
↓
PROSES
↓
UNDER REVIEW
↓
REVISI
↓
UPLOAD
↓
COMPLETE

Workflow dapat kembali dari:

REVISI
↓
UNDER REVIEW

Jika hasil revisi belum memenuhi kebutuhan, project dapat kembali ke REVISI.

---

# 18. PHASE — PLANNING

Planning merupakan tahap persiapan project.

Pada tahap ini Kepala Divisi membuat:

- Task
- Checklist
- Target pekerjaan
- PIC
- Jadwal pekerjaan

Contoh:

PROJECT: Konten PSB

TASK:

[ ] Membuat konsep
[ ] Membuat script
[ ] Shooting
[ ] Editing
[ ] Review
[ ] Export
[ ] Upload

---

# 19. PHASE — PROSES

Pada tahap Proses, anggota menjalankan task dan checklist.

Contoh:

[✓] Membuat konsep
[✓] Membuat script
[✓] Shooting
[ ] Editing
[ ] Review
[ ] Export
[ ] Upload

Progress project dihitung berdasarkan checklist/task yang selesai.

---

# 20. PROJECT PROGRESS

Progress project dihitung otomatis.

Formula:

Completed Task / Total Task × 100

Contoh:

Total Task = 10
Completed = 7

Progress = 70%

Progress tidak diinput secara manual.

---

# 21. PROJECT PHASE VS PROJECT PROGRESS

Phase dan progress merupakan dua hal berbeda.

Contoh:

PROSES — 70%

Artinya:

- Workflow project saat ini berada di tahap Proses.
- 70% task pada tahap tersebut/seluruh task telah selesai.

Phase tidak memaksa angka progress.

Contoh yang valid:

Planning — 0%
Proses — 75%
Under Review — 100%
Revisi — 40%
Upload — 100%
Complete — 100%

---

# 22. DAILY TASK

Setiap project dapat memiliki task harian.

Task dibuat oleh Kepala Divisi.

Field:

- Task
- Project
- PIC
- Division
- Task Date
- Status
- Description
- Completed At

Status:

- Pending
- In Progress
- Completed

---

# 23. CHECKLIST

Task dapat memiliki checklist pekerjaan.

Contoh:

PROJECT: Dokumentasi Kajian

CHECKLIST:

[✓] Persiapan kamera
[✓] Pengambilan footage
[✓] Backup footage
[ ] Seleksi footage
[ ] Editing
[ ] Export

Checklist menjadi salah satu sumber perhitungan progress.

---

# 24. PROJECT DAILY ACTIVITY

Setiap project memiliki riwayat aktivitas harian.

Contoh:

21 September 2026

- 3 task selesai
- 2 task dibuat
- Progress berubah dari 50% → 70%
- Kepala Divisi mengirim laporan
- Project masih dalam tahap Proses

20 September 2026

- 4 task selesai
- Progress berubah dari 30% → 50%

---

# 25. UNDER REVIEW

Project masuk Under Review ketika pekerjaan produksi telah selesai dan hasil siap diperiksa.

Reviewer:

- Ketua Media
- Pembina Media

Reviewer dapat:

- Melihat hasil project
- Melihat checklist
- Melihat progress
- Melihat laporan
- Memberikan catatan
- Menyetujui
- Mengembalikan ke Revisi

---

# 26. REVISION

Jika terdapat perubahan:

UNDER REVIEW
↓
REVISI

Reviewer memberikan catatan revisi.

Data:

- Reviewer
- Tanggal
- Catatan
- Status

Contoh:

CATATAN REVISI:

1. Perbaiki opening.
2. Tambahkan subtitle.
3. Perbaiki audio.
4. Kurangi durasi.

Status catatan:

- Open
- Resolved

---

# 27. REVISION WORKFLOW

REVISI
↓
Task Revisi
↓
Anggota mengerjakan
↓
Checklist selesai
↓
UNDER REVIEW
↓
Reviewer memeriksa kembali

Jika masih terdapat masalah:

UNDER REVIEW
↓
REVISI

Jika disetujui:

UNDER REVIEW
↓
UPLOAD

---

# 28. UPLOAD PHASE

Project yang telah disetujui masuk tahap Upload.

Pada tahap ini content dibuat pada Stock Management.

Flow:

Project Approved
↓
Upload
↓
Create Content Stock
↓
Content Ready
↓
Project Complete

---

# 29. STOCK MANAGEMENT

Stock Management digunakan untuk mengelola konten yang telah dibuat dan siap digunakan untuk publikasi.

Stock Management bukan inventaris peralatan.

Tidak termasuk:

- Kamera
- Tripod
- Microphone
- Lighting
- Peralatan fisik lainnya

Stock Management hanya mengelola content.

---

# 30. STOCK DEFAULT VIEW

Halaman Stock Management secara default menampilkan:

READY TO UPLOAD

Contoh:

| Content | Type | Project | Platform | Status |
|---|---|---|---|---|
| PSB 2027 | Reels | PSB 2027 | Instagram | Ready |
| Kajian Ahad | Poster | Kajian | Instagram | Ready |
| Profil Santri | Reels | Profil | TikTok | Ready |

---

# 31. CONTENT DATA

Setiap content memiliki:

- Content ID
- Title
- Project
- Content Type
- Platform
- File
- File URL
- Caption
- Status
- Created Date
- Uploaded Date
- Created By

---

# 32. CONTENT TYPE

Content Type dapat berupa:

- Reels
- Poster
- Feed
- Carousel
- Story
- Video
- Photo
- Announcement
- Other

---

# 33. CONTENT STATUS

Minimal:

READY TO UPLOAD
UPLOADED
ARCHIVED

Status produksi seperti:

- Planning
- Proses
- Editing
- Review
- Revisi

tidak disimpan sebagai status Stock.

Status produksi dikelola pada Project.

---

# 34. CONTENT → PROJECT RELATION

Setiap content harus memiliki project sumber.

Contoh:

Project:
PSB 2027

Content:
PSB 2027 — Pendaftaran

Dengan demikian sistem dapat mengetahui:

Content ini berasal dari project apa?

---

# 35. PROJECT COMPLETE RULE

Project dapat menjadi Complete setelah:

1. Project telah disetujui.
2. Content telah dibuat.
3. Content telah masuk Stock Management.

Flow:

Approved
↓
Upload
↓
Content Created
↓
Stock Ready
↓
Complete

---

# 36. TEAM MANAGEMENT

Team Management sudah tersedia pada sistem dan dipertahankan.

Implementasi existing:

`TeamController`

Fitur:

- Tambah anggota
- Lihat anggota
- Edit anggota
- Hapus anggota
- Validasi role
- Validasi division

Data user memiliki:

- Role
- Division

Team Management tidak perlu dibuat ulang.

---

# 37. TIM & LAPORAN

Tim & Laporan digunakan untuk monitoring masing-masing divisi.

Submenu:

- Tim Lapangan
- Tim Editing
- Tim Broadcast
- Tim AI Research

---

# 38. DIVISION DASHBOARD

Setiap halaman divisi menampilkan:

## Statistik

- Jumlah Anggota
- Project Aktif
- Task Hari Ini

## Project Aktif

- Project Name
- Progress
- Status
- Deadline

## Laporan Hari Ini

- Status laporan
- Ringkasan pekerjaan

## Laporan Sebelumnya

- Tanggal
- Kepala Divisi
- Ringkasan
- Project

---

# 39. DAILY REPORT

Daily Report hanya dibuat oleh Kepala Divisi.

Anggota tidak membuat Daily Report divisi.

Form:

- Tanggal
- Divisi
- Project
- Ringkasan pekerjaan
- Pekerjaan selesai
- Pekerjaan belum selesai
- Kendala
- Rencana berikutnya
- Catatan

---

# 40. DAILY REPORT MANAGEMENT

Kepala Divisi memiliki:

- Tambah Laporan
- Edit Laporan
- Lihat Laporan Sebelumnya

Laporan sebelumnya tidak boleh hilang ketika laporan baru dibuat.

---

# 41. DAILY REPORT — PROJECT

Daily Report dapat berkaitan dengan project tertentu.

Contoh:

PROJECT: PSB 2027

21 September:

- Membuat script
- Menyelesaikan shooting
- Editing dimulai
- 3 checklist selesai
- Progress 60%

20 September:

- Menyelesaikan konsep
- Membuat script
- Progress 30%

---

# 42. AUTOMATIC REPORTING

Laporan agregat tidak dibuat dengan mengetik ulang data.

Sistem otomatis mengambil data dari:

- Project
- Task
- Checklist
- Daily Report
- Content
- Team
- Division
- Revision

Kemudian menghasilkan laporan.

---

# 43. AUTOMATIC DAILY SUMMARY

Sistem dapat menghasilkan rangkuman otomatis:

LAPORAN MEDIA
21 SEPTEMBER 2026

PROJECT AKTIF
5

TASK SELESAI
17

TASK BELUM SELESAI
6

CONTENT READY
8

DIVISI:

Tim Lapangan
3 task selesai

Tim Editing
7 task selesai

Tim Broadcast
4 task selesai

Tim AI Research
3 task selesai

---

# 44. AUTOMATIC WEEKLY REPORT

Sistem menghasilkan laporan mingguan otomatis.

Data:

- Project Aktif
- Project Complete
- Project Revisi
- Total Task
- Task Selesai
- Task Belum Selesai
- Content Ready
- Content Uploaded
- Aktivitas Divisi
- Jumlah Daily Report

Contoh:

PERIODE:
15–21 September 2026

PROJECT
Aktif: 7
Complete: 3
Revisi: 2

TASK
Total: 84
Selesai: 67
Belum selesai: 17

CONTENT
Ready: 12
Uploaded: 8

---

# 45. AUTOMATIC MONTHLY REPORT

Sistem menghasilkan laporan bulanan otomatis.

Data:

- Total Project
- Project Aktif
- Project Complete
- Project Revisi
- Total Task
- Task Selesai
- Total Content
- Content Ready
- Content Uploaded
- Aktivitas Divisi
- Jumlah Laporan

---

# 46. AUTOMATIC YEARLY REPORT

Sistem menghasilkan laporan tahunan otomatis.

Data dikelompokkan berdasarkan bulan.

Contoh:

JANUARI
- Project
- Task
- Content

FEBRUARI
- Project
- Task
- Content

dan seterusnya.

---

# 47. CETAK LAPORAN

Menu Cetak Laporan digunakan untuk menghasilkan laporan dari data database.

User tidak perlu memasukkan ulang data.

Jenis laporan:

- Harian
- Mingguan
- Bulanan
- Tahunan
- Project
- Divisi

---

# 48. REPORT FILTER

User dapat memilih:

- Jenis Laporan
- Periode
- Divisi
- Project

Contoh:

Jenis:
Laporan Mingguan

Periode:
15–21 September 2026

Divisi:
Tim Editing

Output:
Laporan otomatis berdasarkan database.

---

# 49. REPORT OUTPUT

Output utama:

1. PDF
2. Browser Print

Excel dapat ditambahkan pada versi berikutnya.

---

# 50. AUTOMATIC PROJECT STATUS

Project status tidak boleh diubah manual dari Project Management.

Status berasal dari workflow.

## Planning

Project baru dibuat dan task belum mulai dikerjakan.

## Proses

Task produksi sedang berjalan.

## Under Review

Produksi selesai dan hasil menunggu pemeriksaan.

## Revisi

Reviewer meminta perubahan.

## Upload

Project telah disetujui dan content sedang dimasukkan ke Stock.

## Complete

Content telah berhasil masuk Stock.

---

# 51. BUSINESS RULE — PROJECT

1. Project baru memiliki status Planning.
2. Status project tidak dapat diubah bebas dari form Project.
3. Status mengikuti workflow.
4. Progress dihitung otomatis.
5. Project Under Review dapat masuk Revisi.
6. Project Revisi harus memiliki catatan revisi.
7. Project Revisi dapat kembali Under Review.
8. Project yang disetujui dapat masuk Upload.
9. Project tidak dapat Complete sebelum content dibuat.
10. Project Complete harus memiliki content terkait.

---

# 52. BUSINESS RULE — TASK

1. Task harus memiliki project.
2. Task dapat memiliki PIC.
3. Task memiliki tanggal.
4. Task memiliki status.
5. Task selesai memengaruhi progress project.
6. Task yang selesai memiliki `completed_at`.
7. Task yang belum selesai tidak dihitung sebagai completed.

---

# 53. BUSINESS RULE — DAILY REPORT

1. Daily Report hanya dapat dibuat oleh Kepala Divisi.
2. Daily Report memiliki tanggal.
3. Daily Report memiliki divisi.
4. Daily Report dapat terkait project.
5. Kepala Divisi dapat mengedit laporan.
6. Riwayat laporan tetap tersedia.
7. Dashboard mengambil data laporan secara otomatis.

---

# 54. BUSINESS RULE — REVISION

1. Revisi hanya dapat dibuat pada project Under Review.
2. Setiap revisi memiliki reviewer.
3. Setiap revisi memiliki catatan.
4. Revisi memiliki status Open/Resolved.
5. Project dapat kembali ke Under Review setelah revisi selesai.

---

# 55. BUSINESS RULE — CONTENT

1. Content harus memiliki project.
2. Content dibuat ketika project memasuki Upload.
3. Content minimal memiliki title, type, project, platform, dan status.
4. Content Ready tampil di Stock Management.
5. Content Uploaded tercatat dengan tanggal upload.
6. Content dapat diarsipkan.

---

# 56. DATABASE EXISTING

Database yang sudah tersedia:

## users

Menyimpan:

- User
- Role
- Division

## projects

Menyimpan:

- Project
- Start Date
- Deadline
- Status
- Reference Link
- Brief

## project_user

Pivot Many-to-Many:

Project ↔ User

---

# 57. DATABASE ADDITIONAL

Tambahkan tabel:

## project_tasks

Field:

- id
- project_id
- assigned_to
- title
- description
- task_date
- status
- completed_at
- created_at
- updated_at

---

## daily_reports

Field:

- id
- user_id
- division
- project_id
- report_date
- summary
- completed_work
- unfinished_work
- problems
- next_plan
- notes
- created_at
- updated_at

---

## revision_notes

Field:

- id
- project_id
- user_id
- note
- status
- created_at
- updated_at

---

## contents

Field:

- id
- project_id
- title
- type
- platform
- file_path
- file_url
- caption
- status
- uploaded_at
- created_at
- updated_at

---

# 58. DATABASE RELATIONSHIP

Struktur utama:

USERS
│
├── PROJECTS
│
├── DAILY_REPORTS
│
├── PROJECT_TASKS
│
└── REVISION_NOTES

PROJECTS
│
├── PROJECT_USER
│
├── PROJECT_TASKS
│
├── DAILY_REPORTS
│
├── REVISION_NOTES
│
└── CONTENTS

---

# 59. PROJECT DETAIL PAGE

Project Detail merupakan halaman pusat workflow project.

Struktur:

PROJECT HEADER

- Project Name
- Status
- Progress
- Deadline

PROJECT INFORMATION

- Start Date
- Division
- Members
- Brief
- Reference

PROJECT PHASE

Planning
Proses
Under Review
Revisi
Upload
Complete

DAILY TASK

- Task
- PIC
- Date
- Status
- Checklist

DAILY REPORT

- Tanggal
- Kepala Divisi
- Ringkasan

REVISION

- Reviewer
- Catatan
- Status

CONTENT

- Content hasil project

---

# 60. DASHBOARD DATA FLOW

Dashboard tidak menyimpan statistik manual.

Database
↓
Projects
Tasks
Reports
Contents
Users
Revisions
↓
Dashboard
↓
KPI
Project Progress
Task Hari Ini
Division Activity
Content Stock
Report Summary

---

# 61. AUTOMATIC PROGRESS

Formula utama:

Completed Task / Total Task × 100

Contoh:

Total Task = 20
Completed = 15

Progress = 75%

Progress ditampilkan secara otomatis.

---

# 62. AUTOMATIC ACTIVITY

Sistem dapat menghitung aktivitas berdasarkan:

- Task dibuat
- Task selesai
- Report dibuat
- Report diperbarui
- Revision dibuat
- Revision diselesaikan
- Content dibuat
- Content uploaded

Data digunakan untuk Dashboard dan Report.

---

# 63. UI/UX REQUIREMENT

Gunakan SB Admin 2 sebagai basis utama.

Prioritas:

- Simple
- Clean
- Responsive
- Readable
- Fast
- Consistent

Sidebar mengikuti struktur:

DASHBOARD

MENU UTAMA
- Project Management
- Stock Management
- Team Management

MONITORING DIVISI
- Tim & Laporan
- Cetak Laporan

---

# 64. DASHBOARD LAYOUT

Struktur desktop:

NAVBAR
│
├── SIDEBAR
│
└── CONTENT
    │
    ├── PAGE HEADER
    │
    ├── KPI CARDS
    │
    ├── PROJECT OVERVIEW
    │
    ├── TASK TODAY
    │
    ├── DIVISION ACTIVITY
    │
    └── CONTENT STOCK

---

# 65. RESPONSIVE

Sistem harus dapat digunakan pada:

- Desktop
- Tablet
- Mobile

Prioritas:

Desktop
>
Tablet
>
Mobile

---

# 66. SECURITY

Sistem minimal menggunakan:

- Authentication
- Authorization
- Role-based access
- CSRF protection
- Input validation
- File validation
- Password hashing
- Session protection

---

# 67. DATA VALIDATION

Validasi wajib diterapkan pada:

- Project
- Task
- Daily Report
- Revision
- Content
- Team

Contoh:

Project harus memiliki:

- Name
- Start Date
- Deadline

Task harus memiliki:

- Project
- Title
- Date

Content harus memiliki:

- Project
- Title
- Type
- Platform

---

# 68. MVP SCOPE

## INCLUDED

Authentication
Team Management
Project Management
Project Monitoring
Project Task
Checklist
Project Progress
Daily Report
Division Monitoring
Review
Revision
Content Stock
Automatic Dashboard
Automatic Reporting
PDF Report
Browser Print

## NOT INCLUDED

Inventory Equipment
WhatsApp Automation
n8n Automation
AI Content Generation
Automatic Social Media Posting
Mobile Application
Financial Management
CRM
Client Management

---

# 69. DEVELOPMENT PHASE

## PHASE 1 — EXISTING FOUNDATION

[x] Laravel 13
[x] SQLite
[x] SB Admin 2
[x] Authentication
[x] Users
[x] Team Management
[x] Project Management
[x] Project/User Relationship

---

## PHASE 2 — PROJECT MONITORING

[ ] Project Task
[ ] Checklist
[ ] Daily Task
[ ] Automatic Progress
[ ] Project Phase
[ ] Project Detail
[ ] Automatic Status

---

## PHASE 3 — DIVISION MONITORING

[ ] Division Dashboard
[ ] Daily Report
[ ] Edit Report
[ ] Previous Reports
[ ] Project Daily Activity
[ ] Division Statistics

---

## PHASE 4 — REVIEW & REVISION

[ ] Under Review
[ ] Revision Notes
[ ] Revision Status
[ ] Approve Project
[ ] Return to Revision
[ ] Upload Phase

---

## PHASE 5 — STOCK MANAGEMENT

[ ] Content Table
[ ] Create Content
[ ] Ready to Upload
[ ] Uploaded
[ ] Archived
[ ] Project → Content Relationship
[ ] Automatic Project Complete

---

## PHASE 6 — DASHBOARD

[ ] Daily Statistics
[ ] Weekly Statistics
[ ] Monthly Statistics
[ ] Yearly Statistics
[ ] Active Project
[ ] Task Today
[ ] Division Activity
[ ] Content Stock
[ ] Project Progress

---

## PHASE 7 — REPORTING

[ ] Automatic Daily Report
[ ] Automatic Weekly Report
[ ] Automatic Monthly Report
[ ] Automatic Yearly Report
[ ] Project Report
[ ] Division Report
[ ] PDF
[ ] Browser Print

---

# 70. ACCEPTANCE CRITERIA

## Dashboard

Dashboard harus dapat menampilkan:

- Total Project
- Project Aktif
- Project berdasarkan status
- Task Hari Ini
- Task Selesai
- Total Anggota
- Aktivitas Divisi
- Content Ready
- Project Progress

---

## Project

User yang memiliki akses harus dapat:

- Membuat project
- Mengedit project
- Menghapus project
- Menambahkan anggota
- Melihat detail project
- Melihat task
- Melihat progress
- Melihat laporan
- Melihat revision
- Melihat content

---

## Task

Sistem harus:

- Membuat task
- Assign PIC
- Menentukan tanggal
- Mengubah status
- Menyelesaikan task
- Menghitung progress otomatis

---

## Division

Sistem harus menampilkan:

- Jumlah anggota
- Project aktif
- Task hari ini
- Laporan hari ini
- Riwayat laporan

---

## Daily Report

Kepala Divisi harus dapat:

- Menambah laporan
- Mengedit laporan
- Melihat laporan sebelumnya

Dashboard harus dapat membaca data laporan secara otomatis.

---

## Review

Reviewer harus dapat:

- Membuka project
- Melihat hasil
- Memberikan catatan
- Menyetujui
- Mengembalikan ke revisi

---

## Revision

Sistem harus:

- Menyimpan catatan revisi
- Menyimpan reviewer
- Menyimpan status revisi
- Mengizinkan project kembali ke Under Review

---

## Stock

Sistem harus:

- Menampilkan content Ready
- Menghubungkan content dengan project
- Menyimpan platform
- Menyimpan file/link
- Menyimpan status upload
- Menyimpan tanggal upload

---

## Reporting

Sistem harus menghasilkan laporan berdasarkan data database tanpa input ulang.

---

# 71. DEFINITION OF DONE

MVP dianggap selesai apabila:

[ ] User dapat login
[ ] Role bekerja
[ ] Division bekerja
[ ] Team Management bekerja
[ ] Project Management bekerja
[ ] Project memiliki workflow
[ ] Project memiliki task
[ ] Task memiliki checklist
[ ] Progress dihitung otomatis
[ ] Daily Report bekerja
[ ] Division Monitoring bekerja
[ ] Review bekerja
[ ] Revision bekerja
[ ] Content Stock bekerja
[ ] Project dapat masuk Upload
[ ] Project dapat menjadi Complete
[ ] Dashboard membaca seluruh data
[ ] Daily summary otomatis
[ ] Weekly summary otomatis
[ ] Monthly summary otomatis
[ ] Yearly summary otomatis
[ ] Project report tersedia
[ ] Division report tersedia
[ ] PDF tersedia
[ ] Browser Print tersedia
[ ] Authorization bekerja
[ ] Validation bekerja
[ ] Responsive layout bekerja

---

# 72. CORE SYSTEM FLOW

PROJECT CREATED
        ↓
PLANNING
        ↓
HEAD OF DIVISION
CREATES DAILY TASK
        ↓
TEAM MEMBERS
EXECUTE TASK
        ↓
CHECKLIST UPDATED
        ↓
PROJECT PROGRESS
UPDATED AUTOMATICALLY
        ↓
DAILY REPORT
CREATED BY HEAD OF DIVISION
        ↓
PRODUCTION COMPLETE
        ↓
UNDER REVIEW
        ↓
┌─────────────────────┐
│                     │
APPROVED            REVISION
│                     │
│                     ↓
│                 FIX TASK
│                     │
│                     ↓
│                UNDER REVIEW
│
↓
UPLOAD
↓
CONTENT STOCK
↓
COMPLETE
↓
AUTOMATIC REPORT
↓
DASHBOARD