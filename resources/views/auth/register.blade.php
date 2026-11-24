<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akaun — JournalRepo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            min-height: 100vh;
        }
        .auth-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        }
    </style>
</head>
<body class="d-flex flex-column justify-content-center py-4">
    <div class="container container-tight">
        <div class="text-center mb-4">
            <a href="{{ route('home') }}" class="text-white text-decoration-none d-inline-flex align-items-center gap-2">
                <span class="avatar avatar-md bg-primary text-white rounded-3 shadow"><i class="ti ti-book-2 fs-2"></i></span>
                <span class="fw-bold fs-2" style="letter-spacing: -0.4px;">JournalRepo</span>
            </a>
        </div>

        <div class="card auth-card">
            <div class="card-body p-4 p-sm-5">
                <h2 class="h2 text-center fw-bold mb-1">Cipta Akaun Baharu</h2>
                <p class="text-muted text-center small mb-4">Sertai repositori penerbitan & penyelidikan digital</p>

                <form method="POST" action="{{ route('register') }}" autocomplete="off">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label required">Nama Penuh</label>
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-user"></i></span>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="Nama penuh beserta gelaran" required autofocus>
                        </div>
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Alamat E-mel</label>
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-mail"></i></span>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   placeholder="nama@institusi.edu.my" required>
                        </div>
                        @error('email')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Kata Laluan</label>
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-lock"></i></span>
                            <input type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="Minimum 8 aksara" required>
                        </div>
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label required">Sahkan Kata Laluan</label>
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-lock-check"></i></span>
                            <input type="password" name="password_confirmation"
                                   class="form-control"
                                   placeholder="Taip semula kata laluan" required>
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="ti ti-user-plus me-2"></i> Daftar Akaun
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center text-white-50 small mt-4">
            Sudah mempunyai akaun?
            <a href="{{ route('login') }}" class="text-white fw-semibold text-decoration-underline ms-1">Log Masuk di Sini</a>
            <div class="mt-2">
                <a href="{{ route('home') }}" class="text-white-50 text-decoration-none">
                    <i class="ti ti-arrow-left me-1"></i> Kembali ke Laman Utama
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
</body>
</html>