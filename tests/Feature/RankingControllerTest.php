<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_is_public(): void
    {
        $this->get(route('ranking.index'))->assertOk();
    }

    public function test_excludes_books_without_reviews(): void
    {
        $reviewed = Book::factory()->create();
        Review::factory()->create(['book_id' => $reviewed->id, 'rating' => 5]);
        $noReviews = Book::factory()->create();

        $this->get(route('ranking.index'))
            ->assertViewHas('rankedBooks', fn ($books) => $books->contains($reviewed) && ! $books->contains($noReviews));
    }

    public function test_ordered_by_average_rating_desc(): void
    {
        $low = Book::factory()->create();
        Review::factory()->create(['book_id' => $low->id, 'rating' => 2]);

        $high = Book::factory()->create();
        Review::factory()->create(['book_id' => $high->id, 'rating' => 5]);

        $this->get(route('ranking.index'))
            ->assertViewHas('rankedBooks', fn ($books) => $books->first()->is($high) && $books->last()->is($low));
    }

    public function test_tiebreak_by_review_count_when_average_is_equal(): void
    {
        // 平均評価はどちらも 5。レビュー件数が多い方を上位にする。
        $fewer = Book::factory()->create();
        Review::factory()->count(2)->create(['book_id' => $fewer->id, 'rating' => 5]);

        $more = Book::factory()->create();
        Review::factory()->count(3)->create(['book_id' => $more->id, 'rating' => 5]);

        $this->get(route('ranking.index'))
            ->assertViewHas('rankedBooks', fn ($books) => $books->first()->is($more));
    }

    public function test_limited_to_top_10(): void
    {
        Book::factory()->count(12)->create()->each(
            fn (Book $book) => Review::factory()->create(['book_id' => $book->id, 'rating' => 5])
        );

        $this->get(route('ranking.index'))
            ->assertViewHas('rankedBooks', fn ($books) => $books->count() === 10);
    }
}
