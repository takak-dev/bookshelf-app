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
            ->search($request->input('keyword'), $request->input('genre'))
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
        // 登録者は Sanctum 認証ユーザー（user_id 詐称不可・PM#39）
        $book = DB::transaction(function () use ($request, $validated) {
            $book = $request->user()->books()->create($validated);
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
        $this->authorize('update', $book); // BookPolicy：非所有者は403
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
        $this->authorize('delete', $book); // BookPolicy：非所有者は403
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
