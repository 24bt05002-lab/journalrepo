@extends('layouts.main')

@section('title', 'Selamat Datang - JournalRepo')

@section('content')
<div class="py-4">
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="badge bg-blue-lt text-primary fw-semibold px-3 py-2 mb-3">
                <i class="ti ti-shield-lock me-1"></i> Akses Terhad Kepada Ahli Berdaftar
            </span>

            <h1 class="display-6 fw-bold mb-3">
                Sistem Repositori Artikel Jurnal Akademik
            </h1>

            <p class="text-muted mx-auto mb-4" style="max-width: 650px; line-height: 1.7;">
                Platform pengurusan, carian pintar, dan arkib penerbitan ilmiah berasaskan OJS.
                Sila log masuk atau daftar akaun baharu untuk mengakses koleksi penuh artikel, membaca abstrak, serta memuat turun fail PDF penyelidikan.
            </p>

            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4">
                    <i class="ti ti-login me-2"></i> Log Masuk Sekarang
                </a>

                @if(Route::has('register'))
                    <a href="{{ route('register') }}" class="btn btn-outline-primary btn-lg px-4">
                        <i class="ti ti-user-plus me-2"></i> Daftar Akaun
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="avatar bg-blue-lt text-primary mb-3">
                        <i class="ti ti-books fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Koleksi Penerbitan Berpusat</h4>
                    <p class="text-muted small mb-0">
                        Menghimpunkan artikel jurnal akademik pelbagai bidang lengkap dengan metadata penulis, tahun penerbitan, dan kata kunci.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="avatar bg-azure-lt text-azure mb-3">
                        <i class="ti ti-file-type-pdf fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Akses Teks Penuh & OJS</h4>
                    <p class="text-muted small mb-0">
                        Pengguna berdaftar boleh pratonton dokumen PDF secara terus, memuat turun salinan, atau merujuk pautan sumber asal OJS.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="avatar bg-purple-lt text-purple mb-3">
                        <i class="ti ti-robot fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Carian Pintar & Pembantu AI</h4>
                    <p class="text-muted small mb-0">
                        Dilengkapi penapis penyelidik, analitik penerbitan, penanda buku (<em>bookmark</em>), serta pembantu AI untuk ringkasan kajian.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection