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
        $users = User::all();

        Review::all()->each(function (Review $review) use ($users) {
            // 投稿者本人を除外＝自己いいね禁止と整合。0〜3人をランダムに
            $candidates = $users->where('id', '!=', $review->user_id);
            $count = random_int(0, min(3, $candidates->count()));

            if ($count > 0) {
                $review->likedByUsers()->syncWithoutDetaching($candidates->random($count)->pluck('id'));
            }
        });
    }
}
