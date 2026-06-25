<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 認可をバリデーションより前に評価する（非所有者には検証結果を見せず常に403）。
     * store では route('book') が null のため全認証ユーザー可。update は所有者のみ。
     */
    public function authorize(): bool
    {
        $book = $this->route('book');

        return $book === null || ($this->user()?->can('update', $book) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            // 編集時は自身を除外（store では route('book') が null で全件ユニーク判定）
            'isbn' => ['nullable', 'digits:13', Rule::unique('books', 'isbn')->ignore($this->route('book'))],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }
}
