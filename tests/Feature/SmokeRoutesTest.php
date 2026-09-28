<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Smoke test: buka SEMUA route GET sebagai 4 role berbeda.
 * Tujuan: menangkap error 500 / exception yang tidak terlihat dari test unit.
 * Pakai RefreshDatabase + seeder (DB :memory:), jadi DB asli tidak tersentuh.
 */
class SmokeRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jangan biarkan notifikasi WhatsApp keluar sungguhan saat test
        Http::fake();

        $this->seed([TestAccountSeeder::class, DemoDataSeeder::class]);
    }

    public function test_semua_halaman_get_tidak_error_untuk_semua_role(): void
    {
        $ids = [
            'project'      => Project::query()->value('id') ?? 1,
            'content'      => Content::query()->value('id') ?? 1,
            'meeting'      => Meeting::query()->value('id') ?? 1,
            'id'           => Project::query()->value('id') ?? 1,
            'divisionName' => User::query()->whereNotNull('division')->value('division') ?? 'Lapangan',
        ];
        $taskId = ProjectTask::query()->value('id') ?? 1;

        $failures = [];
        $summary  = [];

        foreach (['pembina', 'ketua_media', 'kepala_divisi', 'anggota'] as $role) {
            $user = User::where('division_role', $role)->where('status', 'Aktif')->first();

            if (! $user) {
                $failures[] = "role {$role}: user tidak ditemukan di seeder";

                continue;
            }

            $this->actingAs($user);

            foreach (Route::getRoutes() as $route) {
                if (! in_array('GET', $route->methods(), true)) {
                    continue;
                }

                $uri = $route->uri();

                // Route bawaan Laravel (butuh signed URL) — lewati
                if (str_starts_with($uri, 'storage/')) {
                    continue;
                }

                if (str_starts_with($uri, 'tasks/')) {
                    $uri = "tasks/{$taskId}/edit";
                } elseif (str_contains($uri, '{')) {
                    $uri = preg_replace_callback(
                        '/\{(\w+)\??\}/',
                        fn ($m) => $ids[$m[1]] ?? 1,
                        $uri
                    );
                }

                $code = $this->get('/' . ltrim($uri, '/'))->getStatusCode();

                $summary[$code] = ($summary[$code] ?? 0) + 1;

                if ($code >= 500) {
                    $failures[] = "{$role}  GET /{$uri}  ->  HTTP {$code}";
                }
            }
        }

        $total = array_sum($summary);

        $this->assertSame([], $failures, "Ada halaman yang error 500:\n" . implode("\n", $failures));
        $this->assertGreaterThanOrEqual(100, $total, 'Request yang diuji terlalu sedikit — cek loop route.');
    }
}
