<?php

namespace Tests\Feature\Api;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 公開APIのトークン発行（Sanctum）のテスト。
 * Factory 既定のパスワードは 'password'。
 */
class TokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_invalid_credentials_return_422(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(422) // 資格情報不正は 422（ValidationException）
            ->assertJsonValidationErrors('email');
    }

    public function test_issued_token_can_access_write_endpoint(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 実トークンを直接発行（HTTP発行は test_valid_credentials_return_token で検証済み）。
        // 同一テストで2回HTTPすると Sanctum ガードが最初の未認証状態をキャッシュし
        // Bearer が無視されるため、ここはトークンを直接作り1リクエストで検証する。
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/books', [
                'title' => 'Token本',
                'author' => 'a',
                'isbn' => '9789999999999',
                'published_date' => '2020-01-01',
                'genres' => [$genre->id],
            ])
            ->assertCreated();
    }
}
