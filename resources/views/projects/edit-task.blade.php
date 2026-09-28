@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-2"></i> {{ $title }}</h1>
        <a href="{{ route('projects.show', $project->id) }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Kembali ke Detail Project
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Form Edit Task: {{ $project->name }}</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('tasks.update', $task->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="form-group">
                    <label><strong>Judul Task / Checklist</strong> <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $task->title) }}" required>
                </div>

                <div class="form-group">
                    <label><strong>Penanggung Jawab (PIC)</strong></label>
                    <select name="assigned_to" class="form-control">
                        <option value="">-- Pilih Anggota --</option>
                        @foreach($project->users as $member)
                            <option value="{{ $member->id }}" {{ $task->assigned_to == $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label><strong>Target Tanggal</strong></label>
                    <input type="date" name="task_date" class="form-control" value="{{ old('task_date', $task->task_date?->format('Y-m-d')) }}">
                </div>

                {{-- ⚡ FASE 2: bobot/poin task bisa diubah kapan saja di sini --}}
                <div class="form-group">
                    <label><strong>Bobot / Poin Task</strong></label>
                    <input type="number" name="weight" class="form-control" min="0" value="{{ old('weight', $task->weight ?? 0) }}">
                    <small class="text-muted">Poin untuk anggota saat task ini selesai (dibagi rata ke kontributor). 0 = tanpa poin.</small>
                </div>

                <div class="form-group">
                    <label><strong>Deskripsi / Catatan Tambahan</strong></label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $task->description) }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Perbarui Task</button>
                <a href="{{ route('projects.show', $project->id) }}" class="btn btn-secondary btn-sm">Batal</a>
            </form>
        </div>
    </div>
@endsection