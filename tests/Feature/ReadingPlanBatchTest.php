<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 日次バッチ（reading-plans:process）のテスト。
 * 自動失効バッチ＋リマインダーバッチ（3タイミング）を検証。
 */
class ReadingPlanBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_overdue_active_plans_only(): void
    {
        $overdue = ReadingPlan::factory()->create([
            'status' => ReadingPlanStatus::Pending,
            'target_date' => Carbon::today()->subDay(),
        ]);
        $future = ReadingPlan::factory()->create([
            'status' => ReadingPlanStatus::Pending,
            'target_date' => Carbon::today()->addDay(),
        ]);
        $completed = ReadingPlan::factory()->create([
            'status' => ReadingPlanStatus::Completed,
            'target_date' => Carbon::today()->subDays(5),
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        // 期日超過の未完了 → 期限切れ
        $this->assertSame(ReadingPlanStatus::Expired, $overdue->refresh()->status);
        // 未来の計画は変化なし
        $this->assertSame(ReadingPlanStatus::Pending, $future->refresh()->status);
        // 読了済みは失効しない
        $this->assertSame(ReadingPlanStatus::Completed, $completed->refresh()->status);
    }

    public function test_sends_three_days_before_reminder(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Pending,
            'target_date' => Carbon::today()->addDays(3),
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('three_days_before', $user->notifications()->first()->data['timing']);
    }

    public function test_sends_on_due_date_reminder(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Reading,
            'target_date' => Carbon::today(),
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame('on_due_date', $user->notifications()->first()->data['timing']);
    }

    public function test_sends_three_days_after_reminder_to_expired_plan(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Pending,
            'target_date' => Carbon::today()->subDays(3),
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        // 失効後も status != 読了 なのでリマインダー対象
        $this->assertSame('three_days_after', $user->notifications()->first()->data['timing']);
    }

    public function test_no_reminder_for_non_matching_date(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Pending,
            'target_date' => Carbon::today()->addDays(5), // どのタイミングにも一致しない
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_no_reminder_for_completed_plan(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Completed,
            'target_date' => Carbon::today(), // 当日だが読了済み
        ]);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_reminders_are_idempotent_on_same_day_rerun(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'status' => ReadingPlanStatus::Reading,
            'target_date' => Carbon::today(),
        ]);

        // 同じ日に2回実行しても通知は重複しない（冪等）
        $this->artisan('reading-plans:process')->assertSuccessful();
        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame(1, $user->notifications()->count());
    }
}
