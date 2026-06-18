<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date?->format('Y-m-d'), // 日付を文字列に変換（null 安全演算子）
            'description' => $this->description,
            'image_url' => $this->image_url,
            'average_rating' => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 2) : null, // withAvg の別名を小数2桁に整形
            'reviews_count' => $this->whenCounted('reviews'),
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')), // 詳細(show)でのみ返す。一覧では未ロード＝キー省略
            'created_at' => $this->created_at,
        ];
    }
}
