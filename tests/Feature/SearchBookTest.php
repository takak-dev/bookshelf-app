<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 書籍一覧（PG01 応用）の検索・フィルタ・ソート機能のテスト。
 * 公開ページのため未認証でアクセスできる前提。
 */
class SearchBookTest extends TestCase
{
    use RefreshDatabase; // 各テストでDBを初期化

    public function test_keyword_matches_title_or_author(): void
    {
        // タイトル一致・著者一致・不一致の3冊を用意
        Book::factory()->create(['title' => 'Laravel Book', 'author' => 'Taylor']);
        Book::factory()->create(['title' => 'Other', 'author' => 'Laravel Fan']);
        Book::factory()->create(['title' => 'Unrelated', 'author' => 'Nobody']);

        // keyword=Laravel で検索（title または author の部分一致）
        $titles = $this->get('/books?keyword=Laravel')->viewData('books')->pluck('title');

        // title一致・author一致の2冊だけ返り、不一致は除外される（順不同で照合）
        $this->assertEqualsCanonicalizing(['Laravel Book', 'Other'], $titles->all());
    }

    public function test_genre_filters_books(): void
    {
        // 絞り込み対象ジャンルと別ジャンルを用意
        $target = Genre::factory()->create();
        $other = Genre::factory()->create();

        // それぞれに書籍を紐付け
        $hit = Book::factory()->create();
        $hit->genres()->attach($target);
        $miss = Book::factory()->create();
        $miss->genres()->attach($other);

        // genre=対象ID で絞り込む
        $ids = $this->get('/books?genre='.$target->id)->viewData('books')->pluck('id');

        // 対象ジャンルに紐づく書籍のみ返る
        $this->assertEquals([$hit->id], $ids->all());
    }

    public function test_nonexistent_genre_is_ignored(): void
    {
        // 書籍を2冊用意
        Book::factory()->count(2)->create();

        // 存在しないジャンルIDを指定（改ざん想定）
        $books = $this->get('/books?genre=999999')->viewData('books');

        // フィルタは無視され全件表示される（寛容フォールバック）
        $this->assertCount(2, $books);
    }

    public function test_sort_newest_is_default(): void
    {
        // 作成日時の異なる2冊（旧→新）
        $old = Book::factory()->create(['created_at' => now()->subDay()]);
        $new = Book::factory()->create(['created_at' => now()]);

        // 並び順未指定＝既定（新しい順）
        $ids = $this->get('/books')->viewData('books')->pluck('id');

        // 新しい順（新→旧）で並ぶ
        $this->assertEquals([$new->id, $old->id], $ids->all());
    }

    public function test_sort_oldest(): void
    {
        // 作成日時の異なる2冊
        $old = Book::factory()->create(['created_at' => now()->subDay()]);
        $new = Book::factory()->create(['created_at' => now()]);

        // sort=oldest で古い順
        $ids = $this->get('/books?sort=oldest')->viewData('books')->pluck('id');

        // 古い順（旧→新）で並ぶ
        $this->assertEquals([$old->id, $new->id], $ids->all());
    }

    public function test_sort_title(): void
    {
        // タイトルの異なる2冊（あえて逆順で作成）
        Book::factory()->create(['title' => 'Banana']);
        Book::factory()->create(['title' => 'Apple']);

        // sort=title でタイトル昇順
        $titles = $this->get('/books?sort=title')->viewData('books')->pluck('title');

        // A→B の昇順で並ぶ
        $this->assertEquals(['Apple', 'Banana'], $titles->all());
    }

    public function test_sort_rating_puts_books_without_reviews_last(): void
    {
        // 高評価・低評価・レビュー無しの3冊
        $high = Book::factory()->create();
        $low = Book::factory()->create();
        $none = Book::factory()->create();

        // 投稿者は Factory が別ユーザーを自動採番（自己レビュー制約に抵触しない）
        Review::factory()->create(['book_id' => $high->id, 'rating' => 5]);
        Review::factory()->create(['book_id' => $low->id, 'rating' => 2]);

        // sort=rating で評価が高い順
        $ids = $this->get('/books?sort=rating')->viewData('books')->pluck('id');

        // 高→低→（レビュー無しは最後）の順で並ぶ
        $this->assertEquals([$high->id, $low->id, $none->id], $ids->all());
    }

    public function test_invalid_sort_falls_back_to_newest_without_error(): void
    {
        // 作成日時の異なる2冊
        $old = Book::factory()->create(['created_at' => now()->subDay()]);
        $new = Book::factory()->create(['created_at' => now()]);

        // 許可外の sort 値（改ざん想定）でアクセス
        $response = $this->get('/books?sort=hacked');

        // 422にせず正常表示（Webは寛容フォールバック）
        $response->assertOk();

        // 既定の新しい順に倒れる
        $this->assertEquals([$new->id, $old->id], $response->viewData('books')->pluck('id')->all());
    }

    public function test_search_conditions_persist_in_pagination_links(): void
    {
        // ページネーションが発生するよう同一キーワードで11冊以上作成
        Book::factory()->count(15)->create(['title' => 'Match Book']);

        // keyword と sort を付けて検索
        $response = $this->get('/books?keyword=Match&sort=title');
        $response->assertOk();

        // ページネーションリンクに検索条件が引き継がれている（appends・第2引数falseで生HTML照合）
        $response->assertSee('keyword=Match', false);
        $response->assertSee('sort=title', false);
    }
}
