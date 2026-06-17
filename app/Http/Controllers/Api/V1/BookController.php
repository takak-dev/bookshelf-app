<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookApiRequest;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $perpage = (int) $request->input('per_page', 20); // 1ページ件数。既定20・上限100は IndexBookRequest で検証

        $books = Book::query()
            ->with('genres')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->input('keyword');
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('genre'), function ($query) use ($request) {
                $query->whereHas('genres', fn ($q) => $q->where('genres.id', $request->input('genre')));
            })
            ->latest()
            ->paginate($perpage)
            ->appends($request->query()); // 検索条件(keyword/genre)をページネーションリンクへ引き継ぐ

        return BookResource::collection($books);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BookApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        // 基本APIは認証なしのため登録者IDをリクエストで受け取り exists で検証（PM#39。応用でSanctum認証ユーザーに変更）
        $book = DB::transaction(function () use ($validated) {
            $book = Book::create($validated);
            $book->genres()->sync($validated['genres']); // genres は books カラムでないため create では無視され、ここで中間テーブルへ同期

            return $book;
        });

        return (new BookResource($this->loadAggregates($book)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book): BookResource
    {
        return new BookResource($this->loadAggregates($book, true));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BookApiRequest $request, Book $book): BookResource
    {
        $validated = $request->validated();

        DB::transaction(function () use ($book, $validated) {
            $book->update($validated);
            $book->genres()->sync($validated['genres']);
        });

        return new BookResource($this->loadAggregates($book));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book): Response
    {
        $book->delete();

        return response()->noContent();
    }

    private function loadAggregates(Book $book, bool $loadUser = false): Book
    {
        $book->loadCount('reviews')->loadAvg('reviews', 'rating');

        $book->load('genres');

        if ($loadUser) {
            $book->load('reviews.user');
        }

        return $book;
    }
}
