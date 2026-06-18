<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        $books = [
            ['title' => '吾輩は猫である', 'author' => '夏目漱石', 'isbn' => '9784101010014', 'published_date' => '1905-01-01',
                'description' => '猫の視点から人間社会を風刺的に描いた、夏目漱石の代表作。', 'genres' => ['小説']],
            ['title' => '人を動かす', 'author' => 'D・カーネギー', 'isbn' => '9784422100524', 'published_date' => '1936-10-01',
                'description' => '人間関係の原則を豊富な事例とともに説いた、自己啓発の古典的名著。', 'genres' => ['ビジネス', '自己啓発']],
            ['title' => 'リーダブルコード', 'author' => 'Dustin Boswell', 'isbn' => '9784873115658', 'published_date' => '2012-06-23',
                'description' => '読みやすく保守しやすいコードを書くための実践的な指針をまとめた一冊。', 'genres' => ['技術書']],
            ['title' => '7つの習慣', 'author' => 'スティーブン・R・コヴィー', 'isbn' => '9784863940246', 'published_date' => '2013-08-30',
                'description' => '原則中心の生き方を説いた、世界的ベストセラーの自己啓発書。', 'genres' => ['ビジネス', '自己啓発']],
            ['title' => '坊っちゃん', 'author' => '夏目漱石', 'isbn' => '9784101010021', 'published_date' => '1906-04-01', 'description' => '正義感あふれる青年教師の奮闘を痛快に描いた、夏目漱石の青春小説。', 'genres' => ['小説']],
            ['title' => 'サピエンス全史', 'author' => 'ユヴァル・ノア・ハラリ', 'isbn' => '9784309226712', 'published_date' => '2016-09-08', 'description' => '認知革命から現代まで、人類の歴史を壮大なスケールで描く話題作。', 'genres' => ['歴史', '科学']],
            ['title' => 'Clean Code', 'author' => 'Robert C. Martin', 'isbn' => '9784048930598', 'published_date' => '2017-12-18',
                'description' => '保守性の高いクリーンなコードを書くための原則と実践を解説する。', 'genres' => ['技術書']],
            ['title' => '嫌われる勇気', 'author' => '岸見一郎・古賀史健', 'isbn' => '9784478025819', 'published_date' => '2013-12-13',
                'description' => 'アドラー心理学のエッセンスを対話形式でわかりやすく解説した一冊。', 'genres' => ['自己啓発']],
            ['title' => '火花', 'author' => '又吉直樹', 'isbn' => '9784163902302', 'published_date' => '2015-03-11', 'description' => '芸人の世界を描き芥川賞を受賞した、又吉直樹のデビュー作。', 'genres' => ['小説']],
            ['title' => 'FACTFULNESS', 'author' => 'ハンス・ロスリング', 'isbn' => '9784822289607', 'published_date' => '2019-01-11',
                'description' => 'データに基づき世界を正しく見るための10の思考法を説く。', 'genres' => ['ビジネス', '科学']],
            ['title' => 'コンテナ物語', 'author' => 'マルク・レビンソン', 'isbn' => '9784822251468', 'published_date' => '2007-01-18',
                'description' => 'コンテナがいかに世界経済を変えたかを描いたノンフィクション。', 'genres' => ['ビジネス', '歴史']],
        ];
        /**
         * ※ 変数名の注意
         * ループ側を $book にすると、firstOrCreate() で生成される
         * Modelオブジェクトによって変数が上書きされ、型エラーを引き起こすため
         * 配列側はあえて $data として区別しています。
         */
        foreach ($books as $index => $data) {
            $book = Book::firstOrCreate(
                ['isbn' => $data['isbn']],
                [
                    'title' => $data['title'],
                    'author' => $data['author'],
                    'published_date' => $data['published_date'],
                    'description' => $data['description'],
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1),
                    'user_id' => $users->random()->id,
                ],
            );

            $genreIds = Genre::whereIn('name', $data['genres'])->pluck('id');
            $book->genres()->sync($genreIds);
        }
    }
}
