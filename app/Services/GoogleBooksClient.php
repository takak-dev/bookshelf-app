<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleBooksClient
{
    /**
     * ISBN-13 で Google Books を検索し、書籍情報を返す。該当なし・通信失敗時は null。
     *
     * @return array<string, string|null>|null
     */
    public function findByIsbn(string $isbn): ?array
    {
        $query = ['q' => "isbn:{$isbn}"];
        if ($key = config('services.google_books.key')) {
            $query['key'] = $key; // キーは設定時のみ付与
        }

        $response = Http::get(config('services.google_books.endpoint'), $query);

        if ($response->failed()) {
            return null;
        }

        $volumeInfo = $response->json('items.0.volumeInfo');
        if (! is_array($volumeInfo)) {
            return null; // 該当なし
        }

        return [
            'title' => $volumeInfo['title'] ?? null,
            // authors は配列。複数は ", " 結合（点55c）
            'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : null,
            'description' => $volumeInfo['description'] ?? null,
            'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
            'published_date' => $volumeInfo['publishedDate'] ?? null,
        ];
    }
}
