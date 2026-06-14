<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_index_to_login(): void
    {
        $this->get(route('genres.index'))->assertRedirect('/login');
    }

    public function test_index_shows_book_count(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(2)->create()->each(fn (Book $book) => $book->genres()->attach($genre));

        $this->actingAs(User::factory()->create())
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewHas('genres', fn ($genres) => $genres->firstWhere('id', $genre->id)->books_count === 2);
    }

    public function test_auth_user_can_store_genre(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('genres.store'), ['name' => '新ジャンル'])
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', ['name' => '新ジャンル']);
    }

    public function test_genre_name_must_be_unique(): void
    {
        Genre::factory()->create(['name' => '小説']);

        $this->actingAs(User::factory()->create())
            ->post(route('genres.store'), ['name' => '小説'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('genres', 1);
    }

    public function test_update_allows_keeping_same_name(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);

        $this->actingAs(User::factory()->create())
            ->put(route('genres.update', $genre), ['name' => '技術書'])
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '技術書']);
    }

    public function test_show_paginates_attached_books_10(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(12)->create()->each(fn (Book $book) => $book->genres()->attach($genre));

        $this->actingAs(User::factory()->create())
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->count() === 10);
    }

    public function test_destroy_is_rejected_when_genre_has_books(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->create()->genres()->attach($genre);

        $this->actingAs(User::factory()->create())
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_destroy_succeeds_when_genre_has_no_books(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'));

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }
}
