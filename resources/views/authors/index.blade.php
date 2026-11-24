@extends('layouts.main')

@section('title', 'Direktori Penulis - Journal Repository')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Direktori Penyelidik & Penulis</h2>
        <p class="text-muted small mb-0">Jumlah {{ $authors->count() }} penyelidik berdaftar dalam repositori.</p>
    </div>
    <div style="min-width: 280px;">
        <input type="text" id="authorLiveSearch" class="form-control" placeholder="Cari nama penyelidik..." value="{{ request('search') }}">
    </div>
</div>

<div class="row g-3" id="authorsGrid">
    @forelse($authors as $author)
        <div class="col-md-6 col-lg-4 author-card-item" data-name="{{ mb_strtolower($author->author_name) }}">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar avatar-md rounded-circle bg-blue-lt fw-bold text-primary">
                            {{ strtoupper(mb_substr($author->author_name, 0, 1)) }}
                        </span>
                        <div>
                            <h4 class="fw-bold mb-0 author-display-name">{{ $author->author_name }}</h4>
                            <span class="text-muted small">Penerbitan Terkini: {{ $author->latest_year ?? '-' }}</span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between text-center border-top border-bottom py-2 mb-3 small">
                        <div class="flex-fill">
                            <div class="fw-bold">{{ $author->articles_count }}</div>
                            <div class="text-muted">Artikel</div>
                        </div>
                        <div class="flex-fill border-start border-end">
                            <div class="fw-bold">{{ $author->total_views }}</div>
                            <div class="text-muted">Paparan</div>
                        </div>
                        <div class="flex-fill">
                            <div class="fw-bold">{{ $author->total_downloads }}</div>
                            <div class="text-muted">Muat Turun</div>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <a href="{{ route('dashboard', ['author' => $author->author_name]) }}" class="btn btn-sm btn-outline-primary w-100 fw-semibold">
                            Lihat Semua Artikel Penulis →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card card-body text-center text-muted py-5">
                Tiada rekod penulis ditemui.
            </div>
        </div>
    @endforelse
</div>

<script>
document.getElementById('authorLiveSearch')?.addEventListener('input', function () {
    const query = this.value.toLowerCase().trim();
    document.querySelectorAll('.author-card-item').forEach(card => {
        const name = card.getAttribute('data-name') || '';
        card.style.display = name.includes(query) ? '' : 'none';
    });
});
</script>
@endsection