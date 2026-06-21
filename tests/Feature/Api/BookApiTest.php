<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    // ===== 読み取り（公開・認証不要） =====

    // 一覧APIは1ページ20件で、各書籍に集計値（平均評価・レビュー件数・ジャンル）を含む
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

    // 一覧では reviews 配列は含めない（詳細でのみ返す）
    public function test_index_does_not_include_reviews_array(): void
    {
        $book = Book::factory()->create();
        Review::factory()->create(['book_id' => $book->id]);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonMissingPath('data.0.reviews');
    }

    // keyword でタイトル/著者の部分一致検索ができる
    public function test_index_filters_by_keyword(): void
    {
        Book::factory()->create(['title' => 'ユニークタイトルABC', 'author' => 'X']);
        Book::factory()->create(['title' => 'その他', 'author' => 'Y']);

        $this->getJson('/api/v1/books?keyword=ABC')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ユニークタイトルABC');
    }

    // genre 指定でジャンル絞り込みができる
    public function test_index_filters_by_genre(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->create()->genres()->attach($genre);
        Book::factory()->create(); // ジャンル未紐付け

        $this->getJson("/api/v1/books?genre={$genre->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // per_page で1ページ件数を指定できる
    public function test_index_respects_per_page(): void
    {
        Book::factory()->count(10)->create();

        $this->getJson('/api/v1/books?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    // 検索パラメータも検証する（per_page 上限超過は 422）
    public function test_index_validates_search_params(): void
    {
        $this->getJson('/api/v1/books?per_page=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    // 詳細APIはジャンルとレビュー（投稿者名・コメント等）を含む
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

    // 存在しない書籍IDは 404 を返す
    public function test_show_returns_404_for_missing_book(): void
    {
        $this->getJson('/api/v1/books/999999')->assertNotFound();
    }

    // ===== 書き込み（Sanctum 認証必須） =====

    // 登録APIは認証ユーザーを所有者として書籍を作成し 201 を返す
    public function test_store_creates_book_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/books', [
            'title' => 'API本',
            'author' => '著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'API本');

        // 所有者は認証ユーザー（リクエスト値ではなくトークンから付与）
        $this->assertDatabaseHas('books', ['isbn' => '9781234567897', 'user_id' => $user->id]);
    }

    // 未認証の登録は 401
    public function test_store_requires_authentication(): void
    {
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', [
            'title' => 'x', 'author' => 'y', 'isbn' => '9781234567897',
            'published_date' => '2020-01-01', 'genres' => [$genre->id],
        ])->assertUnauthorized(); // 401
    }

    // 登録APIはタイトル必須（未入力は 422）
    public function test_store_requires_title(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $genre = Genre::factory()->create();

        $this->postJson('/api/v1/books', [
            'title' => '', 'author' => 'y', 'isbn' => '9781234567897',
            'published_date' => '2020-01-01', 'genres' => [$genre->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    // 所有者は自身のISBNを保持したまま更新できる
    public function test_update_by_owner_succeeds(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '更新後', 'author' => 'y', 'isbn' => $book->isbn,
            'published_date' => '2021-01-01', 'genres' => [$genre->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', '更新後');
    }

    // 他人の書籍の更新は 403（BookPolicy）
    public function test_update_by_non_owner_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs(User::factory()->create()); // 別ユーザー

        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '不正更新', 'author' => 'y', 'isbn' => $book->isbn,
            'published_date' => '2021-01-01', 'genres' => [$genre->id],
        ])->assertForbidden(); // 403
    }

    // 未認証の更新は 401
    public function test_update_requires_authentication(): void
    {
        $book = Book::factory()->create();

        $this->putJson("/api/v1/books/{$book->id}", [])->assertUnauthorized();
    }

    // 所有者は削除でき 204 を返す
    public function test_destroy_by_owner_succeeds(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    // 他人の書籍の削除は 403（BookPolicy）
    public function test_destroy_by_non_owner_is_forbidden(): void
    {
        $book = Book::factory()->create(['user_id' => User::factory()->create()->id]);
        Sanctum::actingAs(User::factory()->create()); // 別ユーザー

        $this->deleteJson("/api/v1/books/{$book->id}")->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    // 未認証の削除は 401
    public function test_destroy_requires_authentication(): void
    {
        $book = Book::factory()->create();

        $this->deleteJson("/api/v1/books/{$book->id}")->assertUnauthorized();
    }
}
