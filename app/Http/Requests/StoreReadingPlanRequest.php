<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認証は middleware('auth')。作成は全認証ユーザー可
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'book_id' => [
                'required', 'integer', 'exists:books,id',
                // 同一書籍で「未読/読書中/期限切れ」の計画が既存なら新規作成不可。
                // 読了(Completed)のみ新規を許可（再計画）。期限切れは新規でなく「編集で再開」する設計（Q62）。
                Rule::unique('reading_plans', 'book_id')
                    ->where('user_id', $this->user()->id)
                    ->whereIn('status', [
                        ReadingPlanStatus::Pending->value,
                        ReadingPlanStatus::Reading->value,
                        ReadingPlanStatus::Expired->value,
                    ]),
            ],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.unique' => 'この書籍には進行中の読書計画が既にあります。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['book_id' => '書籍', 'target_date' => '期日'];
    }
}
