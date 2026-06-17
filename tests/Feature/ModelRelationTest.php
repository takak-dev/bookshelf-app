<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationTest extends TestCase
{
    use RefreshDatabase;

    // Book → 登録ユーザー（belongsTo user）
    public function test_book_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertTrue($book->user->is($user));
    }

    // Book → レビュー（hasMany reviews）
    public function test_book_has_many_reviews(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(2)->create(['book_id' => $book->id]);

        $this->assertCount(2, $book->reviews);
        $this->assertInstanceOf(Review::class, $book->reviews->first());
    }

    // Book ↔ ジャンル（belongsToMany genres・中間 book_genre）
    public function test_book_belongs_to_many_genres(): void
    {
        $book = Book::factory()->create();
        $book->genres()->attach(Genre::factory()->count(2)->create());

        $this->assertCount(2, $book->genres);
        $this->assertInstanceOf(Genre::class, $book->genres->first());
    }

    // Book ↔ お気に入り登録したユーザー（belongsToMany favorites）
    public function test_book_favorited_by_users(): void
    {
        $book = Book::factory()->create();
        $book->favoritedByUsers()->attach(User::factory()->count(2)->create());

        $this->assertCount(2, $book->favoritedByUsers);
    }

    // User → 登録書籍・投稿レビュー（hasMany books / reviews）
    public function test_user_has_many_books_and_reviews(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(2)->for($user)->create();
        Review::factory()->for($user)->create();

        $this->assertCount(2, $user->books);
        $this->assertCount(1, $user->reviews);
    }

    // User ↔ お気に入り書籍・いいねレビュー（belongsToMany favorites / review_likes）
    public function test_user_favorite_books_and_liked_reviews(): void
    {
        $user = User::factory()->create();
        $user->favoriteBooks()->attach(Book::factory()->count(2)->create());
        $user->likedReviews()->attach(Review::factory()->create());

        $this->assertCount(2, $user->favoriteBooks);
        $this->assertCount(1, $user->likedReviews);
    }

    // Review → 投稿者・対象書籍（belongsTo user / book）
    public function test_review_belongs_to_user_and_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->assertTrue($review->user->is($user));
        $this->assertTrue($review->book->is($book));
    }

    // Review ↔ いいねしたユーザー（belongsToMany review_likes）
    public function test_review_liked_by_users(): void
    {
        $review = Review::factory()->create();
        $review->likedByUsers()->attach(User::factory()->count(3)->create());

        $this->assertCount(3, $review->likedByUsers);
    }

    // Genre ↔ 書籍（belongsToMany books）
    public function test_genre_belongs_to_many_books(): void
    {
        $genre = Genre::factory()->create();
        $genre->books()->attach(Book::factory()->count(2)->create());

        $this->assertCount(2, $genre->books);
        $this->assertInstanceOf(Book::class, $genre->books->first());
    }
}
