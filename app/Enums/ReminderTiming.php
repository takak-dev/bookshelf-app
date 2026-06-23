<?php

namespace App\Enums;

enum ReminderTiming: string
{
    case ThreeDaysBefore = 'three_days_before'; // 期日3日前
    case OnDueDate = 'on_due_date';             // 当日
    case ThreeDaysAfter = 'three_days_after';   // 期日3日後（超過）

    /** 期日に対する相対日数（バッチの発火判定に使用）。 */
    public function daysFromDue(): int
    {
        return match ($this) {
            self::ThreeDaysBefore => -3,
            self::OnDueDate => 0,
            self::ThreeDaysAfter => 3,
        };
    }
}
