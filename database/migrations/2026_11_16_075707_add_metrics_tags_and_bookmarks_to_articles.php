<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (!Schema::hasColumn('articles', 'keywords')) {
                $table->string('keywords')->nullable()->after('category');
            }
            if (!Schema::hasColumn('articles', 'views_count')) {
                $table->unsignedInteger('views_count')->default(0)->after('file_path');
            }
            if (!Schema::hasColumn('articles', 'downloads_count')) {
                $table->unsignedInteger('downloads_count')->default(0)->after('views_count');
            }
        });

        if (!Schema::hasTable('article_user_bookmarks')) {
            Schema::create('article_user_bookmarks', function (Blueprint $table) {$table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');$table->foreignId('article_id')->constrained()->onDelete('cascade');
                $table->timestamps();$table->unique(['user_id', 'article_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('article_user_bookmarks');
        Schema::table('articles', function (Blueprint $table) {$table->dropColumn(['keywords', 'views_count', 'downloads_count']);
        });
    }
};