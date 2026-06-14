<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
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
Route::resource('books', BookController::class);

// 後続Issueの確定ルート（URI・名前は固定。各Issueで本実装に置換）
Route::resource('genres', GenreController::class);     // #5
Route::post('/books/{book}/reviews', fn () => abort(404))->name('reviews.store');       // #6
Route::get('/reviews/{review}/edit', fn () => abort(404))->name('reviews.edit');        // #6
Route::delete('/reviews/{review}', fn () => abort(404))->name('reviews.destroy');       // #6
Route::get('/favorites', fn () => abort(404))->name('favorites.index');                 // #7
Route::post('/books/{book}/favorite', fn () => abort(404))->name('favorites.toggle');   // #7
Route::post('/reviews/{review}/like', fn () => abort(404))->name('reviews.like');       // #8
Route::get('/ranking', fn () => abort(404))->name('ranking.index');                     // #9
