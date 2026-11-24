<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunting Artikel - Journal Repository</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7fb; color: #1e293b; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .navbar { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); box-shadow: 0 2px 10px rgba(15, 23, 42, 0.12); }
        .form-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05); }
        .btn-primary-blue { background-color: #2563eb; color: #ffffff; border: 1px solid #2563eb; }
        .btn-primary-blue:hover { background-color: #1d4ed8; color: #ffffff; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark py-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">JOURNAL REPOSITORY</a>
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light">← Kembali ke Senarai</a>
        </div>
    </nav>

    <div class="container my-5" style="max-width: 750px;">
        <div class="mb-4 pb-2 border-bottom">
            <h3 class="fw-bold mb-1">Sunting Artikel</h3>
            <p class="text-muted small">Kemas kini maklumat metadata atau gantikan fail PDF sedia ada.</p>
        </div>

        <form action="{{ route('articles.update', $article) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label small fw-bold">Pautan Artikel OJS</label>
                <input type="url" name="ojs_link" class="form-control border-dark" value="{{ old('ojs_link', $article->ojs_link) }}">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Tajuk Artikel <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control border-dark" value="{{ old('title', $article->title) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Nama Penulis / Penyelidik <span class="text-danger">*</span></label>
                <input type="text" name="author_name" class="form-control border-dark" value="{{ old('author_name', $article->author_name) }}" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">Kategori / Nama Jurnal</label>
                    <input type="text" name="category" class="form-control border-dark" value="{{ old('category', $article->category) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">Tahun Diterbitkan <span class="text-danger">*</span></label>
                    <input type="number" name="publication_year" class="form-control border-dark" value="{{ old('publication_year', $article->publication_year) }}" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Abstrak / Ringkasan <span class="text-danger">*</span></label>
                <textarea name="abstract" rows="5" class="form-control border-dark" required>{{ old('abstract', $article->abstract) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">Gantikan Fail PDF (Biarkan kosong jika kekal fail lama)</label>
                <input type="file" name="file_path" accept="application/pdf" class="form-control border-dark">
                @if($article->file_path)
                    <div class="small text-muted mt-1">Fail semasa tersedia dalam pelayan.</div>
                @endif
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-dark">Batal</a>
                <button type="submit" class="btn btn-black px-4">Simpan Perubahan</button>
            </div>
        </form>
    </div>

</body>
</html>