<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author_name',
        'abstract',
        'keywords',
        'category',
        'publication_year',
        'file_path',
        'ojs_link',
        'doi',
        'journal_name',
        'views_count',
        'downloads_count',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        if (class_exists(\App\Models\ArticleComment::class)) {
            return $this->hasMany(\App\Models\ArticleComment::class)->latest();
        }

        if (class_exists(\App\Models\Comment::class)) {
            return $this->hasMany(\App\Models\Comment::class)->latest();
        }

        // Fallback selamat jika tiada model Comment/ArticleComment
        return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
    }

    public function bookmarkedBy()
    {
        $table = Schema::hasTable('article_bookmarks')
            ? 'article_bookmarks'
            : (Schema::hasTable('article_user') ? 'article_user' : 'bookmarks');

        return $this->belongsToMany(User::class, $table)->withTimestamps();
    }

    public static function parseAuthorNames(?string $rawString): array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', (string) $rawString));
        if (in_array(strtolower($raw), ['', 'tidak dinyatakan'], true)) {
            return [];
        }

        $isOjsSurname = function (string $part): bool {
            $part = trim($part);
            if ($part === '') {
                return false;
            }
            if (str_word_count($part) === 1) {
                return true;
            }
            return (bool) preg_match('/^(Syed|Che|Wan|Nik|Tengku|Raja|Ab|Abdul|Md|Mohd|Mohamed|Ku|Tuanku|Mat|Megat)\b/i', $part);
        };

        $formatOjsPair = function (string $part0, string $part1) use ($isOjsSurname): string {
            $part0 = trim($part0);
            $part1 = trim($part1);

            if (preg_match('/^(bin|binti|bt|b\.|abdul|ab\.|abd\.|a\/l|a\/p)\b/i', $part1)) {
                return trim($part0 . ' ' . $part1);
            }

            return $isOjsSurname($part0)
                ? trim($part1 . ' ' . $part0)
                : trim($part0 . ' ' . $part1);
        };

        $names = [];

        if (str_contains($raw, ';')) {
            foreach (explode(';', $raw) as $item) {
                $item = trim($item);
                if (substr_count($item, ',') === 1) {
                    [$last, $first] = array_map('trim', explode(',', $item, 2));
                    $item = $formatOjsPair($last, $first);
                }
                if ($item !== '') {
                    $names[] = $item;
                }
            }
        } elseif (str_contains($raw, ',')) {
            $parts = array_map('trim', explode(',', $raw));
            $chunks = array_chunk($parts, 2);
            $lastChunk = end($chunks);
            $allPairsComplete = is_array($lastChunk) && count($lastChunk) === 2;

            $hasOjsSurname = false;
            foreach ($chunks as $pair) {
                if ($isOjsSurname($pair[0])) {
                    $hasOjsSurname = true;
                    break;
                }
            }

            if ($allPairsComplete && $hasOjsSurname) {
                foreach ($chunks as $pair) {
                    $names[] = $formatOjsPair($pair[0], $pair[1]);
                }
            } else {
                foreach ($parts as $p) {
                    if ($p !== '') {
                        $names[] = $p;
                    }
                }
            }
        } else {
            $names[] = $raw;
        }

        return array_values(array_unique(array_filter(array_map('trim', $names))));
    }

    public function getParsedAuthorsAttribute(): array
    {
        return self::parseAuthorNames($this->author_name);
    }

    public function getHasLocalPdfAttribute(): bool
    {
        $rawPath = trim((string) ($this->file_path ?? ''));
        if ($rawPath === '' || preg_match('#^https?://#i', $rawPath)) {
            return false;
        }

        $cleanPath = ltrim($rawPath, '/');
        $cleanPath = preg_replace('#^storage/#i', '', $cleanPath);
        $cleanPath = preg_replace('#^public/#i', '', $cleanPath);
        $cleanPath = ltrim($cleanPath, '/');

        if ($cleanPath === '') {
            return false;
        }

        return Storage::disk('public')->exists($cleanPath)
            || Storage::disk('local')->exists($cleanPath)
            || file_exists(public_path($cleanPath));
    }

    public function getResolvedExternalUrlAttribute(): ?string
    {
        $rawPath = trim((string) ($this->file_path ?? ''));
        $candidate = preg_match('#^https?://#i', $rawPath)
            ? $rawPath
            : trim((string) ($this->ojs_link ?? ''));

        if ($candidate === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://' . ltrim($candidate, '/');
        }

        return $candidate;
    }
}