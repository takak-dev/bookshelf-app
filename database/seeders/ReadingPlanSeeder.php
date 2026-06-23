<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * 採点時に読書計画の全挙動を再現できるダミーデータ（Carbon::today() 相対で日付固定に依存しない）。
     * 主要シナリオは山田太郎に集約。バッチ(reading-plans:process)実行で失効/通知の効果を確認可能。
     */
    public function run(): void
    {
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();
        $books = Book::all();
        $today = Carbon::today();

        // [status, target_date, completed_at]（確認ポイントはコメント）
        $scenarios = [
            [ReadingPlanStatus::Pending,   $today->copy()->addDays(3), null],         // バッチで three_days_before 発火
            [ReadingPlanStatus::Reading,   $today->copy(),             null],         // バッチで on_due_date 発火
            [ReadingPlanStatus::Pending,   $today->copy()->subDays(3), null],         // 自動失効＋three_days_after 発火
            [ReadingPlanStatus::Pending,   $today->copy()->subDay(),   null],         // 自動失効のみ（リマインダー無し）
            [ReadingPlanStatus::Reading,   $today->copy()->addDays(10), null],        // 未来・発火しない
            [ReadingPlanStatus::Completed, $today->copy()->subDays(2), $today->copy()->subDays(2)], // 読了・対象外
            [ReadingPlanStatus::Expired,   $today->copy()->subDays(7), null],         // 期限切れ表示（事前設定）
        ];

        foreach ($scenarios as $i => [$status, $targetDate, $completedAt]) {
            ReadingPlan::create([
                'user_id' => $yamada->id,
                'book_id' => $books[$i % $books->count()]->id, // 別々の書籍（未完了の重複を避ける）
                'target_date' => $targetDate,
                'status' => $status,
                'completed_at' => $completedAt,
            ]);
        }

        // 別ユーザー（鈴木花子）：所有者認可(403)の確認用
        ReadingPlan::create([
            'user_id' => $suzuki->id,
            'book_id' => $books->first()->id,
            'target_date' => $today->copy()->addDays(5),
            'status' => ReadingPlanStatus::Pending,
        ]);
    }
}
