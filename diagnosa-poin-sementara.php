<?php
// DIAGNOSA POIN & BOBOT (READ-ONLY, tidak mengubah apa pun)
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$T    = \App\Models\ProjectTask::class;
$U    = \App\Models\User::class;
$line = str_repeat('=', 76);

$window = function ($q, $start, $end) {
    $q->where(function ($w) use ($start, $end) {
        $w->whereBetween('task_date', [$start->toDateString(), $end->toDateString()])
          ->orWhere(function ($w2) use ($start, $end) {
              $w2->whereNull('task_date')->whereBetween('created_at', [$start, $end]);
          });
    });

    return $q;
};

$attr = function ($q, $userId) {
    $q->where(function ($w) use ($userId) {
        $w->whereHas('contributors', fn ($c) => $c->where('users.id', $userId))
          ->orWhere(function ($w2) use ($userId) {
              $w2->whereDoesntHave('contributors')->where('assigned_to', $userId);
          });
    });

    return $q;
};

echo "\n{$line}\n[0] DATABASE & STATUS PROJECT\n{$line}\n";
echo '   driver: ' . config('database.default') . "\n";
echo '   db    : ' . config('database.connections.' . config('database.default') . '.database') . "\n";

foreach (\App\Models\Project::select('status')->get()->groupBy('status') as $st => $rows) {
    echo sprintf("   project %-12s : %d\n", $st, $rows->count());
}

$total           = $T::count();
$berbobot        = $T::where('weight', '>', 0)->count();
$selesai         = $T::where('status', 'Completed')->count();
$selesaiBerbobot = $T::where('status', 'Completed')->where('weight', '>', 0)->count();

echo "\n{$line}\n[1] IKHTISAR TASK\n{$line}\n";
echo "   total task                 : {$total}\n";
echo "   punya bobot (>0)           : {$berbobot}\n";
echo "   status Completed           : {$selesai}\n";
echo "   Completed + ada bobot      : {$selesaiBerbobot}\n";
echo '   Completed TANPA bobot      : ' . ($selesai - $selesaiBerbobot) . "\n";

echo "\n{$line}\n[2] BOBOT TERSIMPAN: MASUK JENDELA PERIODE ATAU TIDAK?\n{$line}\n";

foreach ([7, 30, 90] as $d) {
    $start = now()->subDays($d - 1)->startOfDay();
    $end   = now()->endOfDay();

    $dalam = $window($T::where('weight', '>', 0), $start, $end)->count();

    echo sprintf(
        "   %2d hari (%s s/d %s) -> bobot>0 DI DALAM jendela = %d | DI LUAR = %d\n",
        $d,
        $start->toDateString(),
        $end->toDateString(),
        $dalam,
        $berbobot - $dalam
    );
}

echo "\n{$line}\n[3] TASK SELESAI TAPI BOBOT 0 (penyebab poin 0) — 10 terbaru\n{$line}\n";
echo sprintf("   %-30s %-14s %-11s %s\n", 'TASK', 'PROJECT', 'TGL RENCANA', 'STATUS PROJECT / DIBUAT');

foreach ($T::with('project:id,name,status,released_at')
    ->where('status', 'Completed')->where('weight', '<=', 0)
    ->orderByDesc('completed_at')->limit(10)->get() as $t) {
    $catatan = optional($t->project)->status ?? '-';
    $catatan .= ', dibuat ' . ($t->created_at ? $t->created_at->toDateString() : '-');

    if (optional($t->project)->released_at && $t->created_at && $t->created_at->gt($t->project->released_at)) {
        $catatan .= ' <== DIBUAT SETELAH RILIS';
    }

    echo sprintf(
        "   %-30s %-14s %-11s %s\n",
        mb_substr($t->title, 0, 29),
        mb_substr(optional($t->project)->name ?? '-', 0, 13),
        $t->task_date ? $t->task_date->toDateString() : '-',
        $catatan
    );
}

echo "\n{$line}\n[4] PER ANGGOTA (periode 30 hari)\n{$line}\n";
echo "   skrg  = poin dengan jendela tgl RENCANA (kode yang jalan sekarang)\n";
echo "   alt   = poin kalau jendelanya pakai tgl SELESAI (completed_at)\n\n";
echo sprintf("   %-24s %8s %8s %9s %9s\n", 'NAMA', 'skrg', 'alt', 'selesai', 'bobot>0');

$start30 = now()->subDays(29)->startOfDay();
$end30   = now()->endOfDay();

foreach ($U::where('status', 'Aktif')->orderBy('name')->get() as $u) {
    $skrg = 0;
    $nSel = 0;
    $nBob = 0;

    $tasks = $attr($T::with('contributors:id'), $u->id);
    $tasks = $window($tasks, $start30, $end30)->get();

    foreach ($tasks as $t) {
        $ids = $t->contributors->pluck('id')->toArray();
        if (empty($ids) && $t->assigned_to) {
            $ids = [(int) $t->assigned_to];
        }
        if (empty($ids)) {
            continue;
        }

        if ($t->weight > 0) {
            $nBob++;
        }

        if ($t->status === 'Completed') {
            $nSel++;
            $skrg += ((float) $t->weight) / count($ids);
        }
    }

    $alt = 0;

    $altTasks = $attr($T::with('contributors:id'), $u->id)
        ->where('status', 'Completed')
        ->whereBetween('completed_at', [$start30, $end30])
        ->get();

    foreach ($altTasks as $t) {
        $ids = $t->contributors->pluck('id')->toArray();
        if (empty($ids) && $t->assigned_to) {
            $ids = [(int) $t->assigned_to];
        }
        if (empty($ids)) {
            continue;
        }

        $alt += ((float) $t->weight) / count($ids);
    }

    echo sprintf(
        "   %-24s %8s %8s %9d %9d\n",
        mb_substr($u->name, 0, 23),
        round($skrg, 1),
        round($alt, 1),
        $nSel,
        $nBob
    );
}

echo "\n{$line}\n[5] 12 TASK BERBOBOT TERAKHIR (cek apakah bobotmu benar-benar tersimpan)\n{$line}\n";
echo sprintf("   %-26s %-12s %7s %-11s %-11s %s\n", 'TASK', 'PROJECT', 'BOBOT', 'RENCANA', 'SELESAI', 'STATUS');

foreach ($T::with('project:id,name,status')
    ->where('weight', '>', 0)->orderByDesc('scored_at')->limit(12)->get() as $t) {
    echo sprintf(
        "   %-26s %-12s %7s %-11s %-11s %s\n",
        mb_substr($t->title, 0, 25),
        mb_substr(optional($t->project)->name ?? '-', 0, 11),
        $t->weight,
        $t->task_date ? $t->task_date->toDateString() : '-',
        $t->completed_at ? $t->completed_at->toDateString() : '-',
        $t->status
    );
}

echo "\n{$line}\nSELESAI (tidak ada data yang diubah)\n{$line}\n";
