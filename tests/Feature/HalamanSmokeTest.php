<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ⚡ AUDIT MENYELURUH (grup view): uji asap SEMUA halaman utama untuk beberapa peran.
 *
 * Tujuannya menangkap kelas bug yang sudah 2x ketemu di proyek ini:
 *   - view memakai variabel yang TIDAK dikirim controller -> error 500
 *   - view di-render beda per peran (mis. tombol/kolom khusus manager)
 *
 * Setiap halaman dianggap SEHAT selama statusnya < 500 (redirect 302 atau 403 itu wajar).
 */
class HalamanSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->seed(TestAccountSeeder::class);
    }

    private function pembina(): User
    {
        return User::where('division_role', 'pembina')->where('status', 'Aktif')->firstOrFail();
    }

    private function siapkanData(): array
    {
        $pembina = $this->pembina();
        $anggota = User::where('division_role', 'anggota')->where('status', 'Aktif')->firstOrFail();

        $project = Project::create([
            'name'       => 'Project Smoke',
            'start_date' => now()->toDateString(),
            'deadline'   => now()->addDays(7)->toDateString(),
            'status'     => Project::STATUS_ON_PROGRESS,
            'brief'      => 'Brief uji asap.',
        ]);
        $project->users()->attach([$pembina->id, $anggota->id]);

        $task = ProjectTask::create([
            'project_id'  => $project->id,
            'title'       => 'Task Smoke',
            'status'      => 'Pending',
            'task_date'   => now()->toDateString(),
            'assigned_to' => $anggota->id,
            'weight'      => 5,
        ]);
        $task->contributors()->sync([$anggota->id]);

        $content = Content::create([
            'content_code' => 'SMK001',
            'project_id'   => $project->id,
            'title'        => 'Konten Smoke',
            'upload_type'  => 'content_stock',
            'status'       => 'Ready',
            'platform'     => ['Instagram'],
        ]);

        $meeting = Meeting::create([
            'title'        => 'Rapat Smoke',
            'meeting_date' => now()->toDateString(),
            'start_time'   => '08:00',
            'end_time'     => '09:00',
            'status'       => 'published',
            'created_by'   => $pembina->id,
        ]);

        return [$pembina, $anggota, $project, $task, $content, $meeting];
    }

    private function periksaHalaman(User $user, array $uris, bool $harusRender = false): void
    {
        foreach ($uris as $uri) {
            $res = $this->actingAs($user)->get($uri);

            if ($harusRender) {
                $this->assertSame(
                    200,
                    $res->status(),
                    "Halaman {$uri} TIDAK merender (status {$res->status()}, peran {$user->division_role})"
                );

                continue;
            }

            $this->assertLessThan(
                500,
                $res->status(),
                "Halaman {$uri} ERROR {$res->status()} (peran: {$user->division_role})"
            );
        }
    }

    public function test_semua_halaman_utama_sehat_untuk_pembina(): void
    {
        [$pembina, $anggota, $project, $task, $content, $meeting] = $this->siapkanData();

        // halaman detail hanya untuk Raw & Activity (konten Stock sengaja diredirect) -> buat 1 activity
        $activity = Content::create([
            'content_code' => 'SMK002',
            'project_id'   => $project->id,
            'title'        => 'Activity Smoke',
            'upload_type'  => 'activity',
            'status'       => 'Completed',
            'caption'      => 'Laporan activity untuk uji asap.',
            'platform'     => [],
        ]);

        $this->periksaHalaman($pembina, [
            '/dashboard',
            '/workdesk',
            '/monitoring',
            '/projects',
            '/projects/create',
            "/projects/{$project->id}",
            "/projects/{$project->id}/edit",
            '/contents',
            '/contents/activity',
            '/contents/raw',
            '/contents/create',
            '/contents/upload-raw',
            '/contents/upload-stock',
            '/contents/upload-activity',
            "/contents/{$activity->id}/lihat",
            "/contents/{$content->id}/edit",
            "/contents/{$content->id}/broadcast-metadata",
            '/meetings',
            '/meetings/create',
            "/meetings/{$meeting->id}",
            "/meetings/{$meeting->id}/edit",
            "/meetings/{$meeting->id}/export-pdf",
            "/meetings/{$meeting->id}/export-word",
            '/team',
            '/team/create',
            "/team/{$anggota->id}/edit",
            '/division/Tim%20Lapangan',
            '/division/Tim%20Editing',
            '/division/Tim%20Broadcast',
            '/division/Tim%20AI%20Research',
            '/reports',
            '/my-profile/edit',
            "/profile/{$anggota->id}",
            "/tasks/{$task->id}/edit",
        ], true);
    }

    public function test_halaman_anggota_dan_kepala_divisi_sehat(): void
    {
        [, $anggota, $project, , $content, $meeting] = $this->siapkanData();

        $kepala = User::where('division_role', 'kepala_divisi')->where('status', 'Aktif')->firstOrFail();

        $uris = [
            '/dashboard',
            '/workdesk',
            '/projects',
            "/projects/{$project->id}",
            '/contents/activity',
            '/contents/raw',
            "/contents/{$content->id}/lihat",
            '/meetings',
            "/meetings/{$meeting->id}",
            '/division/Tim%20Lapangan',
            '/my-profile/edit',
            "/profile/{$anggota->id}",
        ];

        $this->periksaHalaman($anggota, $uris);
        $this->periksaHalaman($kepala, $uris);
    }
}
