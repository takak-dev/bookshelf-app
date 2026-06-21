<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/books');
Route::get('/books/isbn/{isbn}', [BookController::class, 'fetchByIsbn'])->name('books.isbn');
Route::resource('books', BookController::class);

// 後続Issueの確定ルート（URI・名前は固定。各Issueで本実装に置換）
Route::resource('genres', GenreController::class); // #5

Route::resource('books.reviews', ReviewController::class)
    ->shallow()
    ->only(['store', 'edit', 'update', 'destroy'])
    ->names(['store' => 'reviews.store']); // #6

Route::get('/favorites', [FavoriteController::class, 'index'])
    ->name('favorites.index');
Route::post('/books/{book}/favorite', [FavoriteController::class, 'toggle'])
    ->name('favorites.toggle'); // 7

Route::post('/reviews/{review}/like', [LikeController::class, 'toggle'])
    ->name('reviews.like');       // #8

Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index');                     // #9

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');             // #16
Route::get('/reading-plans', fn () => abort(404))->name('reading-plans.index'); // #18
Route::get('/notifications', fn () => abort(404))->name('notifications.index'); // #18
