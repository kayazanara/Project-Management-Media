<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# 📂 Sistem Manajemen Proyek & Media - PP Hubbul Khoir Sukoharjo

Sistem informasi berbasis web yang dikembangkan menggunakan **Laravel 13** dan **SQLite** untuk mengelola proyek media, penugasan anggota tim (*Ustadz* dan *Santri*), serta inventaris di lingkungan pondok pesantren.

---

## 🛠️ Stack Teknologi & Lingkungan
* **Framework:** Laravel 13
* **Database:** SQLite
* **Frontend Template:** SB Admin 2 (Bootstrap 4)
* **PHP Version:** 8.3+

---

## 📁 Struktur Direktori & File Utama yang Telah Dibuat

### 1. Database & Migrations (`database/migrations/`)
* `create_users_table.php`: Tabel pengguna dengan opsi role `Ustadz` dan `Santri`, serta divisi media.
* `create_projects_table.php`: Tabel proyek (nama, tanggal mulai, deadline, status, link referensi, brief).
* `create_project_user_table.php`: Tabel *pivot* untuk relasi *Many-to-Many* antara proyek dan anggota tim.

### 2. Models (`app/Models/`)
* **`User.php`**: 
  * Memiliki relasi *Many-to-Many* ke `Project` (`projects()`).
* **`Project.php`**: 
  * Menggunakan `$guarded = ['id']`.
  * Memiliki relasi *Many-to-Many* ke `User` (`users()`).

### 3. Controllers (`app/Http/Controllers/`)
* **`TeamController.php`**: Mengatur CRUD data anggota tim (Tambah, Lihat, Edit, Hapus) dengan validasi input dan pengecekan role/divisi.
* **`ProjectController.php`**: Mengatur CRUD data proyek media:
  * `index()`: Menampilkan daftar proyek beserta relasi user yang terlibat.
  * `create()` & `store()`: Menangani form tambah proyek dengan multi-user (checkbox) dan status default `Planning`.
  * `edit()` & `update()`: Menangani form edit proyek dan sinkronisasi anggota tim (status proyek dikunci/tidak bisa diubah manual agar sinkron dengan monitoring divisi).
  * `destroy()`: Menghapus proyek beserta pembersihan data di tabel pivot.

### 4. Views (`resources/views/`)
* `layouts/app.blade.php`: Kerangka utama layout admin (SB Admin 2) beserta navigasi sidebar.
* `team/`: Berisi file `index.blade.php`, `create.blade.php`, dan `edit.blade.php` untuk manajemen tim.
* `projects/`: Berisi file `index.blade.php`, `create.blade.php`, dan `edit.blade.php` untuk manajemen proyek.

---

## 🌐 Daftar Rute Web (`routes/web.php`)

Semua rute di bawah dilindungi oleh middleware autentikasi (`auth`):
* **Team Management:**
  * `GET /team` ➔ `TeamController@index` (`team.index`)
  * `GET /team/create` ➔ `TeamController@create` (`team.create`)
  * `POST /team` ➔ `TeamController@store` (`team.store`)
  * `GET /team/{id}/edit` ➔ `TeamController@edit` (`team.edit`)
  * `PUT /team/{id}` ➔ `TeamController@update` (`team.update`)
  * `DELETE /team/{id}` ➔ `TeamController@destroy` (`team.destroy`)

* **Project Management:**
  * `GET /projects` ➔ `ProjectController@index` (`projects.index`)
  * `GET /projects/create` ➔ `ProjectController@create` (`projects.create`)
  * `POST /projects` ➔ `ProjectController@store` (`projects.store`)
  * `GET /projects/{id}/edit` ➔ `ProjectController@edit` (`projects.edit`)
  * `PUT /projects/{id}` ➔ `ProjectController@update` (`projects.update`)
  * `DELETE /projects/{id}` ➔ `ProjectController@destroy` (`projects.destroy`)

---

## 🚀 Roadmap & Fitur Selanjutnya
* [ ] **Modul Stock Management:** Inventaris peralatan media (Kamera, Tripod, Mic, Lighting, dll.).
* [ ] **Modul Monitoring Divisi & Auto-Update Status:** Mengubah status proyek secara otomatis berdasarkan laporan dari masing-masing divisi/tim di lapangan.