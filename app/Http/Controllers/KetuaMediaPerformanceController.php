<?php

namespace App\Http\Controllers;

use App\Models\KetuaMediaEvaluation;
use App\Models\User;
use App\Services\KetuaMediaPerformance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ⚡ FITUR BARU: "Kinerja Ketua Media" — menu KHUSUS PEMBINA MEDIA.
 *
 * Halaman ini menjawab satu pertanyaan: "Ketua Media ini benar-benar memastikan
 * semua tugas & project tiap divisi jalan lancar, atau tidak?"
 *
 * Isinya: skor 4 pilar, daftar kerjaan yang MENUNGGU tindakannya (bottleneck),
 * papan 4 divisi, tren 6 bulan, jejak kepemimpinan, dan catatan evaluasi
 * yang ditulis Pembina.
 *
 * Semua angka dihitung di App\Services\KetuaMediaPerformance (satu sumber).
 */
class KetuaMediaPerformanceController extends Controller
{
    public function __construct(private KetuaMediaPerformance $performa)
    {
    }

    // ============================================================
    // HALAMAN UTAMA
    // ============================================================

    public function index(Request $request)
    {
        $this->pastikanPembina();

        $days = (int) $request->input('days', 30);
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        // Daftar Ketua Media (siap kalau nanti lebih dari satu)
        $daftarKetua = User::where('division_role', 'ketua_media')
            ->orderBy('name')
            ->get();

        $ketua = null;
        if ($daftarKetua->isNotEmpty()) {
            $diminta = (int) $request->input('ketua', 0);
            $ketua   = $daftarKetua->firstWhere('id', $diminta) ?? $daftarKetua->first();
        }

        $start = now()->subDays($days - 1)->startOfDay();
        $end   = now()->endOfDay();

        // Dataset dimuat sekali → dipakai untuk evaluasi periode + tren 6 bulan
        $data    = $this->performa->dataset();
        $hasil   = $ketua
            ? $this->performa->evaluate($ketua, $start, $end, $data)
            : null;
        $tren    = $ketua
            ? $this->performa->trend($ketua, 6, $data)
            : ['label' => [], 'data' => []];

        // Riwayat catatan evaluasi
        $catatan = $ketua
            ? KetuaMediaEvaluation::with('evaluator:id,name')
                ->forUser($ketua->id)
                ->orderByDesc('created_at')
                ->take(20)
                ->get()
            : collect();

        return view('kinerja.ketua-media', [
            'title'       => 'Kinerja Ketua Media',
            'filterDays'  => $days,
            'startDate'   => $start,
            'endDate'     => $end,
            'daftarKetua' => $daftarKetua,
            'ketua'       => $ketua,
            'hasil'       => $hasil,
            'tren'        => $tren,
            'catatan'     => $catatan,
            'pilarInfo'   => KetuaMediaPerformance::PILAR,
            'bobot'       => KetuaMediaPerformance::BOBOT,
            'target'      => KetuaMediaPerformance::TARGET,
            'penalti'     => KetuaMediaPerformance::PENALTI,
        ]);
    }

    // ============================================================
    // CATATAN EVALUASI PEMBINA
    // ============================================================

    public function storeEvaluation(Request $request)
    {
        $this->pastikanPembina();

        $validated = $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'note'         => 'required|string|min:10|max:2000',
            'period_start' => 'nullable|date',
            'period_end'   => 'nullable|date|after_or_equal:period_start',
            'days'         => 'nullable|integer|in:7,30,90',
        ], [
            'note.required' => 'Catatan evaluasi wajib diisi.',
            'note.min'      => 'Catatan evaluasi minimal 10 karakter.',
            'period_end.after_or_equal' => 'Tanggal akhir periode tidak boleh sebelum tanggal mulai.',
        ]);

        // Pastikan yang dinilai memang Ketua Media (tidak bisa dipakai menilai peran lain)
        $target = User::where('id', $validated['user_id'])
            ->where('division_role', 'ketua_media')
            ->first();

        if (! $target) {
            return back()->with('error', 'Catatan evaluasi hanya untuk Ketua Media.');
        }

        // Periode: pakai tanggal yang diisi; kalau kosong, pakai periode yang sedang dilihat
        $days = (int) ($validated['days'] ?? 30);
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $start = ! empty($validated['period_start'])
            ? Carbon::parse($validated['period_start'])->startOfDay()
            : now()->subDays($days - 1)->startOfDay();

        $end = ! empty($validated['period_end'])
            ? Carbon::parse($validated['period_end'])->endOfDay()
            : now()->endOfDay();

        // ⚡ Snapshot skor dihitung di SERVER (bukan dikirim dari form) supaya
        // angka yang tersimpan tidak bisa dipalsukan dari sisi klien.
        $hitung = $this->performa->evaluate($target, $start, $end);

        KetuaMediaEvaluation::create([
            'user_id'      => $target->id,
            'evaluator_id' => Auth::id(),
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'score'        => $hitung['skor'],
            'rating'       => $hitung['label'],
            'note'         => $validated['note'],
        ]);

        return redirect()
            ->route('kinerja.ketuaMedia', ['ketua' => $target->id, 'days' => $days])
            ->with('success', 'Catatan evaluasi tersimpan (skor tercatat: ' . ($hitung['skor'] ?? '—') . ').');
    }

    public function destroyEvaluation($id)
    {
        $this->pastikanPembina();

        $catatan = KetuaMediaEvaluation::findOrFail($id);
        $userId  = $catatan->user_id;
        $catatan->delete();

        return redirect()
            ->route('kinerja.ketuaMedia', ['ketua' => $userId])
            ->with('success', 'Catatan evaluasi dihapus.');
    }

    // ============================================================
    // GUARD
    // ============================================================

    /**
     * Halaman ini HANYA untuk Pembina Media. Dijaga dua lapis: di route
     * (middleware divisi.access:pembina) dan di sini — supaya tetap aman
     * walau route-nya suatu saat diubah.
     */
    private function pastikanPembina(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isPembina()) {
            abort(403, 'Menu Kinerja Ketua Media hanya untuk Pembina Media.');
        }
    }
}
