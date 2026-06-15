<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // 認証は middleware('auth')。作成は全認証ユーザー可
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $book = $this->route('book');

            if ($book->user_id === $this->user()->id) {
                $validator->errors()->add('comment', '自分が登録した書籍にはレビューを投稿できません。');
            } elseif ($book->reviews()->where('user_id', $this->user()->id)->exists()) {
                $validator->errors()->add('comment', 'この書籍には既にレビューを投稿しています。');
            }
        });
    }
}
