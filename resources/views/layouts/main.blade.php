<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Journal Repository')</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        [data-bs-theme="light"] body { background-color: #f6f8fb; }
        .navbar-vertical, .top-navbar-dark { background: #0f172a; }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .article-card {
            position: relative;
            z-index: 1;
        }
        .article-card:hover,
        .article-card:focus-within {
            z-index: 100;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
            border-color: #cbd5e1;
        }
        .cite-box {
    background-color: var(--tblr-bg-surface-secondary);
    border: 1px solid var(--tblr-border-color);
    border-radius: 6px;
    padding: 12px;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--tblr-body-color) !important;
}

.cite-box pre,
.cite-box code {
    color: var(--tblr-body-color) !important;
    background: transparent !important;
    margin-bottom: 0;
    padding: 0;
}
        .guest-nav-link {
            position: relative;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .guest-nav-link::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 12px;
            right: 12px;
            height: 2px;
            background-color: #3b82f6;
            border-radius: 2px;
            transform: scaleX(0);
            transition: transform 0.2s ease;
        }
        .guest-nav-link:hover::after,
        .guest-nav-link.active::after {
            transform: scaleX(1);
        }
        .modal-backdrop {
            z-index: 1040 !important;
        }
        .modal {
            z-index: 1060 !important;
        }
        .avatar,
        .avatar.rounded-circle {
            flex-shrink: 0 !important;
            aspect-ratio: 1 / 1;
            border-radius: 50% !important;
        }
    </style>
    <script>
        const savedTheme = localStorage.getItem('jr-theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
    @stack('styles')
</head>
<body>
<div class="page">
    

    @guest
        <!-- 1. TOP NAVBAR (UNTUK LANDING PAGE / TETAMU) -->
        <header class="navbar navbar-expand-md d-print-none top-navbar-dark py-2 shadow-sm" data-bs-theme="dark">
            <div class="container-xl">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#guest-navbar-menu">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Logo -->
                <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-4 mb-0">
                    <a href="{{ route('home') }}" class="text-white text-decoration-none d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-primary text-white rounded-3 shadow-sm"><i class="ti ti-book-2"></i></span>
                        <span class="fw-bold fs-3" style="letter-spacing: -0.3px;">JournalRepo</span>
                    </a>
                </h1>

                <!-- Butang Kanan: Penukar Bahasa, Tema Gelap, Log Masuk & Daftar -->
                <div class="navbar-nav flex-row order-md-last align-items-center gap-2 ms-auto">
                    <!-- Penukar Bahasa BM / EN -->
                   

                    <button type="button" id="themeToggleBtn" class="btn btn-icon btn-outline-light btn-sm border-0" title="{{ __('Tukar Tema Gelap / Cerah') }}">
                        <i id="themeIcon" class="ti ti-moon"></i>
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light px-3 rounded-pill">
                        <i class="ti ti-login me-1"></i> {{ __('Log Masuk') }}
                    </a>
                    <a href="{{ route('register') }}" class="btn btn-sm btn-primary px-3 rounded-pill fw-semibold">
                        {{ __('Daftar Akaun') }}
                    </a>
                </div>

                <!-- Pautan Navigasi Tengah -->
                <div class="collapse navbar-collapse" id="guest-navbar-menu">
                    <ul class="navbar-nav gap-1">
                        <li class="nav-item">
                            <a class="nav-link guest-nav-link px-3 {{ request()->routeIs('home', 'dashboard') ? 'active text-white' : 'text-white-50' }}" href="{{ route('home') }}">
                                {{ __('Laman Utama') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
    @else
        <!-- 2. SIDEBAR KIRI (SELEPAS LOG MASUK) -->
        <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <h1 class="navbar-brand navbar-brand-autodark py-3">
                    <a href="{{ route('dashboard') }}" class="text-white text-decoration-none d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-primary text-white rounded"><i class="ti ti-book-2"></i></span>
                        <span class="fw-bold">JournalRepo</span>
                    </a>
                </h1>

                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-2">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard', 'home') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <span class="nav-link-icon"><i class="ti ti-list-details"></i></span>
                                <span class="nav-link-title">{{ __('Senarai Artikel') }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('authors.index') ? 'active' : '' }}" href="{{ route('authors.index') }}">
                                <span class="nav-link-icon"><i class="ti ti-user-search"></i></span>
                                <span class="nav-link-title">{{ __('Direktori Penulis') }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboards.analytics') ? 'active' : '' }}" href="{{ route('dashboards.analytics') }}">
                                <span class="nav-link-icon"><i class="ti ti-chart-bar"></i></span>
                                <span class="nav-link-title">{{ __('Analitik') }}</span>
                            </a>
                        </li>
                        @if(Auth::user()->canUpload())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('articles.create') ? 'active' : '' }}" href="{{ route('articles.create') }}">
                                    <span class="nav-link-icon"><i class="ti ti-cloud-upload"></i></span>
                                    <span class="nav-link-title">{{ __('Muat Naik Artikel') }}</span>
                                </a>
                            </li>
                        @endif
                        @if(Auth::user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">
                                    <span class="nav-link-icon"><i class="ti ti-users"></i></span>
                                    <span class="nav-link-title">{{ __('Urus Pengguna') }}</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </aside>
    @endguest

    <!-- KANDUNGAN UTAMA -->
    <div class="page-wrapper">
        @auth
            <!-- TOP HEADER PROFIL PENGGUNA & PENUKAR BAHASA -->
            <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none border-bottom">
                <div class="container-xl">
                    <div class="navbar-nav flex-row order-md-last ms-auto align-items-center gap-2">
                        <!-- Penukar Bahasa BM / EN -->
                        

                        <!-- Butang Tema Gelap / Cerah -->
                        <button type="button" id="themeToggleBtn" class="btn btn-icon btn-ghost-secondary btn-sm" title="{{ __('Tukar Tema Gelap / Cerah') }}">
                            <i id="themeIcon" class="ti ti-moon"></i>
                        </button>

                        <!-- Dropdown Profil Pengguna -->
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Buka menu pengguna">
                                <span class="avatar avatar-sm bg-primary-lt fw-bold">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <div class="d-none d-xl-block ps-2">
                                    <div class="fw-semibold">{{ Auth::user()->name }}</div>
                                    <div class="mt-1 small">
                                        @if(Auth::user()->isAdmin())
                                            <span class="badge bg-red-lt">Admin</span>
                                        @elseif(Auth::user()->canUpload())
                                            <span class="badge bg-blue-lt">Penyelidik</span>
                                        @else
                                            <span class="badge bg-secondary-lt">Pengguna</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <a href="{{ route('profile.edit') }}" class="dropdown-item">
                                    <i class="ti ti-user me-2 text-muted"></i> {{ __('Profil Saya') }}
                                </a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="ti ti-logout me-2"></i> {{ __('Log Keluar') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
        @endauth

        <div class="page-body">
            <div class="container-xl">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <div>{{ session('success') }}</div>
                        <a class="btn-close" data-bs-dismiss="alert"></a>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div>{{ session('error') }}</div>
                        <a class="btn-close" data-bs-dismiss="alert"></a>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

</div>

@auth
<!-- WIDGET FLOATING AI CHATBOT -->
<div id="repo-chatbot-wrapper" style="position: fixed; bottom: 24px; right: 24px; z-index: 1050;">
    <!-- Tingkap Chat -->
    <div id="repo-chat-window" class="card shadow-lg d-none mb-2" style="width: 360px; max-width: calc(100vw - 32px); border-radius: 14px; overflow: hidden;">
        <div class="card-header bg-primary text-white py-2 px-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="avatar avatar-sm rounded-circle bg-white text-primary fw-bold">
                    <i class="ti ti-robot"></i>
                </span>
                <div>
                    <div class="fw-bold small lh-1">{{ __('Pembantu AI Repositori') }}</div>
                    <small class="opacity-75" style="font-size: 11px;">{{ __('Dikuasakan oleh AI + Data Dalaman') }}</small>
                </div>
            </div>
            <button type="button" id="repo-chat-close" class="btn-close btn-close-white btn-sm" aria-label="Close"></button>
        </div>

        <div id="repo-chat-messages" class="card-body p-3" style="height: 320px; overflow-y: auto; background-color: #f8fafc; font-size: 13.5px;">
            <div class="mb-2">
                <div class="bg-white border rounded-3 p-2 shadow-sm d-inline-block text-dark" style="max-width: 88%;">
                    @if(app()->getLocale() === 'en')
                        Hi <strong>{{ Auth::user()->name }}</strong>! 👋 Ask me anything about articles, research summaries, authors, or statistics in this repository.
                    @else
                        Hai <strong>{{ Auth::user()->name }}</strong>! 👋 Tanya saya apa-apa tentang artikel, ringkasan kajian, penulis, atau statistik dalam repositori ini.
                    @endif
                </div>
            </div>
        </div>

        <form id="repo-chat-form" class="card-footer p-2 bg-white">
            <div class="input-group input-group-sm">
                <input type="text" id="repo-chat-input" class="form-control" placeholder="{{ __('Contoh: Cari artikel berkaitan AI...') }}" required autocomplete="off">
                <button type="submit" id="repo-chat-send" class="btn btn-primary">
                    <i class="ti ti-send"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Butang Bulat Terapung -->
    <div class="text-end">
        <button type="button" id="repo-chat-toggle" class="btn btn-primary rounded-circle shadow-lg d-inline-flex align-items-center justify-content-center" style="width: 54px; height: 54px;" title="{{ __('Tanya Pembantu AI') }}">
            <i class="ti ti-message-chatbot fs-2"></i>
        </button>
    </div>
</div>
@endauth

<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
<script>
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');

    function updateThemeIcon(theme) {
        if (!themeIcon) return;
        themeIcon.className = theme === 'dark' ? 'ti ti-sun text-warning' : 'ti ti-moon';
    }

    updateThemeIcon(document.documentElement.getAttribute('data-bs-theme'));

    themeToggleBtn?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('jr-theme', next);
        updateThemeIcon(next);
    });
</script>

@auth
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('repo-chat-toggle');
    const closeBtn = document.getElementById('repo-chat-close');
    const chatWindow = document.getElementById('repo-chat-window');
    const chatForm = document.getElementById('repo-chat-form');
    const chatInput = document.getElementById('repo-chat-input');
    const chatMessages = document.getElementById('repo-chat-messages');
    const sendBtn = document.getElementById('repo-chat-send');

    if (!toggleBtn) return;

    toggleBtn.addEventListener('click', () => {
        chatWindow.classList.toggle('d-none');
        if (!chatWindow.classList.contains('d-none')) chatInput.focus();
    });

    closeBtn.addEventListener('click', () => chatWindow.classList.add('d-none'));

    const formatReply = (text) => {
        return text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" class="text-primary fw-semibold text-decoration-underline">$1</a>')
            .replace(/\n/g, '<br>');
    };

    const appendMessage = (html, isUser = false) => {
        const wrapper = document.createElement('div');
        wrapper.className = isUser ? 'mb-2 text-end' : 'mb-2';
        wrapper.innerHTML = isUser
            ? `<div class="bg-primary text-white rounded-3 p-2 shadow-sm d-inline-block text-start" style="max-width: 85%;">${html}</div>`
            : `<div class="bg-white border rounded-3 p-2 shadow-sm d-inline-block text-start text-dark" style="max-width: 88%;">${html}</div>`;
        chatMessages.appendChild(wrapper);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return wrapper;
    };

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        appendMessage(message.replace(/</g, '&lt;'), true);
        chatInput.value = '';
        chatInput.disabled = true;
        sendBtn.disabled = true;

        const loadingEl = appendMessage('<span class="text-muted fst-italic">{{ __("Sedang menyemak repositori...") }}</span>', false);

        try {
            const res = await fetch("{{ route('chatbot.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message })
            });

            const data = await res.json();
            loadingEl.remove();
            appendMessage(formatReply(data.reply || 'Tiada jawapan diterima.'), false);
        } catch (err) {
            loadingEl.remove();
            appendMessage('Maaf, berlaku ralat sambungan ke pelayan.', false);
        } finally {
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
        }
    });
});
</script>
@endauth
@stack('scripts')
</body>
</html>