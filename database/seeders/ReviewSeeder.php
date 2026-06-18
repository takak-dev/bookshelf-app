<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        // 評価別コメント（5段階）
        $templates = [
            1 => '期待に届かなかった。',
            2 => 'いまひとつだった。',
            3 => '普通。可もなく不可もなく。',
            4 => '面白く、読んでよかった。',
            5 => '非常に良かった。おすすめの一冊。',
        ];

        foreach ($books as $book) {
            // 登録者(著者)を除外＝自己レビュー禁止と整合。そこから2〜4人をランダム抽出（重複なし）
            $candidates = $users->where('id', '!=', $book->user_id);
            $count = min($candidates->count(), random_int(2, 4));
            $reviewers = $candidates->random($count);

            foreach ($reviewers as $reviewer) {
                $rating = random_int(1, 5);
                Review::create([
                    'user_id' => $reviewer->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => $templates[$rating],
                ]);
            }
        }
    }
}
