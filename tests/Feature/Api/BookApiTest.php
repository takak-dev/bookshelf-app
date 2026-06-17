<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_books_with_aggregates(): void
    {
        Book::factory()->count(25)->create();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonCount(20, 'data') // デフォルト20件/ページ
            ->assertJsonStructure([
                'data' => [['id', 'title', 'author', 'isbn', 'average_rating', 'reviews_count', 'genres']],
                'links',
                'meta',
            ]);
    }

    public function test_index_does_not_include_reviews_array(): void
    {
        $book = Book::factory()->create();
        Review::factory()->create(['book_id' => $book->id]);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonMissingPath('data.0.reviews');
    }

    public function test_index_filters_by_keyword(): void
    {
        Book::factory()->create(['title' => 'ユニークタイトルABC', 'author' => 'X']);
        Book::factory()->create(['title' => 'その他', 'author' => 'Y']);

        $this->getJson('/api/v1/books?keyword=ABC')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ユニークタイトルABC');
    }

    public function test_index_filters_by_genre(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->create()->genres()->attach($genre);
        Book::factory()->create(); // ジャンル未紐付け

        $this->getJson("/api/v1/books?genre={$genre->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_respects_per_page(): void
    {
        Book::factory()->count(10)->create();

        $this->getJson('/api/v1/books?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_index_validates_search_params(): void
    {
        $this->getJson('/api/v1/books?per_page=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_show_returns_book_with_reviews(): void
    {
        $book = Book::factory()->create();
        $review = Review::factory()->create(['book_id' => $book->id, 'comment' => 'コメントX']);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.reviews.0.comment', 'コメントX')
            ->assertJsonPath('data.reviews.0.user_name', $review->user->name);
    }

    public function test_show_returns_404_for_missing_book(): void
    {
        $this->getJson('/api/v1/books/999999')->assertNotFound();
    }

    public function test_store_creates_book_and_returns_201(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', [
            'user_id' => $user->id,
            'title' => 'API本',
            'author' => '著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'API本');

        $this->assertDatabaseHas('books', ['isbn' => '9781234567897', 'user_id' => $user->id]);
    }

    public function test_store_validates_user_id_exists(): void
    {
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', [
            'user_id' => 999999, // 存在しないユーザー
            'title' => 'x',
            'author' => 'y',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_store_requires_title(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', [
            'user_id' => $user->id,
            'title' => '',
            'author' => 'y',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_update_allows_keeping_same_isbn(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();

        $this->putJson("/api/v1/books/{$book->id}", [
            'user_id' => $user->id,
            'title' => '更新後',
            'author' => 'y',
            'isbn' => $book->isbn, // 自身のISBNは一意制約から除外される
            'published_date' => '2021-01-01',
            'genres' => [$genre->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', '更新後');
    }

    public function test_destroy_returns_204(): void
    {
        $book = Book::factory()->create();

        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }
}
