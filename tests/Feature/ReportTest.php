<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * マイ読書レポート（PG14 応用）のテスト。
 * 集計母集団はログインユーザー自身のレビュー（Q56）。
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        // 未認証はログインへリダイレクト
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_summary_counts_only_own_reviews(): void
    {
        $user = User::factory()->create();
        // 自分のレビュー3件（評価 5,3,1）＝3冊
        Review::factory()->create(['user_id' => $user->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'rating' => 3]);
        Review::factory()->create(['user_id' => $user->id, 'rating' => 1]);
        // 他人のレビューは母集団に含めない
        Review::factory()->create(['rating' => 4]);

        $stats = $this->actingAs($user)->get('/reports')->viewData('stats');

        // 総レビュー数・読了冊数（ユニーク書籍数）・平均評価
        $this->assertSame(3, $stats['summary']['total_reviews']);
        $this->assertSame(3, $stats['summary']['books_read']);
        $this->assertSame(3.0, $stats['summary']['average_rating']); // (5+3+1)/3
    }

    public function test_rating_distribution_counts_each_star(): void
    {
        $user = User::factory()->create();
        // ★5 を2件、★2 を1件
        Review::factory()->create(['user_id' => $user->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'rating' => 2]);

        $dist = $this->actingAs($user)->get('/reports')->viewData('stats')['rating_distribution'];

        // index0=★1 … index4=★5
        $this->assertSame([0, 1, 0, 0, 2], $dist->values()->all());
    }

    public function test_top_rated_books_returns_four_star_and_above_in_desc_order(): void
    {
        $user = User::factory()->create();
        $five = Book::factory()->create(['title' => 'Five']);
        $four = Book::factory()->create(['title' => 'Four']);
        $three = Book::factory()->create(['title' => 'Three']);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $five->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $four->id, 'rating' => 4]);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $three->id, 'rating' => 3]);

        $top = $this->actingAs($user)->get('/reports')->viewData('stats')['top_rated_books'];

        // 4★以上のみ・評価降順（3★は除外）
        $this->assertSame(['Five', 'Four'], $top->pluck('title')->all());
    }

    public function test_genre_ratings_aggregate_by_genre_in_desc_order(): void
    {
        $user = User::factory()->create();
        $tech = Genre::factory()->create(['name' => 'Tech']);
        $novel = Genre::factory()->create(['name' => 'Novel']);

        $bookA = Book::factory()->create();
        $bookA->genres()->attach($tech);
        $bookB = Book::factory()->create();
        $bookB->genres()->attach($novel);

        // Tech=5、Novel=2
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $bookA->id, 'rating' => 5]);
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $bookB->id, 'rating' => 2]);

        $genres = $this->actingAs($user)->get('/reports')->viewData('stats')['genre_ratings'];

        // 平均評価の高い順（Tech→Novel）、件数・平均も検証
        $this->assertSame(['Tech', 'Novel'], $genres->pluck('name')->all());
        $this->assertSame(5.0, $genres->firstWhere('name', 'Tech')['average_rating']);
        $this->assertSame(1, $genres->firstWhere('name', 'Tech')['count']);
    }

    public function test_empty_report_returns_zeroed_summary(): void
    {
        $user = User::factory()->create();

        // レビューが無いユーザー
        $stats = $this->actingAs($user)->get('/reports')->viewData('stats');

        $this->assertSame(0, $stats['summary']['total_reviews']);
        $this->assertSame(0.0, $stats['summary']['average_rating']);
        $this->assertCount(0, $stats['top_rated_books']);
    }
}
