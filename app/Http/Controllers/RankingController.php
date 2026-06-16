<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        $rankedBooks = Book::query()
            ->whereHas('reviews')                       // レビュー0件は除外
            ->withCount('reviews')                      // reviews_count（表示＆タイブレーク）
            ->withAvg('reviews', 'rating')              // reviews_avg_rating（表示＆主ソート）
            ->orderByDesc('reviews_avg_rating')         // 平均評価 降順
            ->orderByDesc('reviews_count')              // 同点: レビュー件数多い順
            ->orderByDesc('created_at')                 // さらに同点: 新しい順
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
