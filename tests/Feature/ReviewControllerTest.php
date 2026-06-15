<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reviewData(array $overrides = []): array
    {
        return array_merge(['rating' => 4, 'comment' => '面白かった。'], $overrides);
    }

    public function test_guest_is_redirected_from_store_to_login(): void
    {
        $book = Book::factory()->create();

        $this->post(route('reviews.store', $book), $this->reviewData())
            ->assertRedirect('/login');
    }

    public function test_user_can_review_others_book(): void
    {
        $book = Book::factory()->create(); // 別ユーザー所有
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('reviews.store', $book), $this->reviewData())
            ->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);
    }

    public function test_cannot_review_own_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('reviews.store', $book), $this->reviewData())
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_cannot_review_same_book_twice(): void
    {
        $book = Book::factory()->create();
        $user = User::factory()->create();
        Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->actingAs($user)
            ->post(route('reviews.store', $book), $this->reviewData())
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_store_requires_rating_and_comment(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', $book), ['rating' => '', 'comment' => ''])
            ->assertSessionHasErrors(['rating', 'comment']);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', $book), $this->reviewData(['rating' => 6]))
            ->assertSessionHasErrors('rating');
    }

    public function test_non_author_gets_403_on_edit(): void
    {
        $review = Review::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('reviews.edit', $review))
            ->assertForbidden();
    }

    public function test_author_can_update_and_is_redirected_to_book_show(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();

        $this->actingAs($user)
            ->put(route('reviews.update', $review), $this->reviewData(['rating' => 5, 'comment' => '更新後のコメント']))
            ->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 5, 'comment' => '更新後のコメント']);
    }

    public function test_non_author_cannot_delete(): void
    {
        $review = Review::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();
    }

    public function test_author_can_delete(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
