@extends('layouts.main')

@section('title', 'Senarai Artikel - Journal Repository')

@section('content')
@php
    $searchTerm = trim((string) request('search', ''));
    $searchWords = array_values(array_unique(array_filter(
        preg_split('/\s+/u', $searchTerm) ?: [],
        fn ($w) => mb_strlen($w) >= 1
    )));

    // Susun frasa penuh dahulu diikuti perkataan individu supaya padanan paling tepat diutamakan
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

    $highlightAbstract = function (?string $abstract) use ($searchTerm, $searchWords, $highlight): string {
        $raw = trim(preg_replace('/\s+/u', ' ', (string) ($abstract ?? '')));
        if ($raw === '') {
            return '';
        }

        $limit = 240;
        if ($searchTerm === '' || mb_strlen($raw) <= $limit) {
            return $highlight(\Illuminate\Support\Str::limit($raw, $limit));
        }

        // Cari kedudukan pertama kata carian supaya petikan abstrak memaparkan bahagian yang di-highlight
        $pos = mb_stripos($raw, $searchTerm);
        if ($pos === false) {
            foreach ($searchWords as $w) {
                $pos = mb_stripos($raw, $w);
                if ($pos !== false) {
                    break;
                }
            }
        }

        if ($pos === false || $pos < 160) {
            return $highlight(\Illuminate\Support\Str::limit($raw, $limit));
        }

        $start = max(0, $pos - 80);
        $snippet = ($start > 0 ? '...' : '') . mb_substr($raw, $start, $limit);
        if ($start + $limit < mb_strlen($raw)) {
            $snippet .= '...';
        }

        return $highlight($snippet);
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
        <h2 class="fw-bold mb-1">Repositori Artikel Jurnal</h2>
        <p class="text-muted small mb-0">
            Jumlah keseluruhan <strong>{{ $totalArticles ?? $articles->total() }}</strong> penerbitan akademik direkodkan.
        </p>
    </div>

    @auth
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('articles.exportCsv') }}" class="btn btn-outline-secondary">
                <i class="ti ti-file-spreadsheet me-1"></i> Eksport CSV
            </a>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
    <i class="ti ti-database-import me-1"></i> Import Pukal
</button>
            <a href="{{ route('articles.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Tambah Artikel
            </a>
        </div>
    @endauth
</div>

<div class="card mb-4">
    <div class="card-body">
        <form id="articleFilterForm" action="{{ route('dashboard') }}" method="GET" class="row g-2 align-items-end">
            @if(request()->filled('author'))
                <input type="hidden" name="author" value="{{ request('author') }}">
            @endif
            @if(request()->boolean('mine'))
                <input type="hidden" name="mine" value="1">
            @endif
            @if(request()->boolean('bookmarked'))
                <input type="hidden" name="bookmarked" value="1">
            @endif

            <div class="col-md-5">
                <label for="liveSearchInput" class="form-label small text-muted mb-1">Carian Tajuk / Penulis / Kata Kunci</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent">
                        <i id="searchIcon" class="ti ti-search text-muted"></i>
                        <span id="searchSpinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></span>
                    </span>
                    <input type="text"
                           id="liveSearchInput"
                           name="search"
                           class="form-control"
                           placeholder="Taip untuk mencari secara automatik..."
                           value="{{ request('search') }}"
                           autocomplete="off">
                    <button type="button"
                            id="btnClearSearch"
                            class="btn btn-outline-secondary {{ request()->filled('search') ? '' : 'd-none' }}"
                            title="Padam carian">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Kategori / Jurnal</label>
                <select name="category" class="form-select js-auto-filter">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Tahun</label>
                <select name="year" class="form-select js-auto-filter">
                    <option value="">Semua Tahun</option>
                    @foreach($years as $yr)
                        <option value="{{ $yr }}" @selected((string) request('year') === (string) $yr)>{{ $yr }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Susunan</label>
                <select name="sort" class="form-select js-auto-filter">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>Terkini Dimuat Naik</option>
                    <option value="year" @selected(request('sort') === 'year')>Tahun Penerbitan</option>
                    <option value="views" @selected(request('sort') === 'views')>Paling Banyak Paparan</option>
                    <option value="downloads" @selected(request('sort') === 'downloads')>Paling Banyak Muat Turun</option>
                </select>
            </div>
        </form>

        <div id="filterTabsContainer" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('dashboard') }}"
                   class="btn btn-sm {{ !request()->boolean('mine') && !request()->boolean('bookmarked') && !request()->filled('author') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    Semua Artikel ({{ $totalArticles ?? $articles->total() }})
                </a>

                @auth
                    <a href="{{ route('dashboard', ['mine' => 1]) }}"
                       class="btn btn-sm {{ request()->boolean('mine') ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="ti ti-user me-1"></i> Milik Saya ({{ $myArticlesCount ?? 0 }})
                    </a>

                    <a href="{{ route('dashboard', ['bookmarked' => 1]) }}"
                       class="btn btn-sm {{ request()->boolean('bookmarked') ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="ti ti-bookmark me-1"></i> Bookmark ({{ count($bookmarkedIds ?? []) }})
                    </a>
                @endauth
            </div>

            @if(request()->hasAny(['search', 'category', 'year', 'author', 'mine', 'bookmarked']) || request('sort', 'latest') !== 'latest')
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-link text-danger text-decoration-none">
                    <i class="ti ti-refresh me-1"></i> Set Semula Penapis
                </a>
            @endif
        </div>
    </div>
</div>

<!-- KONTENA HASIL CARIAN (DIKEMAS KINI SECARA LIVE TANPA GANGGU TAIPAN) -->
<div id="articlesResultsContainer">
    @if(request()->filled('author'))
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 mb-3">
            <div>
                <i class="ti ti-user-check me-1"></i>
                Memaparkan semua artikel oleh penyelidik: <strong>{{ request('author') }}</strong>
                <span class="badge bg-blue text-blue-fg ms-1">{{ $articles->total() }} Artikel</span>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-info">
                <i class="ti ti-x me-1"></i> Papar Semua Artikel
            </a>
        </div>
    @endif

    @if($searchTerm !== '')
        <div class="d-flex align-items-center justify-content-between text-muted small mb-3 px-1">
            <span>
                Menunjukkan <strong>{{ $articles->total() }}</strong> hasil carian untuk
                <mark style="background-color: #fef08a; color: #1e293b; padding: 0 4px; border-radius: 3px; font-weight: 600;">{{ $searchTerm }}</mark>
            </span>
        </div>
    @endif

    @forelse($articles as $article)
        <div class="card mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                        <span class="badge bg-blue text-blue-fg">{{ $article->category ?? 'Umum' }}</span>

                        @if($article->publication_year)
                            <span class="badge bg-azure-lt">{{ $article->publication_year }}</span>
                        @endif

                        @if(Auth::check() && Auth::id() === $article->user_id)
                            <span class="badge bg-purple-lt">
                                <i class="ti ti-user me-1"></i> Milik Saya
                            </span>
                        @endif

                        <span class="text-muted ms-1">
                            <i class="ti ti-eye"></i> {{ $article->views_count ?? 0 }} paparan
                        </span>
                        <span class="text-muted">
                            • <i class="ti ti-download"></i> {{ $article->downloads_count ?? 0 }} muat turun
                        </span>
                        <span class="text-muted">
                            • <i class="ti ti-message"></i> {{ $article->comments_count ?? 0 }} ulasan
                        </span>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ti ti-dots-vertical me-1"></i> Tindakan
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('articles.show', $article) }}">
                                    <i class="ti ti-file-text me-2"></i> Lihat Butiran Lengkap
                                </a>
                            </li>

                            @if($article->has_local_pdf)
                                <li>
                                    <a class="dropdown-item" href="{{ route('articles.viewPdf', $article) }}" target="_blank">
                                        <i class="ti ti-eye me-2"></i> Pratonton PDF
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('articles.download', $article) }}">
                                        <i class="ti ti-download me-2"></i> Muat Turun PDF
                                    </a>
                                </li>
                            @elseif($article->resolved_external_url)
                                <li>
                                    <a class="dropdown-item" href="{{ route('articles.download', $article) }}" target="_blank" rel="noopener noreferrer">
                                        <i class="ti ti-external-link me-2"></i> Buka Pautan Artikel (OJS)
                                    </a>
                                </li>
                            @endif
                            @if(!empty($article->ojs_link))
    <li>
        <a class="dropdown-item" href="{{ $article->ojs_link }}" target="_blank" rel="noopener noreferrer">
            <i class="ti ti-external-link me-2"></i> Buka Pautan Artikel (OJS)
        </a>
    </li>
@endif

                            @auth
                                <li>
                                    <form action="{{ route('articles.bookmark', $article) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="ti ti-bookmark me-2"></i>
                                            {{ in_array($article->id, $bookmarkedIds ?? [], true) ? 'Buang Bookmark' : 'Simpan Bookmark' }}
                                        </button>
                                    </form>
                                </li>

                                @if(method_exists(Auth::user(), 'canManageArticle') ? Auth::user()->canManageArticle($article) : Auth::id() === $article->user_id)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('articles.edit', $article) }}">
                                            <i class="ti ti-edit me-2"></i> Edit Artikel
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('articles.destroy', $article) }}" method="POST" onsubmit="return confirm('Padam artikel ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="ti ti-trash me-2"></i> Padam Artikel
                                            </button>
                                        </form>
                                    </li>
                                @endif
                            @endauth
                        </ul>
                    </div>
                </div>

                <h3 class="fw-bold mb-2" style="line-height: 1.4;">
                    <a href="{{ route('articles.show', $article) }}" class="text-reset text-decoration-none">
                        {!! $highlight($article->title) !!}
                    </a>
                </h3>

                <div class="text-muted small mb-2">
                    Penulis:
                    @forelse($article->parsed_authors as $authorName)
                        <a href="{{ route('dashboard', ['author' => $authorName]) }}"
                           class="text-decoration-none fw-medium link-primary">
                            {!! $highlight($authorName) !!}
                        </a>@if(!$loop->last), @endif
                    @empty
                        <span>Tidak dinyatakan</span>
                    @endforelse
                </div>

                @if($article->abstract)
                    <p class="text-secondary small mb-3" style="line-height: 1.65;">
                        {!! $highlightAbstract($article->abstract) !!}
                    </p>
                @endif

                @if($article->keywords)
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach(array_filter(array_map('trim', explode(',', $article->keywords))) as $tag)
                            <a href="{{ route('dashboard', ['search' => $tag]) }}" class="badge bg-secondary-lt text-decoration-none">
                                #{!! $highlight($tag) !!}
                            </a>
                        @endforeach
                    </div>
                @endif

                <div>
                    <a href="{{ route('articles.show', $article) }}" class="fw-semibold small text-decoration-none link-primary">
                        Lihat metadata, ulasan & artikel berkaitan →
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="card card-body text-center py-5 text-muted">
            <i class="ti ti-file-off fs-1 mb-2"></i>
            <p class="mb-2">Tiada artikel ditemui berdasarkan kriteria carian semasa.</p>
            <div>
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary">Papar Semua Artikel</a>
            </div>
        </div>
    @endforelse

    <div class="mt-4">
        {{ $articles->links() }}
    </div>
</div>
@auth
<div class="modal fade" id="bulkImportModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="ti ti-database-import me-2 text-primary"></i> Import Pukal Artikel (CSV / Pautan OJS)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#pane-ojs" type="button" role="tab">
                            <i class="ti ti-link me-1"></i> Senarai Pautan OJS
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#pane-csv" type="button" role="tab">
                            <i class="ti ti-file-spreadsheet me-1"></i> Fail CSV
                        </button>
                    </li>
                </ul>

                <div class="tab-content mb-3">
                    <div class="tab-pane fade show active" id="pane-ojs" role="tabpanel">
                        <label for="bulkOjsUrlsInput" class="form-label fw-semibold">
                            Tampal Senarai Pautan Artikel OJS <span class="text-muted fw-normal small">(1 pautan setiap baris)</span>
                        </label>
                        <textarea id="bulkOjsUrlsInput" rows="5" class="form-control font-monospace small"
                                  placeholder="https://ejpi.uis.edu.my/index.php/ejpi/article/view/286&#10;https://ejpi.uis.edu.my/index.php/ejpi/article/view/287"></textarea>
                    </div>

                    <div class="tab-pane fade" id="pane-csv" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="bulkCsvFileInput" class="form-label fw-semibold mb-0">Pilih Fail CSV (.csv)</label>
                            <button type="button" id="btnDownloadCsvTemplate" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-download me-1"></i> Muat Turun Template CSV
                            </button>
                        </div>
                        <input type="file" id="bulkCsvFileInput" class="form-control" accept=".csv,.txt">
                        <div class="form-text mt-2">
                            Lajur disokong: <code>title</code>, <code>author_name</code>, <code>abstract</code>, <code>keywords</code>, <code>category</code>, <code>publication_year</code>, <code>ojs_link</code>.
                        </div>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="bulkDownloadPdfCheck" checked>
                    <label class="form-check-label small" for="bulkDownloadPdfCheck">
                        Muat turun fail PDF ke pelayan tempatan secara automatik (jika tersedia pada OJS)
                    </label>
                </div>

                <div id="bulkProgressSection" class="d-none border rounded p-3 bg-surface-secondary">
                    <div class="d-flex justify-content-between align-items-center small fw-semibold mb-1">
                        <span id="bulkProgressLabel">Memulakan proses...</span>
                        <span id="bulkProgressPercent">0%</span>
                    </div>
                    <div class="progress mb-2" style="height: 10px;">
                        <div id="bulkProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 0%;"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small mb-2">
                        <span class="text-success fw-semibold">✓ Berjaya: <span id="bulkCountSuccess">0</span></span>
                        <span class="text-warning fw-semibold">↷ Dilangkau: <span id="bulkCountSkipped">0</span></span>
                        <span class="text-danger fw-semibold">✕ Gagal: <span id="bulkCountError">0</span></span>
                    </div>
                    <div id="bulkLogList" class="border rounded bg-body p-2 small" style="max-height: 190px; overflow-y: auto;"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="btnStopBulkImport" class="btn btn-outline-danger d-none">
                    <i class="ti ti-player-stop me-1"></i> Henti Proses
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" id="btnStartBulkImport" class="btn btn-primary px-4">
                    <i class="ti ti-upload me-1"></i> Mula Import Pukal
                </button>
            </div>
        </div>
    </div>
</div>
@endauth
<script>
(function () {
    const form = document.getElementById('articleFilterForm');
    const searchInput = document.getElementById('liveSearchInput');
    const clearBtn = document.getElementById('btnClearSearch');
    const searchIcon = document.getElementById('searchIcon');
    const searchSpinner = document.getElementById('searchSpinner');
    const resultsContainer = document.getElementById('articlesResultsContainer');
    const tabsContainer = document.getElementById('filterTabsContainer');

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

    async function performLiveSearch(customUrl = null) {
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
                throw new Error('Ralat pelayan semasa mencari.');
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');

            const newResults = doc.getElementById('articlesResultsContainer');
            if (newResults) {
                resultsContainer.innerHTML = newResults.innerHTML;
            }

            const newTabs = doc.getElementById('filterTabsContainer');
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

    // 1. Auto-search serta-merta semasa pengguna menaip (debounce 300ms)
    searchInput.addEventListener('input', function () {
        toggleClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => performLiveSearch(), 300);
    });

    // 2. Elak halaman reload penuh jika pengguna tekan Enter
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        performLiveSearch();
    });

    // 3. Penapis dropdown (Kategori, Tahun, Susunan) juga kemas kini secara live
    form.querySelectorAll('.js-auto-filter').forEach(select => {
        select.addEventListener('change', function () {
            clearTimeout(debounceTimer);
            performLiveSearch();
        });
    });

    // 4. Butang padam teks carian (X)
    clearBtn?.addEventListener('click', function () {
        searchInput.value = '';
        toggleClearButton();
        searchInput.focus();
        clearTimeout(debounceTimer);
        performLiveSearch();
    });

    // 5. Pautan pagination juga bertukar secara lancar tanpa hilang fokus
    resultsContainer.addEventListener('click', function (e) {
        const pageLink = e.target.closest('.pagination a');
        if (pageLink && pageLink.href) {
            e.preventDefault();
            performLiveSearch(pageLink.href);
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
    // --- SKRIP IMPORT PUKAL ---
    const btnStartBulk = document.getElementById('btnStartBulkImport');
    const btnStopBulk = document.getElementById('btnStopBulkImport');
    const btnDownloadTemplate = document.getElementById('btnDownloadCsvTemplate');
    const ojsUrlsInput = document.getElementById('bulkOjsUrlsInput');
    const csvFileInput = document.getElementById('bulkCsvFileInput');
    const downloadPdfCheck = document.getElementById('bulkDownloadPdfCheck');

    const progressSection = document.getElementById('bulkProgressSection');
    const progressLabel = document.getElementById('bulkProgressLabel');
    const progressPercent = document.getElementById('bulkProgressPercent');
    const progressBar = document.getElementById('bulkProgressBar');
    const countSuccessEl = document.getElementById('bulkCountSuccess');
    const countSkippedEl = document.getElementById('bulkCountSkipped');
    const countErrorEl = document.getElementById('bulkCountError');
    const logListEl = document.getElementById('bulkLogList');

    let stopRequested = false;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function parseCsvText(text) {
        text = text.replace(/^\uFEFF/, '');
        if (!text.trim()) return [];
        const firstLine = text.split(/\r?\n/)[0] || '';
        const delimiter = (firstLine.match(/;/g) || []).length > (firstLine.match(/,/g) || []).length ? ';' : ',';

        const rows = [];
        let row = [], val = '', inQuotes = false;
        for (let i = 0; i < text.length; i++) {
            const c = text[i], next = text[i + 1];
            if (c === '"') {
                if (inQuotes && next === '"') { val += '"'; i++; }
                else { inQuotes = !inQuotes; }
            } else if (c === delimiter && !inQuotes) {
                row.push(val.trim()); val = '';
            } else if ((c === '\n' || (c === '\r' && next === '\n')) && !inQuotes) {
                if (c === '\r') i++;
                row.push(val.trim());
                if (row.some(cell => cell !== '')) rows.push(row);
                row = []; val = '';
            } else {
                val += c;
            }
        }
        if (val !== '' || row.length > 0) {
            row.push(val.trim());
            if (row.some(cell => cell !== '')) rows.push(row);
        }
        if (rows.length < 2) return [];

        const headers = rows[0].map(h => h.replace(/^\uFEFF/, '').toLowerCase().trim());
        const pick = (obj, keys) => keys.map(k => obj[k]).find(v => v && String(v).trim() !== '') || '';

        return rows.slice(1).map(cols => {
            const map = {};
            headers.forEach((h, idx) => map[h] = cols[idx] ?? '');
            return {
                title: pick(map, ['title', 'tajuk']),
                author_name: pick(map, ['author_name', 'authors', 'penulis']),
                abstract: pick(map, ['abstract', 'abstrak']),
                keywords: pick(map, ['keywords', 'kata kunci']),
                category: pick(map, ['category', 'kategori', 'journal']),
                publication_year: pick(map, ['publication_year', 'year', 'tahun']),
                ojs_link: pick(map, ['ojs_link', 'url', 'link', 'pautan']),
            };
        }).filter(item => item.title || item.ojs_link);
    }

    function appendLog(type, title, message) {
        const badge = type === 'success'
            ? '<span class="badge bg-green-lt text-green me-1">BERJAYA</span>'
            : (type === 'skipped'
                ? '<span class="badge bg-yellow-lt text-yellow me-1">DILANGKAU</span>'
                : '<span class="badge bg-red-lt text-red me-1">GAGAL</span>');
        const line = document.createElement('div');
        line.className = 'py-1 border-bottom';
        line.innerHTML = `${badge}<strong>${escapeHtml(title)}</strong> — <span class="text-muted">${escapeHtml(message)}</span>`;
        logListEl.prepend(line);
    }

    btnDownloadTemplate?.addEventListener('click', function () {
        const csvContent = "\uFEFFtitle,author_name,abstract,keywords,category,publication_year,ojs_link\n"
            + "\"Contoh Tajuk Artikel\",\"Ahmad bin Ali; Siti Aminah\",\"Ringkasan abstrak kajian...\",\"Pendidikan, Teknologi\",\"e-Jurnal Penyelidikan dan Inovasi\",2026,\"https://ejpi.uis.edu.my/index.php/ejpi/article/view/286\"\n";
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'template_import_artikel.csv';
        a.click();
    });

    btnStopBulk?.addEventListener('click', function () {
        stopRequested = true;
        btnStopBulk.disabled = true;
        progressLabel.textContent = 'Menghentikan proses selepas rekod semasa selesai...';
    });

   btnStartBulk?.addEventListener('click', async function () {
        const isCsvTabActive = document.getElementById('pane-csv')?.classList.contains('active');
        let itemsToImport = [];

        progressSection.classList.remove('d-none');
        logListEl.innerHTML = '';

        if (isCsvTabActive) {
            const file = csvFileInput?.files?.[0];
            if (!file) {
                progressSection.classList.add('d-none');
                return alert('Sila pilih fail CSV terlebih dahulu.');
            }
            itemsToImport = parseCsvText(await file.text());
            if (itemsToImport.length === 0) {
                progressSection.classList.add('d-none');
                return alert('Tiada rekod sah ditemui di dalam fail CSV.');
            }
        } else {
            const rawLines = (ojsUrlsInput?.value || '').split(/\r?\n/).map(l => l.trim()).filter(Boolean);
            if (rawLines.length === 0) {
                progressSection.classList.add('d-none');
                return alert('Sila masukkan pautan jurnal atau artikel OJS.');
            }

            btnStartBulk.disabled = true;
            progressBar.style.width = '100%';
            progressBar.classList.add('progress-bar-animated');
            progressPercent.textContent = '...';
            progressLabel.textContent = 'Sedang mengimbas laman web jurnal & arkib isu untuk mengesan semua artikel...';

            const expandedUrls = [];
            for (const lineUrl of rawLines) {
                if (/\/article\/view\/\d+/i.test(lineUrl)) {
                    expandedUrls.push(lineUrl);
                    continue;
                }

                try {
                    const discRes = await fetch("{{ route('articles.discoverOjs') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ url: lineUrl })
                    });
                    const discData = await discRes.json();

                    if (discRes.ok && discData.success && Array.isArray(discData.urls)) {
                        expandedUrls.push(...discData.urls);
                        appendLog('success', lineUrl, `Berjaya mengesan ${discData.total} artikel daripada laman OJS.`);
                    } else {
                        appendLog('error', lineUrl, discData.message || 'Gagal mengimbas pautan jurnal.');
                    }
                } catch (err) {
                    appendLog('error', lineUrl, 'Ralat sambungan semasa mengimbas laman jurnal.');
                }
            }

            const uniqueUrls = [...new Set(expandedUrls)];
            if (uniqueUrls.length === 0) {
                btnStartBulk.disabled = false;
                progressBar.classList.remove('progress-bar-animated');
                progressLabel.textContent = 'Tiada pautan artikel ditemui.';
                return;
            }

            itemsToImport = uniqueUrls.map(url => ({ ojs_link: url }));
        }

        stopRequested = false;
        let successCount = 0, skippedCount = 0, errorCount = 0;
        const total = itemsToImport.length;
        const downloadPdf = downloadPdfCheck?.checked ?? true;

        countSuccessEl.textContent = '0';
        countSkippedEl.textContent = '0';
        countErrorEl.textContent = '0';
        progressBar.style.width = '0%';
        progressBar.classList.add('progress-bar-animated');
        progressPercent.textContent = '0%';
        btnStartBulk.disabled = true;
        btnStopBulk.classList.remove('d-none');
        btnStopBulk.disabled = false;

        for (let i = 0; i < total; i++) {
            if (stopRequested) break;
            const item = itemsToImport[i];
            const currentNum = i + 1;
            progressLabel.textContent = `Memproses ${currentNum} daripada ${total}...`;

            try {
                const res = await fetch("{{ route('articles.bulk-import') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ ...item, download_pdf: downloadPdf })
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    if (data.skipped) {
                        skippedCount++;
                        countSkippedEl.textContent = String(skippedCount);
                        appendLog('skipped', data.title || item.ojs_link, data.message);
                    } else {
                        successCount++;
                        countSuccessEl.textContent = String(successCount);
                        appendLog('success', data.title || item.ojs_link, data.message);
                    }
                } else {
                    errorCount++;
                    countErrorEl.textContent = String(errorCount);
                    appendLog('error', data.title || item.title || item.ojs_link, data.message || 'Gagal mengimport rekod.');
                }
            } catch (err) {
                errorCount++;
                countErrorEl.textContent = String(errorCount);
                appendLog('error', item.title || item.ojs_link, 'Ralat sambungan rangkaian.');
            }

            const pct = Math.round((currentNum / total) * 100);
            progressBar.style.width = pct + '%';
            progressPercent.textContent = pct + '%';

            if (i < total - 1 && !stopRequested) {
                await new Promise(r => setTimeout(r, 300));
            }
        }

        progressBar.classList.remove('progress-bar-animated');
        progressLabel.textContent = stopRequested
            ? 'Proses dihentikan oleh pengguna.'
            : `Selesai! ${successCount} artikel berjaya ditambah ke dalam repositori.`;

        btnStartBulk.disabled = false;
        btnStopBulk.classList.add('d-none');

        if (successCount > 0 && typeof performLiveSearch === 'function') {
            performLiveSearch();
        }
    });
})();
</script>
@endsection