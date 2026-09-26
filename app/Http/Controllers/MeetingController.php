<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\User;
use App\Services\WhatsappNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeetingController extends Controller
{
    // ============================================================
    // READ (semua authenticated user)
    // ============================================================

    public function index(Request $request)
    {
        $query = Meeting::with('creator');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($x) use ($q) {
                $x->where('title', 'like', "%{$q}%")
                  ->orWhere('agenda', 'like', "%{$q}%")
                  ->orWhere('location', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status') && in_array($request->status, ['draft', 'published'])) {
            $query->where('status', $request->status);
        }

        if ($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->month)) {
            [$y, $m] = explode('-', $request->month);
            $query->whereYear('meeting_date', $y)->whereMonth('meeting_date', $m);
        }

        $meetings = $query->orderBy('meeting_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('meetings.index', [
            'title'    => 'Rapat & Notulen',
            'meetings' => $meetings,
            'filters'  => $request->only(['search', 'status', 'month']),
        ]);
    }

    public function show($id)
    {
        $meeting = Meeting::with('creator')->findOrFail($id);

        return view('meetings.show', [
            'title'     => $meeting->title,
            'meeting'   => $meeting,
            'attendees' => $meeting->attendee_users,
            'canEdit'   => Auth::user()->isManager(),
        ]);
    }

    // ============================================================
    // WRITE (Pembina + Ketua Media)
    // ============================================================

    public function create()
    {
        return view('meetings.form', [
            'title'   => 'Buat Rapat',
            'meeting' => new Meeting([
                'meeting_date' => now()->toDateString(),
                'start_time'   => '08:00',
                'end_time'     => '10:00',
                'status'       => 'draft',
            ]),
            'users' => User::orderBy('name')->get(),
            'mode'  => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateMeeting($request);
        $data['created_by'] = Auth::id();
        $data['status']     = $request->input('status', 'draft');

        if ($data['status'] === 'published') {
            $data['published_at'] = now();
        }

        $meeting = Meeting::create($data);

        if ($meeting->status === 'published') {
            $this->notifyPublish($meeting);
            $this->notifyActionItems($meeting);
        }

        return redirect()
            ->route('meetings.show', $meeting->id)
            ->with('success', 'Notulen rapat berhasil disimpan.');
    }

    public function edit($id)
    {
        $meeting = Meeting::findOrFail($id);

        return view('meetings.form', [
            'title'   => 'Edit Rapat',
            'meeting' => $meeting,
            'users'   => User::orderBy('name')->get(),
            'mode'    => 'edit',
        ]);
    }

    public function update(Request $request, $id)
    {
        $meeting   = Meeting::findOrFail($id);
        $oldStatus = $meeting->status;

        $data           = $this->validateMeeting($request);
        $data['status'] = $request->input('status', $meeting->status);

        if ($data['status'] === 'published' && !$meeting->published_at) {
            $data['published_at'] = now();
        }

        $meeting->update($data);

        if ($oldStatus !== 'published' && $meeting->status === 'published') {
            $this->notifyPublish($meeting);
            $this->notifyActionItems($meeting);
        }

        return redirect()
            ->route('meetings.show', $meeting->id)
            ->with('success', 'Notulen rapat berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $meeting = Meeting::findOrFail($id);
        $meeting->delete();

        return redirect()
            ->route('meetings.index')
            ->with('success', 'Rapat berhasil dihapus.');
    }

    public function publish($id)
    {
        $meeting = Meeting::findOrFail($id);

        if ($meeting->status === 'published') {
            return back()->with('info', 'Rapat sudah dipublikasikan sebelumnya.');
        }

        $meeting->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        $this->notifyPublish($meeting);
        $this->notifyActionItems($meeting);

        return back()->with('success', 'Notulen berhasil dipublikasikan & notifikasi terkirim.');
    }

    public function toggleActionItem(Request $request, $itemId)
    {
        $request->validate([
            'meeting_id' => 'required|integer|exists:meetings,id',
        ]);

        $meeting = Meeting::findOrFail($request->meeting_id);
        $items   = $meeting->action_items ?? [];

        if (!isset($items[$itemId])) {
            return response()->json([
                'success' => false,
                'message' => 'Action item tidak ditemukan.',
            ], 404);
        }

        $user       = Auth::user();
        $isManager  = $user->isManager();
        $isAssignee = (int) ($items[$itemId]['assigned_to'] ?? 0) === (int) $user->id;

        if (!$isManager && !$isAssignee) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak mengubah item ini.',
            ], 403);
        }

        $items[$itemId]['status'] = ($items[$itemId]['status'] ?? 'pending') === 'done'
            ? 'pending'
            : 'done';

        $meeting->action_items = $items;
        $meeting->save();

        return response()->json([
            'success'   => true,
            'status'    => $items[$itemId]['status'],
            'completed' => $meeting->completed_action_items,
            'total'     => $meeting->total_action_items,
        ]);
    }

    // ============================================================
    // EXPORT
    // ============================================================

    public function exportPdf($id)
    {
        $meeting = Meeting::with('creator')->findOrFail($id);

        return view('meetings.print', [
            'title'     => $meeting->title,
            'meeting'   => $meeting,
            'attendees' => $meeting->attendee_users,
            'autoPrint' => true,
        ]);
    }

    public function exportWord($id)
    {
        $meeting = Meeting::with('creator')->findOrFail($id);

        $html = view('meetings.print', [
            'title'     => $meeting->title,
            'meeting'   => $meeting,
            'attendees' => $meeting->attendee_users,
            'autoPrint' => false,
        ])->render();

        $safeName = preg_replace('/[^A-Za-z0-9\-_]+/', '-', $meeting->title);
        $filename = 'Notulen-' . trim($safeName, '-') . '.doc';

        return response($html)
            ->header('Content-Type', 'application/msword')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function validateMeeting(Request $request): array
    {
        $validated = $request->validate([
            'title'                        => 'required|string|max:255',
            'meeting_date'                 => 'required|date',
            'start_time'                   => 'required|date_format:H:i',
            'end_time'                     => 'required|date_format:H:i|after:start_time',
            'location'                     => 'nullable|string|max:255',
            'attendees'                    => 'nullable|array',
            'attendees.*'                  => 'integer|exists:users,id',
            'agenda'                       => 'nullable|string',
            'discussion'                   => 'nullable|string',
            'decisions'                    => 'nullable|array',
            'decisions.*.decision'         => 'nullable|string|max:1000',
            'action_items'                 => 'nullable|array',
            'action_items.*.task'          => 'nullable|string|max:1000',
            'action_items.*.assigned_to'   => 'nullable|integer|exists:users,id',
            'action_items.*.deadline'      => 'nullable|date',
            'notes'                        => 'nullable|string',
            'status'                       => 'nullable|in:draft,published',
        ]);

        // Bersihkan & normalisasi decisions
        if (!empty($validated['decisions'])) {
            $validated['decisions'] = array_values(array_filter(
                $validated['decisions'],
                fn($d) => !empty(trim($d['decision'] ?? ''))
            ));
        } else {
            $validated['decisions'] = [];
        }

        // Bersihkan & normalisasi action_items
        if (!empty($validated['action_items'])) {
            $clean = [];
            foreach ($validated['action_items'] as $item) {
                if (empty(trim($item['task'] ?? ''))) continue;

                $clean[] = [
                    'task'        => $item['task'],
                    'assigned_to' => $item['assigned_to'] ?? null,
                    'deadline'    => $item['deadline'] ?? null,
                    'status'      => $item['status'] ?? 'pending',
                ];
            }
            $validated['action_items'] = $clean;
        } else {
            $validated['action_items'] = [];
        }

        // Pastikan attendees array of int
        $validated['attendees'] = array_map('intval', $validated['attendees'] ?? []);

        return $validated;
    }

    private function notifyPublish(Meeting $meeting): void
    {
        $recipients = User::whereIn('division_role', ['pembina', 'ketua_media', 'kepala_divisi'])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        if ($recipients->isEmpty()) return;

        $notifier = app(WhatsappNotifier::class);
        $waktu    = substr($meeting->start_time, 0, 5) . ' - ' . substr($meeting->end_time, 0, 5);

        foreach ($recipients as $user) {
            $message = $notifier->meetingPublishedTemplate(
                $user->name,
                $meeting->title,
                $meeting->date_formatted,
                $waktu,
                $meeting->location ?? '-',
                $meeting->id
            );
            $notifier->sendAndLog($user, null, 'meeting_published', $message);
        }
    }

    private function notifyActionItems(Meeting $meeting): void
    {
        $items = $meeting->action_items ?? [];
        if (empty($items)) return;

        $assignedIds = collect($items)->pluck('assigned_to')->filter()->unique()->values();
        if ($assignedIds->isEmpty()) return;

        $users = User::whereIn('id', $assignedIds)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->keyBy('id');

        if ($users->isEmpty()) return;

        $notifier = app(WhatsappNotifier::class);

        foreach ($items as $item) {
            $uid = $item['assigned_to'] ?? null;
            if (!$uid || !isset($users[$uid])) continue;

            $user    = $users[$uid];
            $message = $notifier->meetingActionItemTemplate(
                $user->name,
                $meeting->title,
                $item['task'] ?? '-',
                $item['deadline'] ?? '-',
                $meeting->id
            );
            $notifier->sendAndLog($user, null, 'meeting_action_item', $message);
        }
    }
}