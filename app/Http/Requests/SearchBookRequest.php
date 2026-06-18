<?php

namespace App\Http\Requests;

use App\Models\Genre;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchBookRequest extends FormRequest
{
    /** 並び順の許可値。 */
    private const SORTS = ['newest', 'oldest', 'rating', 'title'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 改ざん・不正な検索パラメータを既定へ正規化する（Webは寛容フォールバック）。
     */
    protected function prepareForValidation(): void
    {
        $sort = $this->input('sort');
        $genre = $this->input('genre');

        $this->merge([
            // 許可外の sort は newest に倒す（422を出さない）
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'newest',
            // 存在しない genre は null＝フィルタ無効（全件表示）
            'genre' => (is_numeric($genre) && Genre::whereKey($genre)->exists()) ? (int) $genre : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTS)],
        ];
    }
}
