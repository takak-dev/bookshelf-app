<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

class ReadingReportService
{
    /**
     * ログインユーザーのレビューを母集団に、マイ読書レポートの統計を生成する。
     *
     * @return array<string, mixed>
     */
    public function generate(User $user): array
    {
        // 自分のレビューを書籍・ジャンル付きで取得（N+1回避）
        $reviews = $user->reviews()->with('book.genres')->get();

        return [
            'summary' => $this->summary($reviews),
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];
    }

    /**
     * 基本サマリー：総レビュー数・読了冊数（ユニーク書籍数）・平均評価。
     *
     * @param  Collection<int, Review>  $reviews
     * @return array<string, int|float>
     */
    private function summary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => round((float) $reviews->avg('rating'), 2),
        ];
    }

    /**
     * 評価分布：★1〜5の件数を index 0..4 のCollectionで返す。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, int>
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        return collect(range(1, 5))
            ->map(fn (int $rating) => $reviews->where('rating', $rating)->count());
    }

    /**
     * 高評価書籍TOP5：4★以上を評価降順に最大5件。
     * (user,book) 一意制約により1書籍=1レビュー＝rating はその値。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array<string, mixed>>
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review) => $review->rating >= 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(fn (Review $review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values();
    }

    /**
     * ジャンル別評価傾向TOP5：書籍のジャンルで束ね、平均評価の高い順に最大5件。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array<string, mixed>>
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            // 1レビューを書籍の各ジャンルへ展開（多対多）
            ->flatMap(fn (Review $review) => $review->book->genres->map(fn ($genre) => [
                'genre' => $genre,
                'rating' => $review->rating,
            ]))
            ->groupBy(fn (array $item) => $item['genre']->id)
            ->map(function (Collection $items) {
                $genre = $items->first()['genre'];

                return [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'count' => $items->count(),
                    'average_rating' => round((float) $items->avg('rating'), 2),
                ];
            })
            ->sortByDesc('average_rating')
            ->take(5)
            ->values();
    }
}
