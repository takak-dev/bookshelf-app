<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $book = Book::factory()->create();

        $this->post(route('favorites.toggle', $book))->assertRedirect('/login');
        $this->get(route('favorites.index'))->assertRedirect('/login');
    }

    public function test_toggle_adds_then_removes_then_adds_again(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 追加
        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);

        // 解除
        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);

        // 再追加
        $this->actingAs($user)->post(route('favorites.toggle', $book));
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
    }

    public function test_index_shows_only_own_favorites_paginated_10(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(12)->create();
        $user->favoriteBooks()->attach($books->pluck('id'));

        // 他ユーザーのお気に入りは含めない
        $other = User::factory()->create();
        $other->favoriteBooks()->attach(Book::factory()->create());

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->count() === 10 && $books->total() === 12);
    }
}
