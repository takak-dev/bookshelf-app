<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ReadingPlanSeeder の採点再現性テスト（Q65）。
 * 全状態・複数ユーザーのデータが揃うことを保証する。
 */
class ReadingPlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_reproduces_all_statuses_and_multiple_users(): void
    {
        $this->seed(); // DatabaseSeeder（User/Book/... + ReadingPlanSeeder）

        // 4状態すべてのレコードが存在する
        foreach (ReadingPlanStatus::cases() as $status) {
            $this->assertTrue(
                ReadingPlan::where('status', $status)->exists(),
                "状態 {$status->value} の読書計画が無い",
            );
        }

        // 主要シナリオは山田太郎に集約（複数件）
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $this->assertGreaterThan(1, $yamada->readingPlans()->count());

        // 認可確認用に別ユーザーの計画も存在する
        $this->assertTrue(
            ReadingPlan::where('user_id', '!=', $yamada->id)->exists(),
            '別ユーザーの読書計画が無い（403確認用）',
        );
    }
}
