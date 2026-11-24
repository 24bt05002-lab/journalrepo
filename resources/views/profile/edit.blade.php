@extends('layouts.main')

@section('title', 'Profil & Kata Laluan')

@section('content')
<div class="page-header d-print-none mb-3">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Tetapan Akaun</div>
            <h2 class="page-title">Profil & Keselamatan</h2>
        </div>
    </div>
</div>

<div class="row row-cards">
    <!-- 1. Maklumat Profil -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-user me-2 text-primary"></i> Maklumat Profil
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Kemas kini nama paparan dan alamat e-mel rasmi akaun anda.
                </p>

                <form method="post" action="{{ route('profile.update') }}">
                    @csrf
                    @method('patch')

                    <div class="mb-3">
                        <label class="form-label required">Nama Penuh</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" required autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Alamat E-mel</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Perubahan
                        </button>

                        @if (session('status') === 'profile-updated')
                            <span class="text-success small fw-semibold">
                                <i class="ti ti-check me-1"></i> Disimpan.
                            </span>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. Tukar Kata Laluan -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-lock me-2 text-azure"></i> Kemas Kini Kata Laluan
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Pastikan akaun anda menggunakan kata laluan yang panjang dan selamat.
                </p>

                <form method="post" action="{{ route('password.update') }}">
                    @csrf
                    @method('put')

                    <div class="mb-3">
                        <label class="form-label required">Kata Laluan Semasa</label>
                        <input type="password" name="current_password"
                               class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                               autocomplete="current-password">
                        @error('current_password', 'updatePassword')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Kata Laluan Baharu</label>
                        <input type="password" name="password"
                               class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                               autocomplete="new-password">
                        @error('password', 'updatePassword')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Sahkan Kata Laluan Baharu</label>
                        <input type="password" name="password_confirmation"
                               class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                               autocomplete="new-password">
                        @error('password_confirmation', 'updatePassword')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-key me-1"></i> Tukar Kata Laluan
                        </button>

                        @if (session('status') === 'password-updated')
                            <span class="text-success small fw-semibold">
                                <i class="ti ti-check me-1"></i> Kata laluan dikemas kini.
                            </span>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. Padam Akaun -->
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger-lt">
                <h3 class="card-title fw-bold text-danger">
                    <i class="ti ti-alert-triangle me-2"></i> Padam Akaun Secara Kekal
                </h3>
            </div>
            <div class="card-body">
                <p class="text-secondary small mb-3">
                    Setelah akaun anda dipadamkan, semua sumber dan data berkaitan akan dipadam secara kekal. Sila masukkan kata laluan anda untuk mengesahkan pemadaman akaun.
                </p>

                <form method="post" action="{{ route('profile.destroy') }}" class="row g-2 align-items-end"
                      onsubmit="return confirm('Adakah anda benar-benar pasti ingin memadam akaun ini secara kekal?');">
                    @csrf
                    @method('delete')

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Sahkan Kata Laluan</label>
                        <input type="password" name="password" placeholder="Masukkan kata laluan anda"
                               class="form-control @error('password', 'userDeletion') is-invalid @enderror" required>
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-danger">
                            <i class="ti ti-trash me-1"></i> Padam Akaun Saya
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection