@extends('layouts.main')

@section('title', 'Analitik & Statistik Repositori')

@section('content')
<div class="page-header d-print-none mb-3">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Laporan & Impak Penyelidikan</div>
            <h2 class="page-title">Analitik & Statistik Repositori</h2>
        </div>
        <div class="col-auto ms-auto">
            <button type="button" onclick="window.print()" class="btn btn-outline-primary btn-sm">
                <i class="ti ti-printer me-1"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>
</div>

<!-- BAR PENAPIS INTERAKTIF -->
<div class="card shadow-sm mb-4 d-print-none">
    <div class="card-body py-3">
        <form action="{{ route('dashboards.analytics') }}" method="GET" class="row g-2 align-items-end">
            <!-- Penapis 1: Tahun Penerbitan -->
            <div class="col-sm-6 col-lg-3">
                <label class="form-label small fw-bold text-muted mb-1">
                    <i class="ti ti-calendar me-1"></i> Tahun Penerbitan
                </label>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Tahun</option>
                    <option value="last_5" {{ ($selectedYear ?? '') === 'last_5' ? 'selected' : '' }}>
                        5 Tahun Terkini ({{ date('Y') - 4 }}–{{ date('Y') }})
                    </option>
                    @foreach($availableYears ?? [] as $yr)
                        <option value="{{ $yr }}" {{ (string)($selectedYear ?? '') === (string)$yr ? 'selected' : '' }}>
                            Tahun {{ $yr }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Penapis 2: Jurnal / Bidang -->
            <div class="col-sm-6 col-lg-3">
                <label class="form-label small fw-bold text-muted mb-1">
                    <i class="ti ti-book-2 me-1"></i> Jurnal / Bidang
                </label>
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Jurnal / Bidang</option>
                    @foreach($availableCategories ?? [] as $cat)
                        <option value="{{ $cat }}" {{ ($selectedCategory ?? '') === $cat ? 'selected' : '' }}>
                            {{ $cat }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Penapis 3: Status Sumber Fail -->
            <div class="col-sm-6 col-lg-3">
                <label class="form-label small fw-bold text-muted mb-1">
                    <i class="ti ti-file-text me-1"></i> Kelengkapan Sumber
                </label>
                <select name="source" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Semua Sumber Artikel</option>
                    <option value="pdf" {{ ($selectedSource ?? '') === 'pdf' ? 'selected' : '' }}>
                        Ada Fail PDF Sahaja
                    </option>
                    <option value="ojs" {{ ($selectedSource ?? '') === 'ojs' ? 'selected' : '' }}>
                        Pautan OJS Sahaja (Tiada PDF)
                    </option>
                </select>
            </div>

            <!-- Penapis 4: Had Paparan Carta + Butang Set Semula -->
            <div class="col-sm-6 col-lg-3">
                <label class="form-label small fw-bold text-muted mb-1">
                    <i class="ti ti-list-numbers me-1"></i> Had Kedudukan Carta
                </label>
                <div class="d-flex gap-2">
                    <select name="limit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="5" {{ ($topLimit ?? 5) == 5 ? 'selected' : '' }}>Top 5</option>
                        <option value="10" {{ ($topLimit ?? 5) == 10 ? 'selected' : '' }}>Top 10</option>
                        <option value="15" {{ ($topLimit ?? 5) == 15 ? 'selected' : '' }}>Top 15</option>
                    </select>

                    @if(request()->anyFilled(['year', 'category', 'source']) or ($topLimit ?? 5) != 5)
                        <a href="{{ route('dashboards.analytics') }}" class="btn btn-sm btn-outline-danger flex-shrink-0" title="Set Semula Penapis">
                            <i class="ti ti-rotate-2"></i> Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 4 KAD RINGKASAN KPI -->
<div class="row row-deck row-cards mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar"><i class="ti ti-books"></i></span>
                    </div>
                    <div class="col">
                        <div class="fw-bold fs-3">{{ number_format($stats['total_articles'] ?? 0) }}</div>
                        <div class="text-muted small">Jumlah Penerbitan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-azure text-white avatar"><i class="ti ti-eye"></i></span>
                    </div>
                    <div class="col">
                        <div class="fw-bold fs-3">{{ number_format($stats['total_views'] ?? 0) }}</div>
                        <div class="text-muted small">Jumlah Paparan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-green text-white avatar"><i class="ti ti-download"></i></span>
                    </div>
                    <div class="col">
                        <div class="fw-bold fs-3">{{ number_format($stats['total_downloads'] ?? 0) }}</div>
                        <div class="text-muted small">Jumlah Muat Turun</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-purple text-white avatar"><i class="ti ti-message-circle"></i></span>
                    </div>
                    <div class="col">
                        <div class="fw-bold fs-3">{{ number_format($stats['total_comments'] ?? 0) }}</div>
                        <div class="text-muted small">Ulasan Akademik</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $limitCount = $topLimit ?? 5;
    $horizontalChartHeight = $limitCount > 5 ? ($limitCount * 36) : 260;
@endphp

<!-- 6 GRAF ANALITIK -->
<div class="row row-deck row-cards">
    <!-- Graf 1: Penerbitan Mengikut Tahun -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-trending-up text-primary me-2"></i> Trend Penerbitan Mengikut Tahun
                </h3>
            </div>
            <div class="card-body">
                <div style="height: 280px;">
                    <canvas id="yearChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Graf 2: Pecahan Mengikut Jurnal / Kategori -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-chart-pie text-indigo me-2"></i> Pecahan Mengikut Jurnal / Bidang
                </h3>
            </div>
            <div class="card-body">
                <div style="height: 280px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Graf 3: Top N Paling Banyak Dipapar -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-eye text-azure me-2"></i> Top {{ $limitCount }} Artikel Paling Banyak Dipapar
                </h3>
            </div>
            <div class="card-body">
                @if($topViewedArticles->isEmpty())
                    <div class="text-center text-muted py-5 small">Tiada rekod paparan artikel bagi penapis ini.</div>
                @else
                    <div style="height: {{ $horizontalChartHeight }}px;">
                        <canvas id="topViewedChart"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Graf 4: Top N Paling Banyak Dimuat Turun -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-download text-success me-2"></i> Top {{ $limitCount }} Artikel Paling Banyak Dimuat Turun
                </h3>
            </div>
            <div class="card-body">
                @if($topDownloadedArticles->isEmpty())
                    <div class="text-center text-muted py-5 small">Tiada rekod muat turun bagi penapis ini.</div>
                @else
                    <div style="height: {{ $horizontalChartHeight }}px;">
                        <canvas id="topDownloadedChart"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Graf 5: Top N Penulis Paling Aktif -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-users text-purple me-2"></i> Top {{ $limitCount }} Penulis Paling Aktif
                </h3>
            </div>
            <div class="card-body">
                @if(($topAuthors ?? collect())->isEmpty())
                    <div class="text-center text-muted py-5 small">Tiada rekod penulis bagi penapis ini.</div>
                @else
                    <div style="height: {{ $horizontalChartHeight }}px;">
                        <canvas id="topAuthorsChart"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Graf 6: Top N Bookmark -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-bookmark text-warning me-2"></i> Top {{ $limitCount }} Artikel Paling Banyak Disimpan (Bookmark)
                </h3>
            </div>
            <div class="card-body">
                @if($topBookmarkedArticles->isEmpty())
                    <div class="text-center text-muted py-5 small">
                        Belum ada artikel yang disimpan dalam senarai bacaan pengguna bagi penapis ini.
                    </div>
                @else
                    <div style="height: {{ $horizontalChartHeight }}px;">
                        <canvas id="topBookmarkChart"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const truncateLabel = (text, max = 36) => text.length > max ? text.substring(0, max) + '...' : text;

    // 1. Graf Penerbitan Mengikut Tahun
    const yearData = @json($articlesByYear);
    new Chart(document.getElementById('yearChart'), {
        type: 'bar',
        data: {
            labels: yearData.map(item => item.publication_year),
            datasets: [{
                label: 'Bilangan Artikel',
                data: yearData.map(item => item.total),
                backgroundColor: 'rgba(32, 107, 196, 0.85)',
                borderColor: '#206bc4',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
            }
        }
    });

    // 2. Graf Pecahan Mengikut Kategori / Jurnal
    const catData = @json($articlesByCategory);
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: catData.map(item => item.category),
            datasets: [{
                data: catData.map(item => item.total),
                backgroundColor: [
                    '#206bc4', '#4299e1', '#2fb344', '#f59f00',
                    '#ae3ec9', '#d63939', '#0ca678', '#667382'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } }
            }
        }
    });

    // 3. Graf Top N Paling Banyak Dipapar
    const viewedCanvas = document.getElementById('topViewedChart');
    if (viewedCanvas) {
        const viewedRaw = @json($topViewedArticles);
        new Chart(viewedCanvas, {
            type: 'bar',
            data: {
                labels: viewedRaw.map(item => truncateLabel(item.title, 36)),
                datasets: [{
                    label: 'Jumlah Paparan',
                    data: viewedRaw.map(item => item.views_count),
                    backgroundColor: 'rgba(66, 153, 225, 0.85)',
                    borderColor: '#4299e1',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: (ctx) => viewedRaw[ctx[0].dataIndex].title } }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        });
    }

    // 4. Graf Top N Paling Banyak Dimuat Turun
    const downloadCanvas = document.getElementById('topDownloadedChart');
    if (downloadCanvas) {
        const downloadRaw = @json($topDownloadedArticles);
        new Chart(downloadCanvas, {
            type: 'bar',
            data: {
                labels: downloadRaw.map(item => truncateLabel(item.title, 36)),
                datasets: [{
                    label: 'Jumlah Muat Turun',
                    data: downloadRaw.map(item => item.downloads_count),
                    backgroundColor: 'rgba(47, 179, 68, 0.85)',
                    borderColor: '#2fb344',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: (ctx) => downloadRaw[ctx[0].dataIndex].title } }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        });
    }

    // 5. Graf Top N Penulis Paling Aktif
    const authorsCanvas = document.getElementById('topAuthorsChart');
    if (authorsCanvas) {
        const authorsRaw = @json($topAuthors ?? []);
        new Chart(authorsCanvas, {
            type: 'bar',
            data: {
                labels: authorsRaw.map(item => truncateLabel(item.author_name, 36)),
                datasets: [{
                    label: 'Jumlah Penerbitan',
                    data: authorsRaw.map(item => item.total),
                    backgroundColor: 'rgba(174, 62, 201, 0.85)',
                    borderColor: '#ae3ec9',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: (ctx) => authorsRaw[ctx[0].dataIndex].author_name } }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        });
    }

    // 6. Graf Top N Bookmark
    const bookmarkCanvas = document.getElementById('topBookmarkChart');
    if (bookmarkCanvas) {
        const bookmarkRaw = @json($topBookmarkedArticles);
        new Chart(bookmarkCanvas, {
            type: 'bar',
            data: {
                labels: bookmarkRaw.map(item => truncateLabel(item.title, 36)),
                datasets: [{
                    label: 'Jumlah Simpanan (Bookmark)',
                    data: bookmarkRaw.map(item => item.bookmarked_by_count),
                    backgroundColor: 'rgba(245, 159, 0, 0.85)',
                    borderColor: '#f59f00',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: (ctx) => bookmarkRaw[ctx[0].dataIndex].title } }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        });
    }
</script>
@endpush