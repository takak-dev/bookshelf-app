<?php

namespace App\Notifications;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    public function __construct(
        public readonly ReadingPlan $plan,
        public readonly ReminderTiming $timing,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // DatabaseChannel（notifications テーブルへ保存）
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $title = $this->plan->book->title;

        return [
            'reading_plan_id' => $this->plan->id,
            'book_title' => $title,
            'timing' => $this->timing->value, // Blade の $style 判定キー
            'title' => $this->titleFor(),
            'body' => $this->bodyFor($title),
        ];
    }

    private function titleFor(): string
    {
        return match ($this->timing) {
            ReminderTiming::ThreeDaysBefore => '読書計画のリマインダー',
            ReminderTiming::OnDueDate => '読書計画の期日です',
            ReminderTiming::ThreeDaysAfter => '読書計画の期限超過',
        };
    }

    private function bodyFor(string $title): string
    {
        $date = $this->plan->target_date->format('Y-m-d');

        return match ($this->timing) {
            ReminderTiming::ThreeDaysBefore => "「{$title}」の期日（{$date}）まであと3日です。",
            ReminderTiming::OnDueDate => "「{$title}」は本日（{$date}）が期日です。",
            ReminderTiming::ThreeDaysAfter => "「{$title}」は期日（{$date}）を過ぎています。",
        };
    }
}
