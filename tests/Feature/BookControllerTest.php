<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    // 一覧は公開で、1ページ10件のページネーションになる
    public function test_index_is_public_and_paginates_10(): void
    {
        Book::factory()->count(12)->create();

        $response = $this->get(route('books.index'));

        $response->assertOk();
        $response->assertViewHas('books', fn ($books) => $books->count() === 10);
    }

    // 書籍詳細は公開（ゲストも閲覧可）
    public function test_show_is_public(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.show', $book))->assertOk();
    }

    // 未認証で登録画面に来ると /login へリダイレクトされる
    public function test_guest_is_redirected_from_create_to_login(): void
    {
        $this->get(route('books.create'))->assertRedirect('/login');
    }

    // 認証ユーザーは登録画面を表示できる
    public function test_auth_user_can_view_create(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('books.create'))
            ->assertOk();
    }

    // 認証ユーザーは書籍を登録でき、選択ジャンルが紐付く（登録者は認証ユーザー）
    public function test_auth_user_can_store_book_with_genres(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト本',
            'author' => 'テスト著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'description' => '説明',
            'image_url' => 'https://example.com/a.png',
            'genres' => $genres->pluck('id')->all(),
        ]);

        $response->assertRedirect(route('books.index'));
        $book = Book::firstWhere('isbn', '9781234567897');
        $this->assertNotNull($book);
        $this->assertSame($user->id, $book->user_id);
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $book->genres->pluck('id')->all());
    }

    // タイトル未入力では登録できずバリデーションエラーになる
    public function test_store_requires_title(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('books.store'), [
                'title' => '',
                'author' => '著者',
                'isbn' => '9781234567897',
                'published_date' => '2020-01-01',
                'genres' => [$genre->id],
            ])
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('books', 0);
    }

    // user_id をリクエストに混ぜても無視され、登録者は認証ユーザーになる（なりすまし防止）
    public function test_owner_field_cannot_be_overridden_by_request(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user)->post(route('books.store'), [
            'title' => '本',
            'author' => '著者',
            'isbn' => '9781234567897',
            'published_date' => '2020-01-01',
            'genres' => [$genre->id],
            'user_id' => $other->id, // 改ざん試行
        ]);

        $this->assertDatabaseHas('books', ['isbn' => '9781234567897', 'user_id' => $user->id]);
    }

    // 作成者でない人が編集画面に来ると 403
    public function test_non_owner_gets_403_on_edit(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('books.edit', $book))
            ->assertForbidden();
    }

    // 作成者でない人は更新できない（不正入力でも認可が検証より先＝422でなく403）
    public function test_non_owner_cannot_update_even_with_invalid_input(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('books.update', $book), ['title' => '']) // 不正入力
            ->assertForbidden();
    }

    // 作成者は更新でき、書籍詳細へリダイレクトされる
    public function test_owner_can_update_and_is_redirected_to_show(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($owner)->put(route('books.update', $book), [
            'title' => '更新後',
            'author' => $book->author,
            'isbn' => $book->isbn, // 自身のISBNは一意制約から除外される
            'published_date' => '2021-02-02',
            'genres' => [$genre->id],
        ]);

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新後']);
    }

    // 作成者でない人は削除できない（403）
    public function test_non_owner_cannot_delete(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('books.destroy', $book))
            ->assertForbidden();
    }

    // 作成者は削除でき、一覧へリダイレクトされる
    public function test_owner_can_delete_and_is_redirected_to_index(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }
}
