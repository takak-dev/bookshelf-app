<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BookSeeder の動作確認再現性テスト。
 * 登録者はランダム割当だが、書籍CRUD・自己レビュー禁止を毎回確認できるよう
 * 山田太郎が最低1冊は所有することを保証する。
 */
class BookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_yamada_owns_at_least_one_book(): void
    {
        $this->seed(); // DatabaseSeeder（User/Genre/Book/...）

        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();

        $this->assertTrue(
            Book::where('user_id', $yamada->id)->exists(),
            '山田太郎が所有する書籍が無い（書籍CRUD・自己レビュー禁止の動作確認が不能になる）',
        );
    }
}
