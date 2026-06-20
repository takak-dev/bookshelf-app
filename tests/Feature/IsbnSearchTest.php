<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ISBN検索（Google Books API連携・PG03応用）のテスト。
 * 外部APIは Http::fake() でモックし、安定したテストにする（要件シート10）。
 */
class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        // 未認証アクセスはログインへリダイレクト（コントローラの auth ミドルウェア）
        $this->get('/books/isbn/9784101010014')->assertRedirect('/login');
    }

    public function test_returns_book_info_from_google_books(): void
    {
        // Google Books の成功応答をモック
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => '吾輩は猫である',
                        'authors' => ['夏目漱石'],
                        'description' => '猫の視点の小説。',
                        'imageLinks' => ['thumbnail' => 'https://example.com/cover.jpg'],
                        'publishedDate' => '1905-01-01',
                    ],
                ]],
            ], 200),
        ]);

        // 認証ユーザーで ISBN 検索
        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784101010014');

        // volumeInfo がフォーム用キーにマッピングされて返る
        $response->assertOk();
        $response->assertExactJson([
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
            'description' => '猫の視点の小説。',
            'image_url' => 'https://example.com/cover.jpg',
            'published_date' => '1905-01-01',
        ]);
    }

    public function test_joins_multiple_authors_with_comma(): void
    {
        // authors が複数の応答をモック
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [['volumeInfo' => [
                    'title' => '共著本',
                    'authors' => ['著者A', '著者B'],
                ]]],
            ], 200),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784000000000');

        // author は ", " 区切りで結合される
        $response->assertOk();
        $response->assertJson(['author' => '著者A, 著者B']);
    }

    public function test_returns_error_when_book_not_found(): void
    {
        // 該当なし（items を含まない応答）をモック
        Http::fake([
            'www.googleapis.com/*' => Http::response(['totalItems' => 0], 200),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784000000000');

        // エラー画面に遷移せず、error キーで返す（フォームは空のまま・Q55）
        $response->assertOk();
        $response->assertJsonStructure(['error']);
    }

    public function test_returns_error_when_api_fails(): void
    {
        // 外部APIが500を返すケースをモック
        Http::fake([
            'www.googleapis.com/*' => Http::response([], 500),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/9784101010014');

        // 通信失敗時も error キーで返す（フロー継続）
        $response->assertOk();
        $response->assertJsonStructure(['error']);
    }

    public function test_rejects_invalid_isbn_format(): void
    {
        // 13桁数字でない ISBN は外部APIを呼ばず弾く
        Http::fake();

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/books/isbn/12345');

        // サーバ側バリデーションで422
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);

        // 外部APIは呼ばれていない
        Http::assertNothingSent();
    }
}
