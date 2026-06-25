<?php

use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ルート名は api.v1. を接頭辞に（web の resource('books') と books.index 等が衝突し route:cache が失敗するのを防ぐ）
Route::prefix('v1')->name('api.v1.')->group(function () {
    // トークン発行（メール/パスワード → Bearerトークン）総当たり緩和で6回/分
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:6,1');

    // 公開（読み取り）
    Route::apiResource('books', BookController::class)->only(['index', 'show']);

    // 認証必須（書き込み）。Sanctumトークン＋BookPolicyで所有者のみ
    Route::apiResource('books', BookController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('auth:sanctum');
});
