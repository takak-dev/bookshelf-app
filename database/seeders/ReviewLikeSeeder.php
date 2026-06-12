<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $reviews = Review::orderBy('id')->get();

        // review の id 昇順（ReviewSeeder の並び順）に対応する、いいねした user番号リスト。
        // 各レビュー0〜3人・著者本人は除外済み。
        $likesByReview = [
            [2, 3],     // 1: 吾輩は猫である / 山田
            [3, 4, 5],  // 2: 吾輩は猫である / 鈴木
            [1, 2],     // 3: 吾輩は猫である / 田中
            [4, 5],     // 4: 人を動かす / 山田
            [1],        // 5: 人を動かす / 佐藤
            [2],        // 6: 人を動かす / 高橋
            [3, 5],     // 7: リーダブルコード / 鈴木
            [2],        // 8: リーダブルコード / 田中
            [1, 2, 4],  // 9: リーダブルコード / 高橋
            [],         // 10: 7つの習慣 / 山田
            [5],        // 11: 7つの習慣 / 佐藤
            [4],        // 12: 坊っちゃん / 田中
            [3, 5],     // 13: 坊っちゃん / 佐藤
            [],         // 14: 坊っちゃん / 高橋
            [2, 3],     // 15: サピエンス全史 / 山田
            [1, 5],     // 16: サピエンス全史 / 鈴木
            [2],        // 17: サピエンス全史 / 田中
            [5],        // 18: Clean Code / 鈴木
            [2, 3],     // 19: Clean Code / 高橋
            [3],        // 20: 嫌われる勇気 / 山田
            [4, 5],     // 21: 嫌われる勇気 / 田中
            [],         // 22: 嫌われる勇気 / 佐藤
            [1, 3],     // 23: 嫌われる勇気 / 高橋
            [4],        // 24: 火花 / 鈴木
            [],         // 25: 火花 / 佐藤
            [3, 5],     // 26: FACTFULNESS / 山田
            [2],        // 27: FACTFULNESS / 田中
            [3],        // 28: FACTFULNESS / 高橋
            [],         // 29: コンテナ物語 / 鈴木
            [4],        // 30: コンテナ物語 / 田中
            [5],        // 31: コンテナ物語 / 佐藤
            [2, 3],     // 32: コンテナ物語 / 高橋
        ];

        foreach ($reviews as $index => $review) {
            $likerNos = $likesByReview[$index] ?? [];

            $likerIds = collect($likerNos)
                ->map(fn (int $no): int => $users[$no - 1]->id)
                ->all();

            $review->likedByUsers()->syncWithoutDetaching($likerIds);
        }
    }
}
