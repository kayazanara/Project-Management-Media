<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->seed(TestAccountSeeder::class);
    }

    // ============================================================
    // HELPER
    // ============================================================

    private function olehEmail(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    private function kepalaBroadcast(): User
    {
        return $this->olehEmail('kepala.broadcast@test.com');
    }

    private function anggotaBroadcast(): User
    {
        return $this->olehEmail('anggota.broadcast1@test.com');
    }

    private function buatKonten(array $override = []): Content
    {
        $anggota = User::where('division', 'Lapangan')->firstOrFail();

        $project = Project::create([
            'name'       => 'Project Uji Konten',
            'start_date' => Carbon::today()->toDateString(),
            'deadline'   => Carbon::today()->addDays(7)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
        ]);

        $project->users()->attach($anggota->id);

        return Content::create(array_merge([
            'content_code' => 'DAK001',
            'project_id'   => $project->id,
            'title'        => 'Konten Uji',
            'type'         => 'Reels',
            'platform'     => ['Instagram'],
            'file_url'     => 'https://drive.google.com/file-uji-penting',
            'caption'      => 'Caption penting yang tidak boleh hilang',
            'upload_type'  => 'content_stock',
            'status'       => 'Ready',
        ], $override));
    }

    // ============================================================
    // S1 — EDIT KONTEN TIDAK BOLEH MENGHAPUS DATA
    // ============================================================

    public function test_edit_konten_tidak_menghapus_link_dan_caption(): void
    {
        $konten = $this->buatKonten();

        // Kirim seperti form lama (tanpa file_url & caption) -> tidak boleh mengosongkan data
        $this->actingAs($this->pembina())
            ->put("/contents/{$konten->id}", [
                'project_id' => $konten->project_id,
                'title'      => 'Judul Diubah',
            ])
            ->assertRedirect();

        $konten->refresh();

        $this->assertSame('https://drive.google.com/file-uji-penting', $konten->file_url, 'Link file TIDAK boleh hilang.');
        $this->assertSame('Caption penting yang tidak boleh hilang', $konten->caption, 'Caption TIDAK boleh hilang.');
        $this->assertSame('Judul Diubah', $konten->title);
    }

    public function test_form_edit_adalah_form_edit_bukan_form_tambah(): void
    {
        $konten = $this->buatKonten();

        $res = $this->actingAs($this->pembina())->get("/contents/{$konten->id}/edit");
        $res->assertOk();

        // Form harus POST ke contents.update + method PUT (dulu: ke contents.store -> bikin konten BARU)
        $res->assertSee('action="' . route('contents.update', $konten->id) . '"', false);
        $res->assertSee('name="_method" value="PUT"', false);

        // Nilai lama harus termuat
        $res->assertSee('file-uji-penting', false);
        $res->assertSee('Caption penting yang tidak boleh hilang', false);
        $res->assertSee('Konten Uji', false);
    }

    // ============================================================
    // S4 — KODE KONTEN
    // ============================================================

    public function test_kode_konten_lanjut_dari_nomor_terbesar_dan_3_digit(): void
    {
        $konten = $this->buatKonten(['content_code' => 'MEM022']);

        // kode bernomor KECIL tapi dibuat BELAKANGAN (dulu: generator ikut baris terakhir -> salah)
        $this->buatKonten(['content_code' => 'MEM005', 'title' => 'Konten Lama']);

        $this->actingAs($this->pembina())
            ->post('/contents/upload-stock', [
                'project_id' => $konten->project_id,
                'items'      => [[
                    'title'    => 'Stok Baru',
                    'file_url' => 'https://drive.google.com/stok-baru',
                    'prefix'   => 'MEM',
                ]],
            ])
            ->assertRedirect();

        $baru = Content::where('title', 'Stok Baru')->firstOrFail();

        $this->assertSame('MEM023', $baru->content_code, 'Kode harus lanjut dari nomor TERBESAR (023), format 3 digit.');
    }

    // ============================================================
    // S2 / S3 / S10 — HAK AKSES
    // ============================================================

    public function test_anggota_broadcast_tidak_bisa_mengubah_status_konten(): void
    {
        $konten = $this->buatKonten();

        $this->actingAs($this->anggotaBroadcast())
            ->patch("/contents/{$konten->id}/status", ['status' => 'Archived'])
            ->assertForbidden();

        $this->assertSame('Ready', $konten->fresh()->status);
    }

    public function test_anggota_broadcast_tidak_bisa_menambah_konten(): void
    {
        $konten = $this->buatKonten();

        $this->actingAs($this->anggotaBroadcast())
            ->post('/contents', [
                'project_id'      => $konten->project_id,
                'title'           => 'Konten Selundupan',
                'category_prefix' => 'DAK',
                'type'            => 'Reels',
                'platform'        => ['Instagram'],
            ])
            ->assertForbidden();

        $this->assertNull(Content::where('title', 'Konten Selundupan')->first());
    }

    public function test_metadata_hanya_untuk_kepala_broadcast_atau_manager(): void
    {
        $konten = $this->buatKonten();

        $this->actingAs($this->anggotaBroadcast())
            ->patch("/contents/{$konten->id}/broadcast-metadata", [
                'category'     => 'Dakwah',
                'content_type' => 'Reels',
                'platform'     => ['Instagram'],
            ])
            ->assertRedirect();

        $konten->refresh();

        $this->assertNull($konten->broadcast_filled_at, 'Anggota biasa tidak boleh mengisi metadata.');
        $this->assertSame('Ready', $konten->status);
    }

    public function test_metadata_hanya_untuk_konten_stock(): void
    {
        $konten = $this->buatKonten(['upload_type' => 'raw', 'content_code' => 'RAW001']);

        $this->actingAs($this->kepalaBroadcast())
            ->patch("/contents/{$konten->id}/broadcast-metadata", [
                'category'     => 'Dakwah',
                'content_type' => 'Reels',
                'platform'     => ['Instagram'],
            ])
            ->assertRedirect();

        $this->assertNull($konten->fresh()->broadcast_filled_at, 'Metadata tidak berlaku untuk konten raw.');
    }

    // ============================================================
    // ALUR BARU: metadata -> READY -> POST -> (unpost)
    // ============================================================

    public function test_metadata_mengubah_status_jadi_ready_bukan_completed(): void
    {
        $konten = $this->buatKonten();

        $this->actingAs($this->kepalaBroadcast())
            ->patch("/contents/{$konten->id}/broadcast-metadata", [
                'category'     => 'Dakwah',
                'content_type' => 'Reels',
                'platform'     => ['Instagram'],
                'caption'      => 'Caption siap posting',
            ])
            ->assertRedirect();

        $konten->refresh();

        $this->assertNotNull($konten->broadcast_filled_at);
        $this->assertSame('Ready', $konten->status, 'Setelah metadata status harus READY (siap posting).');
        $this->assertNull($konten->uploaded_at, 'uploaded_at baru diisi saat diposting.');
    }

    public function test_post_hanya_setelah_metadata_dan_bisa_dibatalkan(): void
    {
        $konten = $this->buatKonten(['broadcast_filled_at' => null]);

        // belum ada metadata -> ditolak
        $this->actingAs($this->kepalaBroadcast())
            ->post("/contents/{$konten->id}/post")
            ->assertRedirect();

        $this->assertSame('Ready', $konten->fresh()->status, 'Post tanpa metadata harus ditolak.');

        $konten->update(['broadcast_filled_at' => now()]);

        $this->actingAs($this->kepalaBroadcast())->post("/contents/{$konten->id}/post")->assertRedirect();

        $konten->refresh();
        $this->assertSame('Uploaded', $konten->status, 'Setelah diposting -> Uploaded (ditampilkan "Sudah Diposting").');
        $this->assertNotNull($konten->uploaded_at);

        $this->actingAs($this->kepalaBroadcast())->post("/contents/{$konten->id}/unpost")->assertRedirect();

        $konten->refresh();
        $this->assertSame('Ready', $konten->status, 'Unpost mengembalikan ke Ready.');
        $this->assertNull($konten->uploaded_at);
    }

    public function test_hanya_kepala_broadcast_atau_manager_yang_bisa_post(): void
    {
        $konten = $this->buatKonten(['broadcast_filled_at' => now()]);

        $this->actingAs($this->anggotaBroadcast())
            ->post("/contents/{$konten->id}/post")
            ->assertForbidden();

        $this->assertSame('Ready', $konten->fresh()->status);
    }
}
