<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ArticleController::class, 'index'])->name('home');
Route::get('/dashboard', [ArticleController::class, 'index'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Pengurusan Pengguna (Admin Sahaja)
    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users');
    Route::patch('/admin/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('admin.users.updateRole');

    // Cipta, Muat Naik & Import Pukal
    Route::get('/articles/create', [ArticleController::class, 'create'])->name('articles.create');
    Route::post('/articles/fetch-ojs', [ArticleController::class, 'fetchOjsMetadata'])->name('articles.fetchOjs');
    Route::post('/articles/bulk-import', [ArticleController::class, 'bulkImport'])->name('articles.bulk-import');
    Route::post('/articles', [ArticleController::class, 'store'])->name('articles.store');
    Route::post('/articles/discover-ojs', [ArticleController::class, 'discoverOjsArticles'])->name('articles.discoverOjs');

    // Edit, Update & Delete
    Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');

    // Pratonton PDF, Muat Turun & View Metadata (Susunan ini penting)
    Route::get('/articles/{article}/view-pdf', [ArticleController::class, 'viewPdf'])->name('articles.viewPdf');
    Route::get('/articles/{article}/download', [ArticleController::class, 'download'])->name('articles.download');
    Route::get('/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');

    // Bookmark & Eksport CSV
    Route::post('/articles/{article}/bookmark', [ArticleController::class, 'toggleBookmark'])->name('articles.bookmark');
    Route::get('/articles-export-csv', [ArticleController::class, 'exportCsv'])->name('articles.exportCsv');

    // Direktori Penulis & Analitik
    Route::get('/authors', [ArticleController::class, 'authors'])->name('authors.index');
    Route::get('/analytics', [ArticleController::class, 'analytics'])->name('dashboards.analytics');

    // Komen / Ulasan Artikel
    Route::post('/articles/{article}/comments', [ArticleController::class, 'storeComment'])->name('articles.comments.store');
    Route::delete('/comments/{comment}', [ArticleController::class, 'destroyComment'])->name('articles.comments.destroy');

    // Chatbot
    Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->name('chatbot.ask');

    // Laluan Profile Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
});

require __DIR__.'/auth.php';