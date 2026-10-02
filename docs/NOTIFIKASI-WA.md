# NOTIFIKASI WHATSAPP — Project Media

Notifikasi dikirim lewat **WAHA** (`app/Services/WhatsappNotifier.php`).
Semua pengiriman tercatat di tabel `notification_logs`.

---

## 1. Pengaturan (`.env`)

| Kunci | Arti |
|---|---|
| `WAHA_ENABLED` | `false` = semua pengiriman dilewati (aman untuk pengembangan lokal) |
| `WAHA_URL` | Alamat WAHA, default `http://localhost:3000` |
| `WAHA_API_KEY` | Kunci API WAHA |
| `WAHA_SESSION` | Nama sesi WAHA, default `Hermes`/`default` |
| `WAHA_TIMEOUT` | Batas tunggu **per pesan** dalam detik (default **5**) |
| `WAHA_KIRIM_SETELAH_RESPONSE` | `true` (default) = pesan dikirim setelah halaman pindah ke pengguna |

> ⚠️ `WAHA_TIMEOUT` jangan dibesarkan tanpa alasan: waktu tunggu terburuk =
> `jumlah penerima × WAHA_TIMEOUT`. Dulu 15 detik × 6 penerima = sampai 90 detik.

## 2. Siapa menerima apa (penting — pernah salah sasaran)

| Kejadian | Penerima |
|---|---|
| Project baru dibuat | Ketua Media **aktif** |
| Reminder H-3, H-1, dan lewat deadline | **Anggota project** (Aktif, punya nomor) + **Ketua Media** |
| Ajukan task review / review | Pembina |
| Minta revisi / revisi diperbaiki | Anggota project + Ketua Media |
| Project **dirilis** | Anggota project + Ketua Media |
| **ACC** (disetujui) | Anggota project + Ketua Media |
| **Dilanjutkan tanpa ACC Ketua Media** (override) | Anggota project + Ketua Media |
| Project selesai / dibatalkan complete | Anggota project + Ketua Media |
| Notulen rapat dipublikasikan | Semua pemimpin **berstatus Aktif** (Pembina, Ketua Media, Kepala Divisi) |
| Action item rapat | PIC action item tersebut |
| Bahan raw diunggah | Anggota divisi tujuan |
| Stok konten diunggah | Divisi Broadcast |

Aturan yang berlaku di semua jalur:
1. **Kepala Divisi hanya menerima kalau dia memang anggota project itu.** Dulu
   semua `kepala_divisi` ikut dikirimi dan itu salah sasaran.
2. Pengguna **non-`Aktif`** tidak dikirimi.
3. **Pelaku aksi tidak dikirimi** pesannya sendiri.
4. Pengguna tanpa nomor telepon dilewati (dicatat di log).

## 3. Aturan anti-dobel (penentu di `WhatsappNotifier` + `NotificationLog`)

| Jenis | Aturan |
|---|---|
| `reminder_h3`, `reminder_h1`, `overdue`, `project_created`, `project_approved`, `meeting_action_item` (const `TIPE_SEKALI`) | Sekali saja — tidak diulang |
| Kejadian lain (rilis, revisi, selesai, dll) | Anti-dobel **30 menit** (const `ANTI_DOBEL_MENIT`) |

Kenapa dibedakan: revisi kedua atau reopen kedua **harus tetap terkirim**, sedangkan
reminder harian tidak boleh diulang setiap kali scheduler jalan.

### 3.1 Empat cara dobel yang sudah ditutup (2 Okt 2026)

| # | Celah | Penutupnya |
|---|---|---|
| 1 | Percobaan **gagal** (timeout) dianggap "belum terkirim", padahal WhatsApp bisa saja sudah menerimanya → dicoba lagi → orangnya dapat 2 pesan | Baris `failed` tetap dianggap **bekas pengiriman selama 1 jam** (const `TAHAN_GAGAL_MENIT`). Sesudah 1 jam baru boleh dicoba lagi, supaya pesan yang benar-benar gagal tidak hilang |
| 2 | Anti-dobel dihitung per **akun**, bukan per **nomor** → dua akun dengan nomor sama = 2 pesan | Pemeriksaan memakai **nomor** (`phone`), bukan `user_id` |
| 3 | Pesan rapat/action item dicek dari **teks yang persis sama** → judul rapat diubah = pesan tugas yang sama dikirim lagi | Memakai **kunci** (`dedupe_key`): satu tugas = satu kunci, jadi tidak terkirim ulang walau rapatnya disimpan berkali-kali; tugas berbeda tetap terkirim |
| 4 | Pengiriman ditunda sesudah respons, sehingga keputusan anti-dobel terjadi **saat kirim** → dua permintaan cepat bisa sama-sama lolos | Baris log **dicadangkan lebih dulu** dengan status `pending` saat aksi dijalankan (`siapkan()`), baru dikirim (`kirim()`). Status akhirnya `sent` atau `failed` |

Pesan **ACC** dikirim **sekali per project** (saat kedua pihak sudah ACC). Kalau project
**dibuka ulang (reopen)**, catatan ACC lama dihapus supaya ACC berikutnya tetap dikabari.

### 3.2 Perintah pemeriksa

```bash
php artisan notif:cek-duplikat              # 14 hari terakhir
php artisan notif:cek-duplikat --hari=30    # 30 hari terakhir
```

Hanya membaca (tidak mengubah data) dan menampilkan: ringkasan per jenis, kiriman berulang
per project/akun, satu nomor menerima pesan identik, "gagal padahal sudah terkirim",
pengiriman beruntun dalam satu menit, jumlah per hari, dan baris `pending` yang menggantung.

Catatan: pengiriman beruntun dalam satu menit **belum tentu dobel** — bisa jadi orang itu
memang terlibat di beberapa project yang jatuh tempo bersamaan.

## 4. Dikirim SETELAH halaman pindah (perbaikan kecepatan)

`WhatsappNotifier::kirimSetelahResponse(callable)` mendaftarkan pengiriman sebagai
*terminating callback* (`app()->terminating`), jadi dijalankan **setelah** respons
terkirim ke browser.

- Dipakai di 7 jalur: `ProjectController::notifyUsers`, `MeetingController::notifyPublish`,
  `MeetingController::notifyActionItems`, dan `ContentController` (upload raw & stock).
- **Sengaja TIDAK memakai** `dispatch()->afterResponse()` karena `QUEUE_CONNECTION=database`
  — job akan menunggu worker dan notifikasi tidak terkirim.
- Command `reminders:send` tetap **sinkron** karena laporannya butuh hasil nyata.
- Hasil pengukuran (WAHA lambat 1,5 detik/pesan, 6 penerima):
  **sebelum 9.231 ms** yang dirasakan pengguna → **sesudah 61 ms**.

## 5. Template pesan (13)

`projectCreatedTemplate`, `taskReviewSubmittedTemplate`, `projectReleasedTemplate`,
`requestReviewTemplate`, `revisionRequestedTemplate`, `projectApprovedTemplate`,
`projectCompletedTemplate`, `meetingPublishedTemplate`, `meetingActionItemTemplate`,
`overdueTemplate`, `rawUploadedTemplate`, `stockUploadedTemplate`,
`projectOverrideApprovedTemplate`.

Gaya pesan: sapa nama penerima, judul bergaya bold WhatsApp (`*...*`), tautan ke halaman
terkait, dan tanda tangan `*— Mas Khoir*` (kecuali pesan khusus memakai `*— Pembina Media*`).

## 6. Cara menguji tanpa mengirim pesan sungguhan

```php
// di dalam tes
Http::fake();                                  // menahan pengiriman
config(['services.waha.enabled' => true]);     // WAHA "hidup"
$this->actingAs($pembina)->patch("/projects/{$id}/release");
$this->assertDatabaseHas('notification_logs', ['type' => 'project_released', 'status' => 'sent']);
```

Perintah lain:

```bash
php artisan reminders:send        # jalankan reminder manual (hati-hati: benar-benar mengirim)
php artisan test --filter=NotifikasiWaTest
```

Di laptop `WAHA_ENABLED=false`, jadi pengiriman sungguhan tidak terjadi walaupun
alur kodenya tetap dijalankan.

## 7. Kalau ada keluhan "WA saya tidak terkirim"

1. Cek `notification_logs`: ada barisnya? Status `sent` atau `failed`?
   - Tidak ada baris → pengguna tidak termasuk penerima, nomornya kosong, atau
     tertahan aturan anti-dobel.
   - Status `failed` → lihat kolom `response`, biasanya WAHA tidak jalan atau sesi mati.
2. Cek `users.phone` (format `62…` lewat `PhoneHelper`).
3. Cek `WAHA_ENABLED`, `WAHA_URL`, dan status sesi WAHA.
4. Pastikan penerima bukan pelaku aksi dan statusnya `Aktif`.
