<?php

namespace App\Http\Controllers;

use App\Models\Article;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    private function hasCommentModel(): bool
    {
        return class_exists(\App\Models\ArticleComment::class)
            || class_exists(\App\Models\Comment::class);
    }

    private function getBookmarkTable(): ?string
    {
        foreach (['bookmarks', 'article_bookmarks', 'article_user'] as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }
        return null;
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            return view('welcome');
        }
        $query = Article::with('user');

        $counts = [];
        if ($this->getBookmarkTable()) {
            $counts[] = 'bookmarkedBy';
        }
        if ($this->hasCommentModel()) {
            $counts[] = 'comments';
        }
        if (!empty($counts)) {
            $query->withCount($counts);
        }

        if ($request->filled('author')) {
            $targetAuthor = mb_strtolower(trim((string) $request->input('author')));

            $candidates = Article::whereNotNull('author_name')
                ->where(function ($q) use ($targetAuthor) {
                    foreach (preg_split('/\s+/u', $targetAuthor) as $word) {
                        if ($word !== '') {
                            $q->where('author_name', 'like', "%{$word}%");
                        }
                    }
                })
                ->get(['id', 'author_name']);

            $matchedIds = $candidates->filter(function ($article) use ($targetAuthor) {
                foreach (Article::parseAuthorNames($article->author_name) as $n) {
                    if (mb_strtolower(trim($n)) === $targetAuthor) {
                        return true;
                    }
                }
                return false;
            })->pluck('id');

            $query->whereIn('id', $matchedIds);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%")
                  ->orWhere('keywords', 'like', "%{$search}%")
                  ->orWhere('abstract', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('year')) {
            $query->where('publication_year', $request->input('year'));
        }

        if ($request->boolean('bookmarked') && Auth::check() && $this->getBookmarkTable()) {
            $query->whereHas('bookmarkedBy', fn ($q) => $q->where('user_id', Auth::id()));
        }

        if ($request->boolean('mine') && Auth::check()) {
            $query->where('user_id', Auth::id());
        }

        $sort = $request->input('sort', 'latest');
        if ($sort === 'views') {
            $query->orderByDesc('views_count');
        } elseif ($sort === 'downloads') {
            $query->orderByDesc('downloads_count');
        } elseif ($sort === 'year') {
            $query->orderByDesc('publication_year')->latest();
        } else {
            $query->latest();
        }

        $articles = $query->paginate(10)->withQueryString();

        $categories = Article::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $years = Article::whereNotNull('publication_year')
            ->where('publication_year', '!=', '')
            ->distinct()
            ->orderByDesc('publication_year')
            ->pluck('publication_year');

        $bookmarkedIds = [];
        if (Auth::check()) {
            $user = Auth::user();
            if (method_exists($user, 'bookmarks')) {
                $bookmarkedIds = $user->bookmarks()->pluck('articles.id')->toArray();
            } elseif ($bookmarkTable = $this->getBookmarkTable()) {
                $bookmarkedIds = DB::table($bookmarkTable)
                    ->where('user_id', $user->id)
                    ->pluck('article_id')
                    ->toArray();
            }
        }

        $totalArticles = Article::count();
        $myArticlesCount = Auth::check() ? Article::where('user_id', Auth::id())->count() : 0;

        $viewName = view()->exists('articles.index') ? 'articles.index' : 'dashboard';

        return view($viewName, compact(
            'articles',
            'categories',
            'years',
            'bookmarkedIds',
            'totalArticles',
            'myArticlesCount'
        ));
    }

    public function create()
    {
        return view('articles.create');
    }

    public function store(Request $request)
    {
        $ojsLink = trim((string) $request->input('ojs_link', ''));
        if ($ojsLink !== '' && !preg_match('#^https?://#i', $ojsLink)) {
            $ojsLink = 'https://' . ltrim($ojsLink, '/');
        }

        // Jika pengguna terus klik Simpan sebelum JS selesai menarik data, tarik di pelayan
        $autoMeta = [];
        if ($ojsLink !== '' && (!$request->filled('title') || !$request->filled('abstract') || !$request->filled('pdf_url'))) {
            try {
                $autoMeta = $this->extractOjsMetadataFromUrl($ojsLink);
            } catch (\Throwable $e) {
                $autoMeta = [];
            }
        }

        $request->merge([
            'ojs_link'         => $ojsLink !== '' ? $ojsLink : null,
            'title'            => $request->input('title') ?: ($autoMeta['title'] ?? ''),
            'author_name'      => $request->input('author_name') ?: ($autoMeta['author_name'] ?? ''),
            'category'         => $request->input('category') ?: ($autoMeta['category'] ?? ''),
            'keywords'         => $request->input('keywords') ?: ($autoMeta['keywords'] ?? ''),
            'publication_year' => $request->input('publication_year') ?: ($autoMeta['publication_year'] ?? date('Y')),
            'abstract'         => $request->input('abstract') ?: ($autoMeta['abstract'] ?? ''),
            'pdf_url'          => $request->input('pdf_url') ?: ($autoMeta['pdf_url'] ?? ''),
        ]);

        $validated = $request->validate([
            'title'            => 'required|string|max:500',
            'author_name'      => 'required|string|max:500',
            'abstract'         => 'nullable|string',
            'keywords'         => 'nullable|string|max:500',
            'category'         => 'nullable|string|max:150',
            'publication_year' => 'nullable|string|max:10',
            'ojs_link'         => 'nullable|string|max:500',
            'pdf_url'          => 'nullable|string|max:500',
            'pdf_file'         => 'nullable|file|mimes:pdf|max:20480',
        ]);

        $filePath = null;

        // 1. Jika pengguna muat naik fail PDF secara manual
        if ($request->hasFile('pdf_file')) {
            $filePath = $request->file('pdf_file')->store('articles', 'public');
        }
        // 2. Auto-muat turun fail PDF dari OJS ke storage tempatan
        elseif (!empty($validated['pdf_url']) || $ojsLink !== '') {
            $targetPdfUrl = !empty($validated['pdf_url']) ? $validated['pdf_url'] : $ojsLink;
            $filePath = $this->downloadRemotePdf($targetPdfUrl, $validated['title'], $ojsLink);
        }

        Article::create([
            'user_id'          => Auth::id(),
            'title'            => $validated['title'],
            'author_name'      => $validated['author_name'],
            'abstract'         => $validated['abstract'] ?? null,
            'keywords'         => $validated['keywords'] ?? null,
            'category'         => !empty($validated['category']) ? $validated['category'] : 'Umum',
            'publication_year' => !empty($validated['publication_year']) ? $validated['publication_year'] : date('Y'),
            'ojs_link'         => $ojsLink !== '' ? $ojsLink : null,
            'file_path'        => $filePath,
        ]);

        $msg = $filePath
            ? 'Artikel dan fail PDF berjaya disimpan ke dalam repositori.'
            : 'Artikel berjaya disimpan (pautan OJS dipautkan terus untuk akses PDF).';

        return redirect()->route('dashboard')->with('success', $msg);
    }

    public function show(Article $article)
    {
        $article->increment('views_count');
        $article->load('user');

        if ($this->hasCommentModel()) {
            $article->load('comments.user');
        } else {
            $article->setRelation('comments', collect());
        }

        $relatedArticles = Article::where('id', '!=', $article->id)
            ->when($article->category, fn ($q) => $q->where('category', $article->category))
            ->latest()
            ->take(5)
            ->get();

        return view('articles.show', compact('article', 'relatedArticles'));
    }

    public function edit(Article $article)
    {
        return view('articles.edit', compact('article'));
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:500',
            'author_name'      => 'required|string|max:500',
            'abstract'         => 'nullable|string',
            'keywords'         => 'nullable|string|max:500',
            'category'         => 'nullable|string|max:150',
            'publication_year' => 'nullable|string|max:10',
            'ojs_link'         => 'nullable|string|max:500',
            'pdf_file'         => 'nullable|file|mimes:pdf|max:20480',
        ]);

        if ($request->hasFile('pdf_file')) {
            if ($article->file_path && Storage::disk('public')->exists($article->file_path)) {
                Storage::disk('public')->delete($article->file_path);
            }
            $article->file_path = $request->file('pdf_file')->store('articles', 'public');
        }

        $ojsLink = trim((string) ($validated['ojs_link'] ?? ''));
        if ($ojsLink !== '' && !preg_match('#^https?://#i', $ojsLink)) {
            $ojsLink = 'https://' . ltrim($ojsLink, '/');
        }

        $article->update([
            'title'            => $validated['title'],
            'author_name'      => $validated['author_name'],
            'abstract'         => $validated['abstract'] ?? null,
            'keywords'         => $validated['keywords'] ?? null,
            'category'         => !empty($validated['category']) ? $validated['category'] : 'Umum',
            'publication_year' => $validated['publication_year'] ?? $article->publication_year,
            'ojs_link'         => $ojsLink !== '' ? $ojsLink : null,
            'file_path'        => $article->file_path,
        ]);

        return redirect()->route('articles.show', $article)->with('success', 'Artikel berjaya dikemas kini.');
    }

    public function destroy(Article $article)
    {
        if ($article->file_path && Storage::disk('public')->exists($article->file_path)) {
            Storage::disk('public')->delete($article->file_path);
        }

        $article->delete();

        return redirect()->route('dashboard')->with('success', 'Artikel telah dipadam.');
    }

    public function viewPdf(Article $article)
    {
        $rawPath = trim((string) ($article->file_path ?? ''));
        if ($rawPath !== '' && !preg_match('#^https?://#i', $rawPath)) {
            $cleanPath = ltrim($rawPath, '/');
            $cleanPath = preg_replace('#^storage/#i', '', $cleanPath);
            $cleanPath = preg_replace('#^public/#i', '', $cleanPath);
            $cleanPath = ltrim($cleanPath, '/');

            if ($cleanPath !== '' && Storage::disk('public')->exists($cleanPath)) {
                return response()->file(Storage::disk('public')->path($cleanPath));
            }
        }

        if ($article->resolved_external_url) {
            return redirect()->away($article->resolved_external_url);
        }

        return back()->with('error', 'Fail PDF bagi artikel ini tidak tersedia.');
    }

    public function download(Article $article)
    {
        $rawPath = trim((string) ($article->file_path ?? ''));
        if ($rawPath !== '' && !preg_match('#^https?://#i', $rawPath)) {
            $cleanPath = ltrim($rawPath, '/');
            $cleanPath = preg_replace('#^storage/#i', '', $cleanPath);
            $cleanPath = preg_replace('#^public/#i', '', $cleanPath);
            $cleanPath = ltrim($cleanPath, '/');

            if ($cleanPath !== '') {
                if (Storage::disk('public')->exists($cleanPath)) {
                    $article->increment('downloads_count');
                    return Storage::disk('public')->download($cleanPath);
                }

                if (Storage::disk('local')->exists($cleanPath)) {
                    $article->increment('downloads_count');
                    return Storage::disk('local')->download($cleanPath);
                }

                if (file_exists(public_path($cleanPath))) {
                    $article->increment('downloads_count');
                    return response()->download(public_path($cleanPath));
                }
            }
        }

        if ($article->resolved_external_url) {
            $article->increment('downloads_count');
            return redirect()->away($article->resolved_external_url);
        }

        return back()->with('error', 'Fail PDF atau pautan OJS bagi artikel ini tidak ditemui.');
    }

    public function authors(Request $request)
    {
        $articles = Article::whereNotNull('author_name')
            ->where('author_name', '!=', '')
            ->get(['id', 'author_name', 'category', 'publication_year', 'views_count', 'downloads_count']);

        $authorMap = [];

        foreach ($articles as $article) {
            $names = Article::parseAuthorNames($article->author_name);
            foreach ($names as $name) {
                $key = mb_strtolower($name);
                if (!isset($authorMap[$key])) {
                    $authorMap[$key] = (object) [
                        'author_name'     => $name,
                        'articles_count'  => 0,
                        'total_views'     => 0,
                        'total_downloads' => 0,
                        'latest_year'     => $article->publication_year,
                        'categories'      => [],
                    ];
                }

                $authorMap[$key]->articles_count++;
                $authorMap[$key]->total_views += (int) ($article->views_count ?? 0);
                $authorMap[$key]->total_downloads += (int) ($article->downloads_count ?? 0);

                if (!empty($article->publication_year) && $article->publication_year > $authorMap[$key]->latest_year) {
                    $authorMap[$key]->latest_year = $article->publication_year;
                }

                if (!empty($article->category) && !in_array($article->category, $authorMap[$key]->categories, true)) {
                    $authorMap[$key]->categories[] = $article->category;
                }
            }
        }

        $authors = collect(array_values($authorMap))
            ->sortByDesc('articles_count')
            ->values();

        if ($request->filled('search')) {
            $q = mb_strtolower(trim((string) $request->input('search')));
            $authors = $authors->filter(fn ($a) => str_contains(mb_strtolower($a->author_name), $q))->values();
        }

        return view('authors.index', compact('authors'));
    }

    public function analytics(Request $request)
    {
        $selectedYear     = $request->input('year');
        $selectedCategory = $request->input('category');
        $selectedSource   = $request->input('source');
        $topLimit         = (int) $request->input('limit', 10);
        if (!in_array($topLimit, [5, 10, 15, 20], true)) {
            $topLimit = 10;
        }

        $availableYears = Article::whereNotNull('publication_year')
            ->where('publication_year', '!=', '')
            ->distinct()
            ->orderByDesc('publication_year')
            ->pluck('publication_year');

        $availableCategories = Article::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $baseQuery = Article::query()
            ->when($selectedYear, fn ($q) => $q->where('publication_year', $selectedYear))
            ->when($selectedCategory, fn ($q) => $q->where('category', $selectedCategory))
            ->when($selectedSource === 'local', fn ($q) => $q->whereNotNull('file_path')->where('file_path', '!=', ''))
            ->when($selectedSource === 'ojs', fn ($q) => $q->where(fn ($sub) => $sub->whereNull('file_path')->orWhere('file_path', '')));

        $stats = [
            'total_articles'  => (clone $baseQuery)->count(),
            'total_views'     => (int) (clone $baseQuery)->sum('views_count'),
            'total_downloads' => (int) (clone $baseQuery)->sum('downloads_count'),
            'total_authors'   => (clone $baseQuery)->pluck('author_name')
                ->flatMap(fn ($s) => Article::parseAuthorNames($s))
                ->map(fn ($n) => mb_strtolower($n))
                ->unique()
                ->count(),
        ];

        $articlesByYear = (clone $baseQuery)
            ->select('publication_year', DB::raw('COUNT(*) as total'))
            ->whereNotNull('publication_year')
            ->where('publication_year', '!=', '')
            ->groupBy('publication_year')
            ->orderBy('publication_year')
            ->get();

        $articlesByCategory = (clone $baseQuery)
            ->select(DB::raw("COALESCE(NULLIF(category, ''), 'Umum / Lain-lain') as category"), DB::raw('COUNT(*) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $topViewedArticles = (clone $baseQuery)
            ->orderByDesc('views_count')
            ->take($topLimit)
            ->get();

        $topDownloadedArticles = (clone $baseQuery)
            ->orderByDesc('downloads_count')
            ->take($topLimit)
            ->get();

        $topAuthors = (clone $baseQuery)
            ->whereNotNull('author_name')
            ->pluck('author_name')
            ->flatMap(fn ($s) => Article::parseAuthorNames($s))
            ->countBy()
            ->sortDesc()
            ->take($topLimit)
            ->map(fn ($count, $name) => (object) ['author_name' => $name, 'total' => $count])
            ->values();

        $topBookmarkedArticles = $this->getBookmarkTable()
            ? (clone $baseQuery)->withCount('bookmarkedBy')->orderByDesc('bookmarked_by_count')->take($topLimit)->get()
            : collect();

        return view('dashboards.analytics', compact(
            'stats',
            'articlesByYear',
            'articlesByCategory',
            'topViewedArticles',
            'topDownloadedArticles',
            'topAuthors',
            'topBookmarkedArticles',
            'availableYears',
            'availableCategories',
            'selectedYear',
            'selectedCategory',
            'selectedSource',
            'topLimit'
        ));
    }

    public function toggleBookmark(Article $article)
    {
        $user = Auth::user();
        if (method_exists($user, 'bookmarks')) {
            $user->bookmarks()->toggle($article->id);
        } elseif ($table = $this->getBookmarkTable()) {
            $exists = DB::table($table)
                ->where('user_id', $user->id)
                ->where('article_id', $article->id)
                ->exists();

            if ($exists) {
                DB::table($table)
                    ->where('user_id', $user->id)
                    ->where('article_id', $article->id)
                    ->delete();
            } else {
                DB::table($table)->insert([
                    'user_id'    => $user->id,
                    'article_id' => $article->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return back();
    }

    public function storeComment(Request $request, Article $article)
    {
        $request->validate(['body' => 'required|string|max:2000']);

        if ($this->hasCommentModel()) {
            $article->comments()->create([
                'user_id' => Auth::id(),
                'body'    => $request->input('body'),
            ]);
        } else {
            $table = Schema::hasTable('article_comments') ? 'article_comments' : 'comments';
            if (Schema::hasTable($table)) {
                DB::table($table)->insert([
                    'article_id' => $article->id,
                    'user_id'    => Auth::id(),
                    'body'       => $request->input('body'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return back()->with('success', 'Ulasan berjaya dihantar.');
    }

    public function destroyComment($comment)
    {
        if (class_exists(\App\Models\ArticleComment::class)) {
            \App\Models\ArticleComment::findOrFail($comment)->delete();
        } elseif (class_exists(\App\Models\Comment::class)) {
            \App\Models\Comment::findOrFail($comment)->delete();
        } else {
            $table = Schema::hasTable('article_comments') ? 'article_comments' : 'comments';
            if (Schema::hasTable($table)) {
                DB::table($table)->where('id', $comment)->delete();
            }
        }

        return back()->with('success', 'Ulasan dipadam.');
    }

    public function exportCsv(Request $request)
    {
        $articles = Article::latest()->get();
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="articles_export.csv"',
        ];

        return response()->stream(function () use ($articles) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['ID', 'Tajuk', 'Penulis', 'Kategori', 'Tahun', 'Pautan OJS', 'Paparan', 'Muat Turun']);
            foreach ($articles as $a) {
                fputcsv($handle, [
                    $a->id,
                    $a->title,
                    $a->author_name,
                    $a->category,
                    $a->publication_year,
                    $a->ojs_link,
                    $a->views_count,
                    $a->downloads_count,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function fetchOjsMetadata(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        try {
            $meta = $this->extractOjsMetadataFromUrl($request->input('url'));
            return response()->json($meta);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal mencapai pautan OJS tersebut: ' . $e->getMessage()
            ], 422);
        }
    }

    private function extractOjsMetadataFromUrl(string $url): array
    {
        $response = Http::withoutVerifying()
            ->withOptions(['allow_redirects' => true])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->timeout(20)
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Pelayan OJS memulangkan status HTTP ' . $response->status());
        }

        $html = $response->body();

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $metas = [];
        foreach ($dom->getElementsByTagName('meta') as $metaTag) {
            $name = strtolower(trim($metaTag->getAttribute('name') ?: $metaTag->getAttribute('property')));
            $content = html_entity_decode(trim($metaTag->getAttribute('content')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($name !== '' && $content !== '') {
                $metas[$name][] = $content;
            }
        }

        // 1. Tajuk Artikel
        $title = $metas['citation_title'][0]
            ?? $metas['dc.title'][0]
            ?? $metas['og:title'][0]
            ?? '';

        if ($title === '') {
            $h1Node = $xpath->query('//h1[contains(@class,"page_title")] | //h1')->item(0);
            if ($h1Node) {
                $title = trim(preg_replace('/\s+/u', ' ', $h1Node->textContent));
            }
        }

        // 2. Nama Penulis
        $authorsList = $metas['citation_author'] ?? $metas['dc.creator'] ?? [];
        $authorName = implode('; ', array_unique(array_filter(array_map(
            fn ($a) => trim(preg_replace('/\s+/u', ' ', $a)),
            $authorsList
        ))));

        // 3. Kategori / Nama Jurnal
        $category = $metas['citation_journal_title'][0]
            ?? $metas['dc.source'][0]
            ?? $metas['og:site_name'][0]
            ?? '';

        if ($category === '') {
            $sectionNode = $xpath->query('//*[contains(@class,"item") and contains(@class,"section")]//*[contains(@class,"value")]')->item(0);
            if ($sectionNode) {
                $category = trim(preg_replace('/\s+/u', ' ', $sectionNode->textContent));
            }
        }

        // 4. Kata Kunci / Tags
        $keywordItems = $metas['citation_keywords']
            ?? $metas['dc.subject']
            ?? $metas['keywords']
            ?? [];

        if (empty($keywordItems)) {
            $kwNode = $xpath->query('//*[contains(@class,"item") and contains(@class,"keywords")]//*[contains(@class,"value")]')->item(0);
            if ($kwNode) {
                $keywordItems[] = trim(preg_replace('/\s+/u', ' ', $kwNode->textContent));
            }
        }

        $keywords = implode(', ', array_unique(array_filter(array_map(
            fn ($k) => trim($k, " \t\n\r\0\x0B,;"),
            $keywordItems
        ))));

        // 5. Tahun Diterbitkan
        $rawDate = $metas['citation_date'][0]
            ?? $metas['dc.date.issued'][0]
            ?? $metas['dc.date'][0]
            ?? $metas['article:published_time'][0]
            ?? '';

        $publicationYear = date('Y');
        if (preg_match('/(19|20)\d{2}/', $rawDate, $yrMatch)) {
            $publicationYear = $yrMatch[0];
        }

        // 6. Abstrak / Ringkasan
        $abstract = $metas['citation_abstract'][0]
            ?? $metas['dc.description'][0]
            ?? $metas['og:description'][0]
            ?? $metas['description'][0]
            ?? '';

        if (mb_strlen($abstract) < 30) {
            $absNode = $xpath->query('//*[contains(@class,"item") and contains(@class,"abstract")] | //*[@id="articleAbstract"]')->item(0);
            if ($absNode) {
                $absText = trim($absNode->textContent);
                $absText = preg_replace('/^(Abstract|Abstrak|Ringkasan)\s*:?\s*/iu', '', $absText);
                $absText = trim(preg_replace('/\s+/u', ' ', $absText));
                if ($absText !== '') {
                    $abstract = $absText;
                }
            }
        }

        // 7. Pautan PDF Galley OJS
        $pdfUrl = $this->resolveOjsPdfUrl($url, $metas, $xpath);

        return [
            'title'            => $title,
            'author_name'      => $authorName,
            'category'         => $category,
            'keywords'         => $keywords,
            'publication_year' => $publicationYear,
            'abstract'         => $abstract,
            'pdf_url'          => $pdfUrl,
            'ojs_link'         => $url,
        ];
    }

    private function resolveOjsPdfUrl(string $articleUrl, array $metas, \DOMXPath $xpath): string
    {
        $parsedBase = parse_url($articleUrl);
        $baseOrigin = ($parsedBase['scheme'] ?? 'https') . '://' . ($parsedBase['host'] ?? '');

        $normalizeUrl = function (string $href) use ($baseOrigin): string {
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href === '') {
                return '';
            }
            if (str_starts_with($href, '//')) {
                $href = 'https:' . $href;
            } elseif (str_starts_with($href, '/')) {
                $href = $baseOrigin . $href;
            }
            return $href;
        };

        if (!empty($metas['citation_pdf_url'][0])) {
            return $normalizeUrl($metas['citation_pdf_url'][0]);
        }

        $queries = [
            '//a[contains(@class,"obj_galley_link") and contains(@class,"pdf")]',
            '//a[contains(@class,"obj_galley_link")]',
            '//a[contains(@href,"/article/download/")]',
            '//a[contains(@href,"/article/view/")]',
        ];

        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            if (!$nodes) {
                continue;
            }
            foreach ($nodes as $node) {
                if (!($node instanceof \DOMElement)) {
                    continue;
                }
                $href = trim($node->getAttribute('href'));
                $text = strtolower(trim($node->textContent));

                if (preg_match('#/article/view/\d+/?$#i', $href)) {
                    continue;
                }

                if (
                    preg_match('#/article/(view|download)/\d+/\d+#i', $href)
                    || str_ends_with(strtolower($href), '.pdf')
                    || str_contains($text, 'pdf')
                    || str_contains($text, 'full text')
                    || str_contains($text, 'muat turun')
                ) {
                    $resolved = $normalizeUrl($href);
                    if ($resolved !== '') {
                        return $resolved;
                    }
                }
            }
        }

        return '';
    }

    private function downloadRemotePdf(string $pdfUrl, string $title, string $refererUrl = ''): ?string
    {
        $cookieJar = new CookieJar();
        $parsedBase = parse_url($pdfUrl);
        $baseOrigin = ($parsedBase['scheme'] ?? 'https') . '://' . ($parsedBase['host'] ?? '');

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
            'Accept'     => 'application/pdf,application/octet-stream,text/html,*/*;q=0.8',
            'Referer'    => $refererUrl !== '' ? $refererUrl : $pdfUrl,
        ];

        // Senarai calon URL untuk dicuba (download terus & halaman galley view)
        $candidates = array_unique(array_filter([
            preg_replace('#/article/view/(\d+/\d+(?:/\d+)?)#i', '/article/download/$1', trim($pdfUrl)),
            trim($pdfUrl),
        ]));

        // Lawati halaman utama artikel dahulu supaya sesi/cookie OJS sah
        if ($refererUrl !== '') {
            try {
                Http::withoutVerifying()
                    ->withOptions(['allow_redirects' => true, 'cookies' => $cookieJar])
                    ->withHeaders($headers)
                    ->timeout(15)
                    ->get($refererUrl);
            } catch (\Throwable $e) {
                // Abaikan jika gagal
            }
        }

        $isPdfPayload = function ($response): bool {
            if (!$response || !$response->successful()) {
                return false;
            }
            $body = $response->body();
            $ct = strtolower((string) $response->header('Content-Type'));
            return str_contains($ct, 'application/pdf') || str_contains(substr($body, 0, 2048), '%PDF-');
        };

        $savePdf = function (string $binary) use ($title): string {
            $slug = Str::slug(Str::limit($title, 60, '')) ?: 'article';
            $filename = 'articles/' . $slug . '-' . time() . '-' . Str::random(4) . '.pdf';
            Storage::disk('public')->put($filename, $binary);
            return $filename;
        };

        foreach ($candidates as $candidateUrl) {
            try {
                $res = Http::withoutVerifying()
                    ->withOptions(['allow_redirects' => true, 'cookies' => $cookieJar])
                    ->withHeaders($headers)
                    ->timeout(30)
                    ->get($candidateUrl);

                if ($isPdfPayload($res)) {
                    return $savePdf($res->body());
                }

                // Jika pelayan memulangkan halaman pemapar PDF.js OJS 3 (HTML), ekstrak pautan fail sebenar di dalamnya
                if ($res->successful()) {
                    $html = $res->body();
                    $nestedUrls = [];

                    // 1. Parameter viewer.html?file=https%3A%2F%2F...
                    if (preg_match_all('/[?&]file=([^"\'&\s<>]+)/i', $html, $mFile)) {
                        foreach ($mFile[1] as $encoded) {
                            $nestedUrls[] = urldecode(html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        }
                    }

                    // 2. Pautan terus /article/download/{id}/{galleyId}/{fileId} atau .pdf
                    if (preg_match_all('#(?:href|src)=["\']([^"\']+/article/download/[^"\']+|[^"\']+\.pdf(?:\?[^"\']*)?)["\']#i', $html, $mHref)) {
                        foreach ($mHref[1] as $rawHref) {
                            $nestedUrls[] = html_entity_decode($rawHref, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    }

                    foreach (array_unique($nestedUrls) as $innerUrl) {
                        $innerUrl = trim($innerUrl);
                        if ($innerUrl === '') {
                            continue;
                        }
                        if (str_starts_with($innerUrl, '//')) {
                            $innerUrl = 'https:' . $innerUrl;
                        } elseif (str_starts_with($innerUrl, '/')) {
                            $innerUrl = $baseOrigin . $innerUrl;
                        }

                        $res2 = Http::withoutVerifying()
                            ->withOptions(['allow_redirects' => true, 'cookies' => $cookieJar])
                            ->withHeaders(array_merge($headers, ['Referer' => $candidateUrl]))
                            ->timeout(30)
                            ->get($innerUrl);

                        if ($isPdfPayload($res2)) {
                            return $savePdf($res2->body());
                        }
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }
    public function bulkImport(Request $request)
    {
        // Mod 1: Permintaan AJAX JSON (1 item setiap kali untuk bar kemajuan)
        if ($request->expectsJson() || $request->isJson()) {
            try {
                $result = $this->importSingleBulkRecord([
                    'title'            => $request->input('title', ''),
                    'author_name'      => $request->input('author_name', ''),
                    'abstract'         => $request->input('abstract', ''),
                    'keywords'         => $request->input('keywords', ''),
                    'category'         => $request->input('category', ''),
                    'publication_year' => $request->input('publication_year', ''),
                    'ojs_link'         => $request->input('ojs_link', ''),
                ], $request->boolean('download_pdf', true));

                return response()->json($result);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'skipped' => false,
                    'title'   => $request->input('title') ?: $request->input('ojs_link') ?: 'Rekod',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        return back()->with('error', 'Sila gunakan butang Import Pukal pada papan pemuka.');
    }

    private function importSingleBulkRecord(array $input, bool $downloadPdf = true): array
    {
        $title   = trim((string) ($input['title'] ?? ''));
        $ojsLink = trim((string) ($input['ojs_link'] ?? ''));

        if ($ojsLink !== '' && !preg_match('#^https?://#i', $ojsLink)) {
            $ojsLink = 'https://' . ltrim($ojsLink, '/');
        }

        if ($title === '' && $ojsLink === '') {
            throw new \RuntimeException('Baris kosong tanpa tajuk atau pautan OJS.');
        }

        if ($ojsLink !== '') {
            $existingByUrl = Article::where('ojs_link', $ojsLink)->first();
            if ($existingByUrl) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'title'   => $existingByUrl->title,
                    'message' => 'Dilangkau (pautan OJS ini sudah direkodkan).',
                ];
            }
        }

        $authorName = trim((string) ($input['author_name'] ?? ''));
        $abstract   = trim((string) ($input['abstract'] ?? ''));
        $keywords   = trim((string) ($input['keywords'] ?? ''));
        $category   = trim((string) ($input['category'] ?? ''));
        $year       = trim((string) ($input['publication_year'] ?? ''));
        $pdfUrl     = '';

        if ($ojsLink !== '' && ($title === '' || $authorName === '' || $abstract === '' || $downloadPdf)) {
            try {
                $meta = $this->extractOjsMetadataFromUrl($ojsLink);
                $title      = $title !== '' ? $title : ($meta['title'] ?? '');
                $authorName = $authorName !== '' ? $authorName : ($meta['author_name'] ?? '');
                $abstract   = $abstract !== '' ? $abstract : ($meta['abstract'] ?? '');
                $keywords   = $keywords !== '' ? $keywords : ($meta['keywords'] ?? '');
                $category   = $category !== '' ? $category : ($meta['category'] ?? '');
                $year       = $year !== '' ? $year : ($meta['publication_year'] ?? '');
                $pdfUrl     = trim((string) ($meta['pdf_url'] ?? ''));
            } catch (\Throwable $e) {
                if ($title === '') {
                    throw new \RuntimeException($e->getMessage());
                }
            }
        }

        if ($title === '') {
            throw new \RuntimeException('Tajuk artikel tidak ditemui pada pautan tersebut.');
        }

        $existingByTitle = Article::where('title', $title)->first();
        if ($existingByTitle) {
            return [
                'success' => true,
                'skipped' => true,
                'title'   => $existingByTitle->title,
                'message' => 'Dilangkau (tajuk artikel yang sama sudah wujud).',
            ];
        }

        $filePath = null;
        if ($downloadPdf && ($pdfUrl !== '' || $ojsLink !== '')) {
            $filePath = $this->downloadRemotePdf($pdfUrl !== '' ? $pdfUrl : $ojsLink, $title, $ojsLink);
        }

        $article = Article::create([
            'user_id'          => Auth::id(),
            'title'            => Str::limit($title, 495, ''),
            'author_name'      => $authorName !== '' ? Str::limit($authorName, 495, '') : 'Tidak dinyatakan',
            'abstract'         => $abstract !== '' ? $abstract : null,
            'keywords'         => $keywords !== '' ? Str::limit($keywords, 495, '') : null,
            'category'         => $category !== '' ? Str::limit($category, 145, '') : 'Umum',
            'publication_year' => $year !== '' ? $year : date('Y'),
            'ojs_link'         => $ojsLink !== '' ? $ojsLink : null,
            'file_path'        => $filePath,
        ]);

        return [
            'success' => true,
            'skipped' => false,
            'title'   => $article->title,
            'message' => $filePath
                ? 'Berjaya disimpan berserta fail PDF.'
                : 'Berjaya disimpan (pautan OJS dipautkan).',
        ];
    }
    public function discoverOjsArticles(Request $request)
    {
        $request->validate(['url' => 'required|url']);
        @set_time_limit(120);

        $inputUrl = rtrim(trim((string) $request->input('url')), '/');

        // Jika sudah merupakan pautan artikel khusus (/article/view/286)
        if (preg_match('#^(https?://.+?/article/view/\d+)#i', $inputUrl, $m)) {
            return response()->json([
                'success' => true,
                'urls'    => [$m[1]],
                'total'   => 1,
            ]);
        }

        try {
            $articleUrls = [];
            $issueUrls   = [];

            $fetchHtml = function (string $targetUrl): string {
                $res = Http::withoutVerifying()
                    ->withOptions(['allow_redirects' => true])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
                        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->timeout(20)
                    ->get($targetUrl);

                return $res->successful() ? $res->body() : '';
            };

            $extractLinks = function (string $html, string $baseUrl) use (&$articleUrls, &$issueUrls) {
                if ($html === '') {
                    return;
                }
                $parsed = parse_url($baseUrl);
                $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

                if (preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
                    foreach ($matches[1] as $rawHref) {
                        $href = html_entity_decode(trim($rawHref), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        if (str_starts_with($href, '//')) {
                            $href = 'https:' . $href;
                        } elseif (str_starts_with($href, '/')) {
                            $href = $origin . $href;
                        }

                        // Tangkap semua pautan artikel (/article/view/{id})
                        if (preg_match('#^(https?://[^"\'\s<>]+/article/view/\d+)#i', $href, $artMatch)) {
                            $articleUrls[$artMatch[1]] = true;
                        }

                        // Tangkap semua pautan isu (/issue/view/{id})
                        if (preg_match('#^(https?://[^"\'\s<>]+/issue/view/\d+)#i', $href, $issMatch)) {
                            $issueUrls[$issMatch[1]] = true;
                        }
                    }
                }
            };

            // 1. Imbas halaman utama jurnal / isu yang ditampal
            $mainHtml = $fetchHtml($inputUrl);
            if ($mainHtml === '') {
                throw new \RuntimeException('Gagal mencapai laman web OJS tersebut.');
            }
            $extractLinks($mainHtml, $inputUrl);

            // 2. Imbas halaman arkib isu (/issue/archive) untuk dapatkan semua edisi terdahulu
            if (!preg_match('#/issue/view/\d+#i', $inputUrl)) {
                $journalBase = preg_replace('#/(issue|article|about|search|announcement).*$#i', '', $inputUrl);
                $archiveUrl  = rtrim($journalBase, '/') . '/issue/archive';
                $archiveHtml = $fetchHtml($archiveUrl);
                $extractLinks($archiveHtml, $archiveUrl);

                // Imbas halaman seterusnya dalam arkib jika ada (contoh: /issue/archive/2)
                if (preg_match_all('#href=["\']([^"\']+/issue/archive/\d+)["\']#i', $archiveHtml, $archPages)) {
                    $parsed = parse_url($archiveUrl);
                    $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                    foreach (array_slice(array_unique($archPages[1]), 0, 5) as $pHref) {
                        if (str_starts_with($pHref, '/')) {
                            $pHref = $origin . $pHref;
                        }
                        $extractLinks($fetchHtml($pHref), $pHref);
                    }
                }
            }

            // 3. Lawati setiap isu yang ditemui untuk mengumpul semua pautan artikel di dalamnya
            foreach (array_slice(array_keys($issueUrls), 0, 30) as $issUrl) {
                $extractLinks($fetchHtml($issUrl), $issUrl);
            }

            $finalUrls = array_keys($articleUrls);

            if (empty($finalUrls)) {
                throw new \RuntimeException('Tiada pautan artikel ditemui pada laman web OJS tersebut.');
            }

            return response()->json([
                'success' => true,
                'urls'    => $finalUrls,
                'total'   => count($finalUrls),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}