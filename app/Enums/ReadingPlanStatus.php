<?php

namespace App\Enums;

enum ReadingPlanStatus: string
{
    case Pending = 'pending'; // 未読
    case Reading = 'reading'; // 読書中
    case Completed = 'completed'; // 読了
    case Expired = 'expired'; // 期限切れ

    /** 画面表示用の日本語ラベル。 */
    public function label(): string
    {
        return match ($this) {
            self::Pending => '未読',
            self::Reading => '読書中',
            self::Completed => '読了',
            self::Expired => '期限切れ',
        };
    }

    /** 一覧バッジの Tailwind クラス。 */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-800',
            self::Reading => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Expired => 'bg-red-100 text-red-800',
        };
    }
}
