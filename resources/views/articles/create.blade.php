@extends('layouts.main')

@section('title', 'Muat Naik Artikel Baharu - Journal Repository')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="fw-bold mb-1">Muat Naik Artikel Baharu</h2>
        <p class="text-muted small mb-0">Tampal pautan artikel OJS di bawah — semua metadata dan fail PDF akan ditarik secara automatik.</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">← Kembali ke Senarai</a>
</div>

@if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0 small">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

@if(session('success'))
    <div class="alert alert-success mb-3">{{ session('success') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body p-4">
        <form action="{{ route('articles.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="pdf_url" id="ojs_pdf_url" value="{{ old('pdf_url') }}">

            <!-- KOTAK AUTO-CAPTURE OJS (OTOMATIK TANPA PERLU TEKAN BUTANG) -->
            <div class="p-3 rounded mb-4 border" style="background-color: #f0f6ff; border-color: #cfe2ff !important;">
                <label for="ojs_link" class="form-label fw-bold text-primary mb-2">
                    <i class="ti ti-link me-1"></i> Pautan Artikel OJS (Tampal pautan untuk isi semua secara automatik)
                </label>
                <div class="input-group">
                    <input type="url"
                           name="ojs_link"
                           id="ojs_link"
                           class="form-control"
                           placeholder="Tampal pautan artikel OJS di sini (contoh: https://ejpi.uis.edu.my/index.php/ejpi/article/view/286)"
                           value="{{ old('ojs_link') }}"
                           autocomplete="off">
                    <button type="button" id="btnRefreshOjs" class="btn btn-outline-primary" title="Tarik semula metadata">
                        <i class="ti ti-refresh"></i>
                    </button>
                </div>
                <div id="ojsStatusMessage" class="small mt-2">
                    <span class="text-muted">Sebaik sahaja anda menampal pautan OJS, sistem akan mengisi tajuk, penulis, jurnal, kata kunci, abstrak dan fail PDF secara automatik.</span>
                </div>
            </div>

            <!-- TAJUK ARTIKEL -->
            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Tajuk Artikel <span class="text-danger">*</span></label>
                <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required>
            </div>

            <!-- NAMA PENULIS -->
            <div class="mb-3">
                <label for="author_name" class="form-label fw-semibold">Nama Penulis / Penyelidik <span class="text-danger">*</span></label>
                <input type="text" name="author_name" id="author_name" class="form-control" value="{{ old('author_name') }}" required>
            </div>

            <!-- KATEGORI, KATA KUNCI & TAHUN -->
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="category" class="form-label fw-semibold">Kategori / Nama Jurnal</label>
                    <input type="text" name="category" id="category" class="form-control" placeholder="Contoh: e-Jurnal Penyelidikan dan Inovasi" value="{{ old('category') }}">
                </div>
                <div class="col-md-5">
                    <label for="keywords" class="form-label fw-semibold">Kata Kunci / Tags <span class="text-muted fw-normal small">(Pisahkan dengan koma)</span></label>
                    <input type="text" name="keywords" id="keywords" class="form-control" placeholder="Contoh: AI, Pendidikan, Kewangan Islam" value="{{ old('keywords') }}">
                </div>
                <div class="col-md-3">
                    <label for="publication_year" class="form-label fw-semibold">Tahun Diterbitkan <span class="text-danger">*</span></label>
                    <input type="text" name="publication_year" id="publication_year" class="form-control" value="{{ old('publication_year', date('Y')) }}" required>
                </div>
            </div>

            <!-- ABSTRAK -->
            <div class="mb-3">
                <label for="abstract" class="form-label fw-semibold">Abstrak / Ringkasan <span class="text-danger">*</span></label>
                <textarea name="abstract" id="abstract" rows="5" class="form-control" required>{{ old('abstract') }}</textarea>
            </div>

            <!-- MUAT NAIK FAIL PDF MANUAL -->
            <div class="mb-4">
                <label for="pdf_file" class="form-label fw-semibold">Muat Naik Fail PDF Manual <span class="text-muted fw-normal small">(Pilihan — kosongkan jika PDF berjaya ditarik dari OJS)</span></label>
                <input type="file" name="pdf_file" id="pdf_file" class="form-control" accept="application/pdf">
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" id="btnSubmitArticle" class="btn btn-primary px-4">
                    <i class="ti ti-device-floppy me-1"></i> Simpan Artikel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const urlInput = document.getElementById('ojs_link');
    const statusEl = document.getElementById('ojsStatusMessage');
    const refreshBtn = document.getElementById('btnRefreshOjs');
    let lastFetchedUrl = '';
    let debounceTimer = null;
    let isFetching = false;

    function isValidHttpUrl(str) {
        try {
            const u = new URL(str);
            return (u.protocol === 'http:' || u.protocol === 'https:') && u.hostname.includes('.');
        } catch (_) {
            return false;
        }
    }

    async function fetchMetadataAutomatically(force = false) {
        const url = urlInput.value.trim();
        if (!url || !isValidHttpUrl(url)) {
            return;
        }
        if (!force && (url === lastFetchedUrl || isFetching)) {
            return;
        }

        isFetching = true;
        lastFetchedUrl = url;
        if (refreshBtn) refreshBtn.disabled = true;

        statusEl.innerHTML = '<span class="text-primary fw-medium"><span class="spinner-border spinner-border-sm me-1"></span> Sedang menarik semua metadata & fail PDF secara automatik...</span>';

        try {
            const response = await fetch("{{ route('articles.fetchOjs') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ url })
            });

            const data = await response.json();

            if (!response.ok || data.error) {
                throw new Error(data.error || 'Gagal menangkap data daripada pautan OJS.');
            }

            if (data.title) document.getElementById('title').value = data.title;
            if (data.author_name) document.getElementById('author_name').value = data.author_name;
            if (data.category) document.getElementById('category').value = data.category;
            if (data.keywords) document.getElementById('keywords').value = data.keywords;
            if (data.publication_year) document.getElementById('publication_year').value = data.publication_year;
            if (data.abstract) document.getElementById('abstract').value = data.abstract;
            document.getElementById('ojs_pdf_url').value = data.pdf_url || '';

            if (data.pdf_url) {
                statusEl.innerHTML = '<span class="text-success fw-medium">✓ Semua metadata & fail PDF berjaya dikesan! (<a href="' + data.pdf_url + '" target="_blank" class="text-decoration-underline">Lihat pautan PDF</a>) — Fail PDF akan disimpan automatik apabila anda klik <strong>Simpan Artikel</strong>.</span>';
            } else {
                statusEl.innerHTML = '<span class="text-success fw-medium">✓ Metadata berjaya ditarik secara automatik (tiada fail PDF terbuka dikesan; anda boleh muat naik PDF manual di bawah jika ada).</span>';
            }
        } catch (err) {
            lastFetchedUrl = '';
            statusEl.innerHTML = '<span class="text-danger">✕ ' + err.message + '</span>';
        } finally {
            isFetching = false;
            if (refreshBtn) refreshBtn.disabled = false;
        }
    }

    // 1. Auto-tarik serta-merta apabila tampal (paste) pautan
    urlInput?.addEventListener('paste', function () {
        setTimeout(() => fetchMetadataAutomatically(false), 60);
    });

    // 2. Auto-tarik apabila menaip pautan (selepas berhenti menaip 700ms)
    urlInput?.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchMetadataAutomatically(false), 700);
    });

    // 3. Auto-tarik apabila keluar dari kotak input (change / blur)
    urlInput?.addEventListener('change', function () {
        fetchMetadataAutomatically(false);
    });

    // 4. Butang muat semula manual jika diperlukan
    refreshBtn?.addEventListener('click', function () {
        fetchMetadataAutomatically(true);
    });
})();
</script>
@endsection