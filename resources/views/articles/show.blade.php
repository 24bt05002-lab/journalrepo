@extends('layouts.main')

@section('title', $article->title . ' - Journal Repository')

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">← Kembali ke Senarai</a>
    <div class="text-muted small">
        <span class="me-3"><i class="ti ti-eye"></i> <strong>{{ $article->views_count ?? 0 }}</strong> Paparan</span>
        <span><i class="ti ti-download"></i> <strong>{{ $article->downloads_count ?? 0 }}</strong> Muat Turun</span>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-4 p-md-5">
        <div class="mb-3 d-flex flex-wrap gap-2">
            <span class="badge bg-blue text-blue-fg">{{ $article->category ?? 'Umum' }}</span>
            <span class="badge bg-azure-lt">{{ $article->publication_year }}</span>
            @if($article->keywords)
                @foreach(array_filter(array_map('trim', explode(',', $article->keywords))) as $tag)
                    <a href="{{ route('dashboard', ['search' => $tag]) }}" class="badge bg-secondary-lt text-decoration-none">#{{ $tag }}</a>
                @endforeach
            @endif
        </div>

        <h1 class="fw-bold mb-3" style="line-height: 1.35;">{{ $article->title }}</h1>

        <div class="p-3 rounded mb-4 border bg-surface-secondary">
            <div class="row small g-2">
                <div class="col-md-6">
                    <span class="text-muted d-block">Penulis:</span>
                    @forelse($article->parsed_authors as $authorName)
                        <a href="{{ route('dashboard', ['author' => $authorName]) }}"
                           class="text-decoration-none fw-bold link-primary">
                            {{ $authorName }}
                        </a>@if(!$loop->last), @endif
                    @empty
                        <strong>Tidak dinyatakan</strong>
                    @endforelse
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">Dimuat Naik Oleh:</span>
                    <strong>{{ $article->user->name ?? 'Sistem' }}</strong>
                    <span class="text-muted">({{ $article->created_at->format('d M Y') }})</span>
                </div>
            </div>
        </div>

        <h4 class="text-uppercase text-primary small fw-bold mb-2">Abstrak / Ringkasan</h4>
        <p class="text-secondary mb-4" style="line-height: 1.8; text-align: justify;">
            {{ $article->abstract }}
        </p>

        <div class="pt-4 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex flex-wrap gap-2">
                @if($article->has_local_pdf)
                    <a href="{{ route('articles.viewPdf', $article) }}" target="_blank" class="btn btn-primary">
                        <i class="ti ti-eye me-1"></i> Pratonton PDF
                    </a>
                    <a href="{{ route('articles.download', $article) }}" class="btn btn-outline-primary">
                        <i class="ti ti-download me-1"></i> Muat Turun PDF
                    </a>
                @elseif($article->resolved_external_url)
                    <a href="{{ route('articles.download', $article) }}" target="_blank" class="btn btn-primary">
                        <i class="ti ti-external-link me-1"></i> Buka / Muat Turun Artikel (OJS)
                    </a>
                @endif
                @if(!empty($article->ojs_link))
    <a href="{{ $article->ojs_link }}"
       target="_blank"
       rel="noopener noreferrer"
       class="btn btn-outline-primary">
        <i class="ti ti-external-link me-1"></i> Pautan Asal OJS
    </a>
@endif
            </div>

            @if(Auth::check() && Auth::user()->canManageArticle($article))
                <div class="d-flex gap-2">
                    <a href="{{ route('articles.edit', $article) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form action="{{ route('articles.destroy', $article) }}" method="POST" onsubmit="return confirm('Padam artikel ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            @endif
            
        </div>
        
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-messages me-2 text-primary"></i> Ulasan & Nota Akademik ({{ $article->comments->count() }})
                </h3>
            </div>
            <div class="card-body">
                <form action="{{ route('articles.comments.store', $article) }}" method="POST" class="mb-4">
                    @csrf
                    <div class="mb-2">
                        <textarea name="body" rows="3" class="form-control @error('body') is-invalid @enderror" placeholder="Tulis ulasan, pertanyaan penyelidikan, atau nota rujukan anda di sini..." required></textarea>
                        @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-sm btn-primary px-3">
                            <i class="ti ti-send me-1"></i> Hantar Ulasan
                        </button>
                    </div>
                </form>

                <div class="divide-y">
                    @forelse($article->comments as $comment)
                        <div class="py-3">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar-xs rounded-circle bg-blue-lt fw-bold">
                                        {{ strtoupper(substr($comment->user->name ?? 'U', 0, 1)) }}
                                    </span>
                                    <strong class="small">{{ $comment->user->name ?? 'Pengguna' }}</strong>
                                    <span class="badge bg-secondary-lt" style="font-size: 0.65rem;">{{ strtoupper($comment->user->role ?? '') }}</span>
                                    <span class="text-muted" style="font-size: 0.75rem;">• {{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                @if(Auth::id() === $comment->user_id or Auth::user()->isAdmin() or Auth::user()->canManageArticle($article))
                                    <form action="{{ route('articles.comments.destroy', $comment) }}" method="POST" onsubmit="return confirm('Padam ulasan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link text-danger p-0 small" title="Padam ulasan">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <p class="text-secondary small mb-0 ps-4" style="line-height: 1.6;">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <div class="text-center text-muted small py-3">
                            Belum ada ulasan akademik untuk artikel ini. Jadilah yang pertama berkongsi pandangan!
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title fw-bold">
                    <i class="ti ti-sparkles me-2 text-primary"></i> Artikel Berkaitan
                </h3>
            </div>
            <div class="list-group list-group-flush">
                @forelse($relatedArticles as $related)
                    <a href="{{ route('articles.show', $related) }}" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-blue-lt">{{ $related->category ?? 'Umum' }}</span>
                            <span class="text-muted small">{{ $related->publication_year }}</span>
                        </div>
                        <div class="fw-bold text-reset mb-1" style="line-height: 1.4;">
                            {{ Str::limit($related->title, 85) }}
                        </div>
                        <div class="text-muted small">
                            Oleh: {{ implode(', ', $related->parsed_authors) ?: $related->author_name }}
                        </div>
                    </a>
                @empty
                    <div class="list-group-item text-center text-muted small py-4">
                        Tiada cadangan artikel berkaitan buat masa ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection