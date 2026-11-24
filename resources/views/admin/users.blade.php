@extends('layouts.main')

@section('title', 'Pengurusan Pengguna - Journal Repository')

@section('content')
@php
    $searchTerm = trim((string) request('search', ''));
    $searchWords = array_values(array_unique(array_filter(
        preg_split('/\s+/u', $searchTerm) ?: [],
        fn ($w) => mb_strlen($w) >= 1
    )));

    $highlightTokens = $searchTerm !== ''
        ? array_values(array_unique(array_merge([$searchTerm], $searchWords)))
        : [];

    $highlightPattern = !empty($highlightTokens)
        ? implode('|', array_map(fn ($t) => preg_quote($t, '/'), $highlightTokens))
        : null;

    $highlight = function (?string $text) use ($highlightPattern): string {
        $text = (string) ($text ?? '');
        if ($text === '' || $highlightPattern === null) {
            return e($text);
        }

        $parts = preg_split('/(' . $highlightPattern . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return e($text);
        }

        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(?:' . $highlightPattern . ')$/iu', $part)) {
                $out .= '<mark style="background-color: #fef08a; color: #1e293b; padding: 0 3px; border-radius: 3px; font-weight: 600;">' . e($part) . '</mark>';
            } else {
                $out .= e($part);
            }
        }

        return $out;
    };

    $roleBadgeClass = function (?string $role): string {
        return match (strtolower((string) $role)) {
            'admin'      => 'bg-red-lt text-red fw-bold',
            'lecturer'   => 'bg-blue-lt text-blue fw-bold',
            'researcher' => 'bg-purple-lt text-purple fw-bold',
            'editor'     => 'bg-teal-lt text-teal fw-bold',
            default      => 'bg-secondary-lt text-secondary fw-bold',
        };
    };
@endphp

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Pengurusan Pengguna & Peranan</h2>
        <p class="text-muted small mb-0">
            Jumlah keseluruhan <strong>{{ $totalUsers ?? $users->total() }}</strong> akaun pengguna berdaftar dalam sistem.
        </p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">← Kembali ke Senarai Artikel</a>
</div>

<!-- KAD CARIAN & PENAPIS PENGGUNA -->
<div class="card mb-4">
    <div class="card-body">
        <form id="userFilterForm" action="{{ route('admin.users') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="liveUserSearchInput" class="form-label small text-muted mb-1">Carian Nama atau Alamat E-mel</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent">
                        <i id="userSearchIcon" class="ti ti-search text-muted"></i>
                        <span id="userSearchSpinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>
                    </span>
                    <input type="text"
                           id="liveUserSearchInput"
                           name="search"
                           class="form-control"
                           placeholder="Taip nama atau e-mel pengguna..."
                           value="{{ request('search') }}"
                           autocomplete="off">
                    <button type="button"
                            id="btnClearUserSearch"
                            class="btn btn-outline-secondary {{ request()->filled('search') ? '' : 'd-none' }}"
                            title="Padam carian">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Peranan (Role)</label>
                <select name="role" class="form-select js-user-auto-filter">
                    <option value="">Semua Peranan</option>
                    @foreach($availableRoles as $roleOption)
                        <option value="{{ $roleOption }}" @selected(request('role') === $roleOption)>
                            {{ ucfirst($roleOption) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Susunan</label>
                <select name="sort" class="form-select js-user-auto-filter">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>Daftar Terkini</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Daftar Terawal</option>
                    <option value="name" @selected(request('sort') === 'name')>Nama (A - Z)</option>
                    @if($hasArticlesRelation ?? false)
                        <option value="articles" @selected(request('sort') === 'articles')>Paling Banyak Artikel</option>
                    @endif
                </select>
            </div>
        </form>

        <!-- TAB PENAPIS PANTAS PERANAN -->
        <div id="userFilterTabsContainer" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.users') }}"
                   class="btn btn-sm {{ !request()->filled('role') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Semua Pengguna ({{ $totalUsers ?? $users->total() }})
                </a>

                @foreach($availableRoles as $roleTab)
                    @if(isset($roleCounts[$roleTab]) || in_array($roleTab, ['admin', 'lecturer', 'user'], true))
                        <a href="{{ route('admin.users', ['role' => $roleTab]) }}"
                           class="btn btn-sm {{ request('role') === $roleTab ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ ucfirst($roleTab) }} ({{ $roleCounts[$roleTab] ?? 0 }})
                        </a>
                    @endif
                @endforeach
            </div>

            @if(request()->hasAny(['search', 'role']) || request('sort', 'latest') !== 'latest')
                <a href="{{ route('admin.users') }}" class="btn btn-sm btn-link text-danger text-decoration-none">
                    <i class="ti ti-refresh me-1"></i> Set Semula Penapis
                </a>
            @endif
        </div>
    </div>
</div>

<!-- KONTENA JADUAL PENGGUNA (DIKEMAS KINI SECARA LIVE) -->
<div id="usersResultsContainer">
    @if($searchTerm !== '')
        <div class="d-flex align-items-center justify-content-between text-muted small mb-3 px-1">
            <span>
                Menunjukkan <strong>{{ $users->total() }}</strong> pengguna sepadan dengan
                <mark style="background-color: #fef08a; color: #1e293b; padding: 0 4px; border-radius: 3px; font-weight: 600;">{{ $searchTerm }}</mark>
            </span>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>E-mel</th>
                        <th>Peranan Semasa</th>
                        @if($hasArticlesRelation ?? false)
                            <th class="text-center">Artikel</th>
                        @endif
                        <th>Tarikh Daftar</th>
                        <th style="min-width: 240px;">Tukar Peranan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar-sm rounded-circle bg-blue-lt text-primary fw-bold">
                                        {{ strtoupper(mb_substr($u->name ?? 'U', 0, 1)) }}
                                    </span>
                                    <div>
                                        <div class="fw-bold">
                                            {!! $highlight($u->name) !!}
                                            @if(Auth::id() === $u->id)
                                                <span class="badge bg-green-lt ms-1">Anda</span>
                                            @endif
                                        </div>
                                        <div class="text-muted small">ID #{{ $u->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-secondary">
                                {!! $highlight($u->email) !!}
                            </td>
                            <td>
                                <span class="badge {{ $roleBadgeClass($u->role ?? 'user') }}">
                                    {{ strtoupper($u->role ?? 'USER') }}
                                </span>
                            </td>
                            @if($hasArticlesRelation ?? false)
                                <td class="text-center">
                                    <span class="badge bg-azure-lt">{{ $u->articles_count ?? 0 }}</span>
                                </td>
                            @endif
                            <td class="text-muted small">
                                {{ $u->created_at ? $u->created_at->format('d M Y') : '-' }}
                            </td>
                            <td>
                                @if(Auth::id() === $u->id)
                                    <span class="text-muted small fst-italic">Akaun semasa (tidak boleh diubah)</span>
                                @else
                                    <form action="{{ route('admin.users.updateRole', $u) }}" method="POST" class="d-flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="form-select form-select-sm">
                                            @foreach($availableRoles as $rOpt)
                                                <option value="{{ $rOpt }}" @selected(strtolower((string) ($u->role ?? 'user')) === strtolower($rOpt))>
                                                    {{ ucfirst($rOpt) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            Simpan
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($hasArticlesRelation ?? false) ? 6 : 5 }}" class="text-center py-5 text-muted">
                                <i class="ti ti-users-off fs-1 d-block mb-2"></i>
                                Tiada pengguna ditemui berdasarkan carian atau penapis semasa.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('userFilterForm');
    const searchInput = document.getElementById('liveUserSearchInput');
    const clearBtn = document.getElementById('btnClearUserSearch');
    const searchIcon = document.getElementById('userSearchIcon');
    const searchSpinner = document.getElementById('userSearchSpinner');
    const resultsContainer = document.getElementById('usersResultsContainer');
    const tabsContainer = document.getElementById('userFilterTabsContainer');

    if (!form || !searchInput || !resultsContainer) return;

    let debounceTimer = null;
    let activeController = null;

    function setLoading(loading) {
        if (loading) {
            searchIcon?.classList.add('d-none');
            searchSpinner?.classList.remove('d-none');
            resultsContainer.style.opacity = '0.6';
        } else {
            searchIcon?.classList.remove('d-none');
            searchSpinner?.classList.add('d-none');
            resultsContainer.style.opacity = '1';
        }
    }

    function toggleClearButton() {
        if (!clearBtn) return;
        if (searchInput.value.trim() !== '') {
            clearBtn.classList.remove('d-none');
        } else {
            clearBtn.classList.add('d-none');
        }
    }

    async function performLiveUserSearch(customUrl = null) {
        if (activeController) {
            activeController.abort();
        }
        activeController = new AbortController();

        let targetUrl = customUrl;
        if (!targetUrl) {
            const urlObj = new URL(form.action, window.location.origin);
            const formData = new FormData(form);
            for (const [key, value] of formData.entries()) {
                if (String(value).trim() !== '') {
                    urlObj.searchParams.set(key, String(value).trim());
                }
            }
            targetUrl = urlObj.toString();
        }

        setLoading(true);

        try {
            const response = await fetch(targetUrl, {
                signal: activeController.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            });

            if (!response.ok) {
                throw new Error('Ralat pelayan semasa menapis pengguna.');
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');

            const newResults = doc.getElementById('usersResultsContainer');
            if (newResults) {
                resultsContainer.innerHTML = newResults.innerHTML;
            }

            const newTabs = doc.getElementById('userFilterTabsContainer');
            if (newTabs && tabsContainer) {
                tabsContainer.innerHTML = newTabs.innerHTML;
            }

            window.history.replaceState({}, '', targetUrl);
        } catch (err) {
            if (err.name === 'AbortError') return;
            form.submit();
        } finally {
            setLoading(false);
        }
    }

    searchInput.addEventListener('input', function () {
        toggleClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => performLiveUserSearch(), 300);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        performLiveUserSearch();
    });

    form.querySelectorAll('.js-user-auto-filter').forEach(select => {
        select.addEventListener('change', function () {
            clearTimeout(debounceTimer);
            performLiveUserSearch();
        });
    });

    clearBtn?.addEventListener('click', function () {
        searchInput.value = '';
        toggleClearButton();
        searchInput.focus();
        clearTimeout(debounceTimer);
        performLiveUserSearch();
    });

    resultsContainer.addEventListener('click', function (e) {
        const pageLink = e.target.closest('.pagination a');
        if (pageLink && pageLink.href) {
            e.preventDefault();
            performLiveUserSearch(pageLink.href);
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
})();
</script>
@endsection