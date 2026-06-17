<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    // ログアウトするとセッションが破棄され、/login へリダイレクトされる
    public function test_logout_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    // 正しい入力で会員登録でき、認証状態になりユーザーがDBに保存される
    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => '新規太郎',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    // 名前が空だと登録できず name のバリデーションエラーになる（未認証のまま）
    public function test_registration_requires_name(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
    }

    // 既に使われているメールアドレスでは登録できない（email の一意制約）
    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->post('/register', [
            'name' => '重複太郎',
            'email' => 'dup@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // 正しいメール・パスワードでログインでき、その本人として認証される
    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    // 誤ったパスワードではログインに失敗し、エラー＋未認証になる
    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }
}
