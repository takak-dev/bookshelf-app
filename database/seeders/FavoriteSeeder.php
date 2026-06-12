<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $books = Book::orderBy('id')->get();

        // user番号(1-5) => お気に入りの book番号リスト（各3〜5冊）
        $favorites = [
            1 => [1, 3, 7, 10],
            2 => [2, 3, 5, 8],
            3 => [1, 6, 8, 11],
            4 => [4, 5, 9],
            5 => [3, 6, 7, 10, 11],
        ];

        foreach ($favorites as $userNo => $bookNos) {
            $bookIds = collect($bookNos)
                ->map(fn (int $no): int => $books[$no - 1]->id)
                ->all();

            $users[$userNo - 1]->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}
