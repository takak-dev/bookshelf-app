<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessReadingPlans extends Command
{
    protected $signature = 'reading-plans:process';

    protected $description = '読書計画の自動失効とリマインダー通知を行う日次バッチ';

    public function handle(): int
    {
        $this->expireOverduePlans();
        $this->sendReminders();

        return self::SUCCESS;
    }

    /** 期日を過ぎた未完了(未読/読書中)を期限切れへ自動遷移（自動失効）。 */
    private function expireOverduePlans(): void
    {
        ReadingPlan::query()
            ->whereIn('status', [ReadingPlanStatus::Pending->value, ReadingPlanStatus::Reading->value])
            ->whereDate('target_date', '<', Carbon::today())
            ->update(['status' => ReadingPlanStatus::Expired->value]);
    }

    /** 3タイミング(期日-3/当日/+3)の対象計画にリマインダー通知（読了済みは除外）。 */
    private function sendReminders(): void
    {
        foreach (ReminderTiming::cases() as $timing) {
            // 例: ThreeDaysBefore(daysFromDue=-3) は target_date が today+3 のとき発火
            $fireDate = Carbon::today()->subDays($timing->daysFromDue());

            ReadingPlan::with('user', 'book')
                ->where('status', '!=', ReadingPlanStatus::Completed->value)
                ->whereDate('target_date', $fireDate)
                ->each(function (ReadingPlan $plan) use ($timing) {
                    // 冪等性: 同日に同じ計画・同じタイミングの通知が既にあれば送らない
                    // （バッチを同じ日に複数回実行しても通知は1回だけ）
                    $alreadySentToday = $plan->user->notifications()
                        ->where('type', ReadingPlanReminder::class)
                        ->where('data->reading_plan_id', $plan->id)
                        ->where('data->timing', $timing->value)
                        ->whereDate('created_at', Carbon::today())
                        ->exists();

                    if (! $alreadySentToday) {
                        $plan->user->notify(new ReadingPlanReminder($plan, $timing));
                    }
                });
        }
    }
}
