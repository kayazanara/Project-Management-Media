<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectRevision;
use App\Models\Content;
use App\Models\Meeting;
use App\Models\WorkdeskAlert;
use App\Models\NotificationLog;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    protected $pembina;
    protected $ketuaMedia;
    protected $kepalaDivisi;
    protected $anggota;

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🎬 Membuat data demo...');
        $this->command->info('');

        $this->loadUsers();

        if (!$this->pembina || !$this->ketuaMedia) {
            $this->command->error('❌ Akun test belum ada! Jalankan dulu:');
            $this->command->warn('   php artisan db:seed --class=TestAccountSeeder');
            return;
        }

        $this->createProjects();
        $this->createContents();
        $this->createMeetings();
        $this->createAlerts();
        $this->createNotifications();

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('✅ DEMO DATA BERHASIL DIBUAT!');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('   Projects : ' . Project::count());
        $this->command->info('   Tasks    : ' . ProjectTask::count());
        $this->command->info('   Contents : ' . Content::count());
        $this->command->info('   Meetings : ' . Meeting::count());
        $this->command->info('   Alerts   : ' . WorkdeskAlert::count());
        $this->command->info('');
        $this->command->info('🔗 Buka Workdesk: ' . url('/workdesk'));
        $this->command->info('');
    }

    protected function loadUsers(): void
    {
        $this->pembina = User::where('division_role', 'pembina')->first();
        $this->ketuaMedia = User::where('division_role', 'ketua_media')->first();

        $this->kepalaDivisi = [
            'Lapangan' => User::where('email', 'kepala.lapangan@test.com')->first(),
            'Editing' => User::where('email', 'kepala.editing@test.com')->first(),
            'Broadcast' => User::where('email', 'kepala.broadcast@test.com')->first(),
            'AI Research' => User::where('email', 'kepala.ai@test.com')->first(),
        ];

        $this->anggota = [
            'Lapangan' => User::where('division', 'Lapangan')->where('division_role', 'anggota')->get()->all(),
            'Editing' => User::where('division', 'Editing')->where('division_role', 'anggota')->get()->all(),
            'Broadcast' => User::where('division', 'Broadcast')->where('division_role', 'anggota')->get()->all(),
            'AI Research' => User::where('division', 'AI Research')->where('division_role', 'anggota')->get()->all(),
        ];
    }

    protected function createProjects(): void
    {
        $this->command->info('📁 Membuat projects...');

        $projectTemplates = [
            ['name' => 'Dokumentasi Kegiatan PSB 2027', 'status' => 'Completed', 'days_ago' => 85, 'duration_days' => 14, 'divisions' => ['Lapangan', 'Editing'], 'task_count' => 6],
            ['name' => 'Video Profil Santri Baru', 'status' => 'Completed', 'days_ago' => 75, 'duration_days' => 10, 'divisions' => ['Lapangan', 'Editing'], 'task_count' => 5],
            ['name' => 'Konten Dakwah Bulanan', 'status' => 'Completed', 'days_ago' => 65, 'duration_days' => 7, 'divisions' => ['Editing', 'Broadcast'], 'task_count' => 4],
            ['name' => 'Live Streaming Kajian Ahad', 'status' => 'Completed', 'days_ago' => 55, 'duration_days' => 5, 'divisions' => ['Lapangan', 'Broadcast'], 'task_count' => 4],
            ['name' => 'Reels Ramadan Series', 'status' => 'Completed', 'days_ago' => 45, 'duration_days' => 12, 'divisions' => ['Editing', 'Broadcast'], 'task_count' => 6],
            ['name' => 'Poster Kegiatan Tahunan', 'status' => 'Completed', 'days_ago' => 35, 'duration_days' => 8, 'divisions' => ['Editing'], 'task_count' => 4],
            ['name' => 'Podcast Santri Prestasi', 'status' => 'Completed', 'days_ago' => 25, 'duration_days' => 15, 'divisions' => ['Lapangan', 'Editing', 'AI Research'], 'task_count' => 8],
            ['name' => 'AI Research: Tren Konten 2026', 'status' => 'Completed', 'days_ago' => 15, 'duration_days' => 10, 'divisions' => ['AI Research'], 'task_count' => 5],
            ['name' => 'Konten TikTok Harian Batch 1', 'status' => 'Completed', 'days_ago' => 7, 'duration_days' => 5, 'divisions' => ['Editing', 'Broadcast'], 'task_count' => 6],
            ['name' => 'Company Profile Pesantren', 'status' => 'On Progress', 'days_ago' => 10, 'duration_days' => 20, 'divisions' => ['Lapangan', 'Editing'], 'task_count' => 7],
            ['name' => 'Dokumenter Kegiatan Outdoor', 'status' => 'On Progress', 'days_ago' => 5, 'duration_days' => 14, 'divisions' => ['Lapangan', 'Editing', 'Broadcast'], 'task_count' => 8],
            ['name' => 'Instagram Feed Series Q4', 'status' => 'On Progress', 'days_ago' => 3, 'duration_days' => 21, 'divisions' => ['Editing', 'Broadcast'], 'task_count' => 6],
            ['name' => 'Konten AI Generated Showcase', 'status' => 'Task Review', 'days_ago' => 2, 'duration_days' => 14, 'divisions' => ['AI Research', 'Editing'], 'task_count' => 5],
            ['name' => 'Event Report Bulanan', 'status' => 'Scoring', 'days_ago' => 4, 'duration_days' => 10, 'divisions' => ['Lapangan', 'Broadcast'], 'task_count' => 4],
            ['name' => 'Reels Promosi Wisuda', 'status' => 'Review', 'days_ago' => 15, 'duration_days' => 12, 'divisions' => ['Lapangan', 'Editing'], 'task_count' => 5],
            ['name' => 'Video Behind The Scene', 'status' => 'Revision', 'days_ago' => 18, 'duration_days' => 10, 'divisions' => ['Lapangan', 'Editing'], 'task_count' => 5],
            ['name' => 'Konten Prestasi Santri', 'status' => 'Approved', 'days_ago' => 8, 'duration_days' => 8, 'divisions' => ['Editing', 'Broadcast'], 'task_count' => 4],
            ['name' => 'Dokumentasi Haul Akbar', 'status' => 'Upload', 'days_ago' => 10, 'duration_days' => 7, 'divisions' => ['Lapangan', 'Broadcast'], 'task_count' => 5],
            ['name' => 'Persiapan Konten Akhir Tahun', 'status' => 'Planning', 'days_ago' => 1, 'duration_days' => 30, 'divisions' => ['Editing', 'Broadcast', 'AI Research'], 'task_count' => 0],
            ['name' => 'Kenang-Kenangan Wisuda (URGENT)', 'status' => 'On Progress', 'days_ago' => 40, 'duration_days' => 20, 'divisions' => ['Lapangan', 'Editing'], 'task_count' => 5, 'force_overdue' => true],
            ['name' => 'Recap Event Ramadhan (Terlambat)', 'status' => 'On Progress', 'days_ago' => 30, 'duration_days' => 15, 'divisions' => ['Broadcast', 'AI Research'], 'task_count' => 4, 'force_overdue' => true],
        ];

        foreach ($projectTemplates as $template) {
            $this->createProjectFromTemplate($template);
        }
    }

    protected function createProjectFromTemplate(array $t): void
    {
        $startDate = now()->subDays($t['days_ago']);
        $deadline = $startDate->copy()->addDays($t['duration_days']);
        $isOverdue = isset($t['force_overdue']) || ($t['status'] !== 'Completed' && $deadline->isPast());

        $memberIds = [];
        foreach ($t['divisions'] as $divName) {
            if (isset($this->kepalaDivisi[$divName]) && $this->kepalaDivisi[$divName]) {
                $memberIds[] = $this->kepalaDivisi[$divName]->id;
            }
            foreach ($this->anggota[$divName] ?? [] as $anggota) {
                $memberIds[] = $anggota->id;
            }
        }
        $memberIds = array_unique($memberIds);

        $isScored = in_array($t['status'], ['Scoring', 'On Progress', 'Review', 'Revision', 'Approved', 'Upload', 'Completed']);
        $isReleased = in_array($t['status'], ['On Progress', 'Review', 'Revision', 'Approved', 'Upload', 'Completed']);
        $isApproved = in_array($t['status'], ['Approved', 'Upload', 'Completed']);
        $isCompleted = $t['status'] === 'Completed';

        $project = Project::create([
            'name' => $t['name'],
            'start_date' => $startDate->toDateString(),
            'deadline' => $deadline->toDateString(),
            'status' => $t['status'],
            'is_overdue' => $isOverdue,
            'brief' => 'Brief untuk project ' . $t['name'] . '. Dibuat sebagai data demo untuk testing Workdesk.',
            'reference_link' => 'https://example.com/' . \Str::slug($t['name']),
            'completed_at' => $isCompleted ? $startDate->copy()->addDays($t['duration_days'])->toDateTimeString() : null,
            'released_at' => $isReleased ? $startDate->copy()->addDay()->toDateTimeString() : null,
            'scored_at' => $isScored ? $startDate->copy()->addHours(8)->toDateTimeString() : null,
            'approved_by_pembina_at' => $isApproved ? $startDate->copy()->addDays($t['duration_days'] - 2)->toDateTimeString() : null,
            'approved_by_ketua_at' => $isApproved ? $startDate->copy()->addDays($t['duration_days'] - 2)->addHours(2)->toDateTimeString() : null,
        ]);

        $project->users()->attach($memberIds);

        if ($t['task_count'] > 0) {
            $this->createTasksForProject($project, $t);
        }

        if ($t['status'] === 'Revision') {
            ProjectRevision::create([
                'project_id' => $project->id,
                'user_id' => $this->pembina->id,
                'note' => 'Perbaiki opening 5 detik pertama, tambahkan subtitle Bahasa Indonesia, dan perbaiki audio mix.',
            ]);
        }

        if ($t['status'] === 'Completed' && rand(0, 1)) {
            ProjectRevision::create([
                'project_id' => $project->id,
                'user_id' => $this->ketuaMedia->id,
                'note' => 'Revisi minor pada timestamp 2:15 — sudah diperbaiki.',
                'resolved_at' => $startDate->copy()->addDays($t['duration_days'] - 3)->toDateTimeString(),
                'created_at' => $startDate->copy()->addDays($t['duration_days'] - 4)->toDateTimeString(),
                'updated_at' => $startDate->copy()->addDays($t['duration_days'] - 3)->toDateTimeString(),
            ]);
        }
    }

    protected function createTasksForProject(Project $project, array $t): void
    {
        $taskTitles = [
            'Membuat konsep & script',
            'Persiapan alat & lokasi',
            'Shooting footage utama',
            'Shooting footage tambahan (B-roll)',
            'Backup & seleksi footage',
            'Editing kasar (rough cut)',
            'Editing halus (fine cut)',
            'Color grading',
            'Audio mixing & SFX',
            'Review internal tim',
            'Export & upload',
            'Publikasi & distribusi',
        ];

        $startDate = Carbon::parse($project->start_date);
        $totalTasks = $t['task_count'];
        $isCompleted = $t['status'] === 'Completed';
        $isScored = in_array($t['status'], ['Scoring', 'On Progress', 'Review', 'Revision', 'Approved', 'Upload', 'Completed']);
        $isReleased = in_array($t['status'], ['On Progress', 'Review', 'Revision', 'Approved', 'Upload', 'Completed']);

        $allMembers = [];
        if ($this->kepalaDivisi['Lapangan']) $allMembers[] = $this->kepalaDivisi['Lapangan'];
        if ($this->kepalaDivisi['Editing']) $allMembers[] = $this->kepalaDivisi['Editing'];
        if ($this->kepalaDivisi['Broadcast']) $allMembers[] = $this->kepalaDivisi['Broadcast'];
        if ($this->kepalaDivisi['AI Research']) $allMembers[] = $this->kepalaDivisi['AI Research'];
        foreach ($this->anggota as $list) {
            foreach ($list as $a) $allMembers[] = $a;
        }

        if (empty($allMembers)) return;

        for ($i = 0; $i < $totalTasks; $i++) {
            $taskTitle = $taskTitles[$i % count($taskTitles)];
            $taskDate = $startDate->copy()->addDays(intval($i * ($t['duration_days'] / max($totalTasks, 1))));

            if ($isCompleted) {
                $taskCompleted = true;
            } elseif (in_array($t['status'], ['Review', 'Revision', 'Approved', 'Upload'])) {
                $taskCompleted = $i < $totalTasks - 1;
            } elseif ($t['status'] === 'On Progress') {
                $taskCompleted = $i < intval($totalTasks * 0.5);
            } else {
                $taskCompleted = false;
            }

            $pic = $allMembers[array_rand($allMembers)];

            $task = ProjectTask::create([
                'project_id' => $project->id,
                'assigned_to' => $pic->id,
                'title' => $taskTitle,
                'description' => 'Deskripsi task ' . $taskTitle . ' untuk project ' . $project->name,
                'task_date' => $taskDate->toDateString(),
                'status' => $taskCompleted ? 'Completed' : 'Pending',
                'completed_at' => $taskCompleted ? $taskDate->copy()->addHours(rand(2, 10))->toDateTimeString() : null,
                'weight' => $isScored ? rand(3, 10) : 0,
                'is_approved' => $isReleased ? true : false,
                'scored_at' => $isScored ? $startDate->copy()->addHours(8)->toDateTimeString() : null,
            ]);

            if ($taskCompleted) {
                $contributors = [$pic->id];
                $extraCount = rand(0, 2);
                for ($j = 0; $j < $extraCount; $j++) {
                    $extra = $allMembers[array_rand($allMembers)];
                    $contributors[] = $extra->id;
                }
                $task->contributors()->sync(array_unique($contributors));
            }
        }
    }

    protected function createContents(): void
    {
        $this->command->info('📦 Membuat contents...');

        $projects = Project::all();
        if ($projects->isEmpty()) return;

        $types = ['Reels', 'Poster', 'Feed', 'Carousel', 'Story', 'Video', 'Photo'];
        $platforms = [
            ['Instagram'],
            ['TikTok'],
            ['YouTube'],
            ['Instagram', 'TikTok'],
            ['Instagram', 'Facebook'],
            ['Instagram', 'TikTok', 'YouTube'],
        ];
        $uploadTypes = ['raw', 'content_stock', 'activity'];
        $categories = ['DAK', 'DOK', 'MEM', 'PSB'];

        for ($i = 0; $i < 25; $i++) {
            $project = $projects->random();
            $uploadType = $uploadTypes[array_rand($uploadTypes)];
            $prefix = $categories[array_rand($categories)];
            $contentCode = $prefix . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

            $statusRoll = rand(1, 10);
            if ($statusRoll <= 5) $status = 'Ready';
            elseif ($statusRoll <= 8) $status = 'Uploaded';
            else $status = 'Archived';

            Content::create([
                'content_code' => $contentCode,
                'project_id' => $project->id,
                'upload_type' => $uploadType,
                'target_division' => $uploadType === 'raw' ? 'Editing' : null,
                'title' => 'Konten ' . $project->name . ' #' . ($i + 1),
                'type' => $types[array_rand($types)],
                'category' => $uploadType === 'content_stock' ? $categories[array_rand($categories)] : null,
                'content_type' => $uploadType === 'content_stock' ? 'Reels' : null,
                'platform' => $platforms[array_rand($platforms)],
                'file_url' => 'https://drive.google.com/file/d/demo-' . uniqid(),
                'caption' => 'Caption konten demo #' . ($i + 1) . ' untuk ' . $project->name,
                'status' => $status,
                'uploaded_at' => $status === 'Uploaded' ? now()->subDays(rand(1, 30))->toDateTimeString() : null,
                'broadcast_filled_at' => ($uploadType === 'content_stock' && $status !== 'Ready') ? now()->subDays(rand(1, 20))->toDateTimeString() : null,
                'created_at' => now()->subDays(rand(1, 90)),
            ]);
        }
    }

    protected function createMeetings(): void
    {
        $this->command->info('📅 Membuat rapat...');

        $allUserIds = User::pluck('id')->toArray();

        // Ambil data project (dipisah, tidak pakai static chained method)
        $completedProject = Project::where('status', 'Completed')->first();
        $onProgressProject = Project::where('status', 'On Progress')->first();

        $completedName = $completedProject ? $completedProject->name : 'Project X';
        $onProgressName = $onProgressProject ? $onProgressProject->name : 'Project Y';

        for ($i = 0; $i < 3; $i++) {
            $meetingDate = now()->subWeeks(3 - $i);

            Meeting::create([
                'title' => 'Rapat Pekanan Divisi Media — ' . $meetingDate->translatedFormat('d M Y'),
                'meeting_date' => $meetingDate->toDateString(),
                'start_time' => '08:00:00',
                'end_time' => '09:30:00',
                'location' => 'Ruang Media PP Hubbul Khoir',
                'attendees' => $allUserIds,
                'agenda' => "1. Review project aktif\n2. Update progress divisi\n3. Rencana pekan depan\n4. Lain-lain",
                'discussion' => 'Pembahasan lengkap rapat pekanan. Semua divisi melaporkan progress. Beberapa kendala dibahas dan dicarikan solusi bersama.',
                'decisions' => [
                    ['decision' => 'Approve project ' . $completedName],
                    ['decision' => 'Rekrut 1 anggota tambahan untuk Tim Editing'],
                    ['decision' => 'Update deadline project ' . $onProgressName],
                ],
                'action_items' => [
                    ['task' => 'Koordinasi rekrut anggota Editing', 'assigned_to' => $this->ketuaMedia->id, 'deadline' => now()->addDays(7)->toDateString(), 'status' => 'pending'],
                    ['task' => 'Update deadline di sistem', 'assigned_to' => $this->pembina->id, 'deadline' => now()->addDays(2)->toDateString(), 'status' => 'done'],
                    ['task' => 'Follow up Tim Lapangan', 'assigned_to' => ($this->kepalaDivisi['Lapangan'] ? $this->kepalaDivisi['Lapangan']->id : $this->pembina->id), 'deadline' => now()->addDays(3)->toDateString(), 'status' => 'pending'],
                ],
                'notes' => 'Rapat berjalan lancar, semua divisi hadir.',
                'created_by' => $this->ketuaMedia->id,
                'status' => 'published',
                'published_at' => $meetingDate->copy()->addHours(10)->toDateTimeString(),
                'created_at' => $meetingDate,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $meetingDate = now()->addDays(2 + ($i * 7));

            Meeting::create([
                'title' => 'Rapat Pekanan Divisi Media — ' . $meetingDate->translatedFormat('d M Y'),
                'meeting_date' => $meetingDate->toDateString(),
                'start_time' => '08:00:00',
                'end_time' => '09:30:00',
                'location' => $i === 0 ? 'Ruang Media PP Hubbul Khoir' : 'Zoom Meeting',
                'attendees' => $allUserIds,
                'agenda' => "1. Update project Q4\n2. Review performa divisi\n3. Rencana akhir tahun\n4. Lain-lain",
                'discussion' => null,
                'decisions' => null,
                'action_items' => null,
                'notes' => null,
                'created_by' => $this->ketuaMedia->id,
                'status' => 'draft',
                'created_at' => now()->subDay(),
            ]);
        }
    }

    protected function createAlerts(): void
    {
        $this->command->info('🔔 Membuat alerts...');

        $managers = User::whereIn('division_role', ['pembina', 'ketua_media'])->get();

        foreach ($managers as $manager) {
            WorkdeskAlert::create([
                'user_id' => $manager->id,
                'type' => 'system',
                'title' => 'Welcome to Workdesk',
                'message' => 'Workdesk siap digunakan. Semua statistik akan ter-refresh otomatis setiap 30 detik.',
                'priority' => 'low',
                'is_read' => false,
                'action_url' => route('workdesk'),
            ]);

            WorkdeskAlert::create([
                'user_id' => $manager->id,
                'type' => 'reminder',
                'title' => 'Demo Data Aktif',
                'message' => 'Sistem sedang dalam mode demo. Data yang ditampilkan adalah data dummy untuk testing.',
                'priority' => 'medium',
                'is_read' => false,
            ]);
        }
    }

    protected function createNotifications(): void
    {
        $this->command->info('📱 Membuat notification logs...');

        $projects = Project::take(10)->get();
        $users = User::whereNotNull('phone')->take(10)->get();

        $types = ['project_created', 'reminder_h3', 'reminder_h1', 'overdue'];

        foreach ($projects as $project) {
            foreach ($users as $user) {
                if (rand(0, 2) === 0) {
                    NotificationLog::create([
                        'user_id' => $user->id,
                        'project_id' => $project->id,
                        'type' => $types[array_rand($types)],
                        'phone' => $user->phone,
                        'message' => '[DEMO] Notifikasi untuk project ' . $project->name,
                        'status' => rand(0, 9) > 1 ? 'sent' : 'failed',
                        'sent_at' => now()->subDays(rand(1, 30))->toDateTimeString(),
                        'created_at' => now()->subDays(rand(1, 30)),
                    ]);
                }
            }
        }
    }
}