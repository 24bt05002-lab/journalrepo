<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function ask(Request $request)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $userMessage = trim($request->input('message'));
            $lowerMessage = mb_strtolower($userMessage);
            $groqKey = config('services.groq.key');
            $currentLang = app()->getLocale() === 'en' ? 'Bahasa Inggeris (English)' : 'Bahasa Melayu';

            // 1. Statistik ringkas repositori
            $totalArticles = Article::count();
            $totalViews = (int) Article::sum('views_count');
            $totalDownloads = (int) Article::sum('downloads_count');

            // 2. Senarai perkataan umum (Stop Words Melayu & Inggeris) yang perlu diabaikan
            $stopWords = [
                'ada', 'tak', 'tidak', 'tiada', 'yang', 'untuk', 'dan', 'atau', 'ini', 'itu',
                'tentang', 'berkaitan', 'berkenaan', 'mengenai', 'pasal', 'cari', 'carikan',
                'artikel', 'jurnal', 'kajian', 'penyelidikan', 'penerbitan', 'rekod', 'senarai',
                'boleh', 'tolong', 'sila', 'bagitahu', 'beritahu', 'tunjuk', 'tunjukkan', 'berikan',
                'ringkaskan', 'rumuskan', 'jelaskan', 'terangkan', 'apa', 'apakah', 'siapa', 'siapakah',
                'berapa', 'berapakah', 'mana', 'manakah', 'bila', 'kenapa', 'mengapa', 'bagaimana',
                'saya', 'aku', 'kita', 'kami', 'anda', 'awak', 'dia', 'mereka', 'nak', 'mahu', 'ingin',
                'dalam', 'pada', 'dengan', 'dari', 'daripada', 'kepada', 'sebagai', 'adalah', 'ialah',
                'the', 'is', 'are', 'was', 'were', 'for', 'and', 'or', 'this', 'that', 'these', 'those',
                'about', 'related', 'regarding', 'find', 'search', 'show', 'list', 'give', 'tell',
                'article', 'articles', 'journal', 'paper', 'papers', 'study', 'studies', 'research',
                'can', 'could', 'would', 'please', 'summarize', 'explain', 'what', 'who', 'how', 'many',
                'any', 'some', 'all', 'with', 'from', 'into', 'have', 'has', 'do', 'does'
            ];

            // 3. Kamus singkatan & sinonim dwibahasa
            $synonymMap = [
                'ai'                      => ['ai', 'artificial intelligence', 'kecerdasan buatan', 'machine learning', 'deep learning', 'generative ai', 'chatgpt'],
                'artificial intelligence' => ['artificial intelligence', 'ai', 'kecerdasan buatan', 'machine learning', 'deep learning'],
                'kecerdasan buatan'       => ['kecerdasan buatan', 'artificial intelligence', 'ai', 'machine learning'],
                'ml'                      => ['ml', 'machine learning', 'pembelajaran mesin', 'deep learning'],
                'iot'                     => ['iot', 'internet of things', 'kebendaan internet'],
                'vr'                      => ['vr', 'virtual reality', 'realiti maya'],
                'ar'                      => ['ar', 'augmented reality', 'realiti terimbuh'],
                'ir4'                     => ['ir 4.0', 'ir4.0', 'industrial revolution', 'revolusi industri'],
                'ir40'                    => ['ir 4.0', 'ir4.0', 'industrial revolution', 'revolusi industri'],
                'islam'                   => ['islam', 'islamic', 'islami', 'syariah', 'shariah'],
                'islamic'                 => ['islamic', 'islam', 'syariah', 'shariah'],
                'pendidikan'              => ['pendidikan', 'education', 'educational', 'pembelajaran', 'pengajaran', 'pedagogi'],
                'education'               => ['education', 'pendidikan', 'learning', 'teaching'],
                'kewangan'                => ['kewangan', 'finance', 'financial', 'perbankan', 'banking', 'ekonomi'],
                'finance'                 => ['finance', 'financial', 'kewangan', 'banking', 'economic'],
                'kesihatan'               => ['kesihatan', 'health', 'healthcare', 'perubatan', 'medical', 'ubatan'],
                'health'                  => ['health', 'healthcare', 'kesihatan', 'medical'],
                'teknologi'               => ['teknologi', 'technology', 'digital', 'sistem', 'system'],
                'technology'              => ['technology', 'teknologi', 'digital', 'system'],
            ];

            // Ekstrak kata kunci bersih daripada mesej pengguna
            $cleanText = preg_replace('/[^\p{L}\p{N}\s.-]/u', ' ', $lowerMessage);
            $rawWords = collect(preg_split('/\s+/u', trim((string) $cleanText)))
                ->map(fn ($w) => trim($w, '.-'))
                ->filter(fn ($w) => mb_strlen($w) >= 2 && !in_array($w, $stopWords, true))
                ->unique()
                ->values();

            // Kembangkan kata kunci dengan sinonim/singkatan yang berkaitan
            $expandedTerms = collect();
            foreach ($rawWords as $word) {
                $expandedTerms->push($word);
                if (isset($synonymMap[$word])) {
                    foreach ($synonymMap[$word] as $syn) {
                        $expandedTerms->push($syn);
                    }
                }
            }

            foreach ($synonymMap as $phrase => $syns) {
                if (str_contains($phrase, ' ') && str_contains($lowerMessage, $phrase)) {
                    foreach ($syns as $syn) {
                        $expandedTerms->push($syn);
                    }
                }
            }

            $searchTerms = $expandedTerms->unique()->values();

            // 4. Cari & beri skor kerelevanan menggunakan sempadan perkataan tepat (elak padanan rambang seperti 'kajian' untuk 'ai')
            $hasMatchedSearch = false;
            $relevantArticles = collect();

            if ($searchTerms->isNotEmpty()) {
                // Tapis calon awal di SQL
                $candidates = Article::query()
                    ->where(function ($q) use ($searchTerms) {
                        foreach ($searchTerms as $term) {
                            $q->orWhere('title', 'like', "%{$term}%")
                              ->orWhere('keywords', 'like', "%{$term}%")
                              ->orWhere('abstract', 'like', "%{$term}%")
                              ->orWhere('author_name', 'like', "%{$term}%")
                              ->orWhere('category', 'like', "%{$term}%");
                        }
                    })
                    ->latest()
                    ->take(100)
                    ->get();

                $matchesTermExact = function (string $haystack, string $term): bool {
                    if ($haystack === '' || $term === '') {
                        return false;
                    }
                    // Gunakan sempadan perkataan penuh supaya 'ai' hanya padan dengan perkataan 'AI', bukan 'kajian' atau 'lain'
                    $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/iu';
                    return (bool) preg_match($pattern, $haystack);
                };

                $scored = $candidates->map(function ($article) use ($searchTerms, $matchesTermExact) {
                    $score = 0;
                    $title    = (string) ($article->title ?? '');
                    $keywords = (string) ($article->keywords ?? '');
                    $author   = (string) ($article->author_name ?? '');
                    $category = (string) ($article->category ?? '');
                    $abstract = (string) ($article->abstract ?? '');

                    foreach ($searchTerms as $term) {
                        if ($matchesTermExact($title, $term)) {
                            $score += 12;
                        }
                        if ($matchesTermExact($keywords, $term)) {
                            $score += 10;
                        }
                        if ($matchesTermExact($author, $term)) {
                            $score += 8;
                        }
                        if ($matchesTermExact($category, $term)) {
                            $score += 6;
                        }
                        if ($matchesTermExact($abstract, $term)) {
                            $score += 5;
                        }
                    }

                    $article->relevance_score = $score;
                    return $article;
                })
                ->filter(fn ($a) => $a->relevance_score > 0)
                ->sortByDesc(fn ($a) => [$a->relevance_score, $a->views_count ?? 0, $a->id])
                ->take(10)
                ->values();

                if ($scored->isNotEmpty()) {
                    $relevantArticles = $scored;
                    $hasMatchedSearch = true;
                }
            }

            // Jika pengguna bertanya soalan umum (contoh: artikel paling popular / terkini) tanpa kata kunci topik khusus
            if ($relevantArticles->isEmpty()) {
                $fallbackQuery = Article::query();
                if (str_contains($lowerMessage, 'popular') || str_contains($lowerMessage, 'paparan') || str_contains($lowerMessage, 'viewed')) {
                    $fallbackQuery->orderByDesc('views_count');
                } elseif (str_contains($lowerMessage, 'muat turun') || str_contains($lowerMessage, 'download')) {
                    $fallbackQuery->orderByDesc('downloads_count');
                } else {
                    $fallbackQuery->latest();
                }
                $relevantArticles = $fallbackQuery->take(10)->get();
            }

            $contextHeader = $hasMatchedSearch
                ? "HASIL CARIAN ARTIKEL YANG SEPADAN DENGAN PERTANYAAN PENGGUNA ({$relevantArticles->count()} artikel ditemui):"
                : "SENARAI ARTIKEL TERKINI / POPULAR DALAM REPOSITORI (Tiada padanan kata kunci khusus ditemui):";

            $articlesContext = $relevantArticles->map(function ($a) {
                $url = route('articles.show', $a->id);
                $abstract = Str::limit(strip_tags($a->abstract ?? 'Tiada abstrak'), 450);
                $kw = $a->keywords ? " | Kata Kunci: {$a->keywords}" : '';
                return "- Tajuk: {$a->title} | Penulis: {$a->author_name} | Tahun: {$a->publication_year} | Kategori: {$a->category}{$kw} | Paparan: {$a->views_count} | Muat Turun: {$a->downloads_count} | Pautan: {$url} | Abstrak: {$abstract}";
            })->implode("\n");

            $systemInstruction = <<<PROMPT
Anda ialah Pembantu AI Rasmi bagi Sistem Repositori Akademik ini.
Jawab soalan pengguna dalam {$currentLang} secara lalai (kecuali jika pengguna secara khusus menulis dalam bahasa lain) dengan ringkas, tepat, mesra, dan profesional.

STATISTIK REPOSITORI SEMASA:
- Jumlah Penerbitan: {$totalArticles}
- Jumlah Paparan: {$totalViews}
- Jumlah Muat Turun: {$totalDownloads}

{$contextHeader}
{$articlesContext}

PERATURAN KETAT:
1. HANYA rujuk artikel, penulis, dan statistik yang disenaraikan di atas. JANGAN reka tajuk artikel atau nama penulis yang tiada dalam senarai.
2. Apabila menyebut mana-mana artikel dari senarai di atas, sertakan pautannya dalam format Markdown: [Tajuk Artikel](Pautan).
3. Jika pengguna meminta ringkasan, ringkaskan berdasarkan abstrak yang diberikan.
4. Jika senarai di atas ditandakan sebagai "Tiada padanan kata kunci khusus ditemui" dan pengguna sedang mencari topik tertentu, nyatakan dengan sopan bahawa tiada artikel berkaitan topik tersebut dalam repositori ini, namun anda boleh mencadangkan artikel lain yang ada dalam senarai jika relevan.
PROMPT;

            if (empty($groqKey)) {
                return response()->json([
                    'reply' => '⚠️ **Ralat Konfigurasi:** `GROQ_API_KEY` kosong. Sila semak `.env` dan jalankan `php artisan config:clear`.',
                ]);
            }

            // 5. Pilih model aktif terbaik daripada akaun Groq anda
            $modelsResponse = Http::withoutVerifying()
                ->withToken($groqKey)
                ->timeout(10)
                ->get('https://api.groq.com/openai/v1/models');

            $activeModel = 'llama-3.1-8b-instant';
            if ($modelsResponse->successful()) {
                $chatModels = collect(data_get($modelsResponse->json(), 'data', []))
                    ->pluck('id')
                    ->filter(function ($id) {
                        $blocked = ['whisper', 'tts', 'guard', 'compound', 'orpheus', 'vision', 'embed', 'distil'];
                        foreach ($blocked as $word) {
                            if (str_contains(strtolower($id), $word)) {
                                return false;
                            }
                        }
                        return true;
                    })
                    ->values();

                $preferred = $chatModels->first(fn ($id) => str_contains(strtolower($id), 'llama-3.3-70b'))
                    ?? $chatModels->first(fn ($id) => str_contains(strtolower($id), 'llama-3.1-8b'))
                    ?? $chatModels->first(function ($id) {
                        $lower = strtolower($id);
                        return str_contains($lower, 'llama')
                            || str_contains($lower, 'qwen')
                            || str_contains($lower, 'gpt-oss')
                            || str_contains($lower, 'gemma')
                            || str_contains($lower, 'mixtral');
                    });

                $activeModel = $preferred ?? ($chatModels->first() ?? $activeModel);
            }

            // 6. Hantar soalan ke Groq API
            $response = Http::withoutVerifying()
                ->withToken($groqKey)
                ->timeout(25)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $activeModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemInstruction],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 800,
                ]);

            if ($response->successful()) {
                $reply = data_get($response->json(), 'choices.0.message.content');
                if ($reply) {
                    return response()->json(['reply' => $reply]);
                }
            }

            $errorDetail = data_get($response->json(), 'error.message', 'HTTP Status ' . $response->status());
            return response()->json([
                'reply' => "⚠️ **Ralat Groq API ({$activeModel}):** {$errorDetail}",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'reply' => "⚠️ **Ralat PHP (Baris {$e->getLine()}):** {$e->getMessage()}",
            ]);
        }
    }
}