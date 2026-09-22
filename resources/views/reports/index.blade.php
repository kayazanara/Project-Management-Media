@extends('layouts.app')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-print mr-2"></i> {{ $title }}</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Parameter Laporan Berkala</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('reports.generate') }}" method="POST" target="_blank">
                @csrf

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><strong>Dari Tanggal</strong> <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="{{ $start_date }}" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label><strong>Sampai Tanggal</strong> <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" value="{{ $end_date }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Nama Penanggung Jawab (PJ Media)</strong> <span class="text-danger">*</span></label>
                    <input type="text" name="pj_name" class="form-control" value="Zevana Asyam Zahran" required>
                </div>

                <div class="form-group">
                    <label><strong>Mengetahui (Manajemen Bidang / Pimpinan)</strong> <span class="text-danger">*</span></label>
                    <input type="text" name="manager_name" class="form-control" value="Fauzan Pratama" required>
                </div>

                <button type="submit" class="btn btn-success btn-sm shadow-sm">
                    <i class="fas fa-file-alt mr-1"></i> Generate Laporan Performa
                </button>
            </form>
        </div>
    </div>
@endsection