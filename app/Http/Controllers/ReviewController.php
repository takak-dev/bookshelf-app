<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        try {
            $book->reviews()->create([
                'user_id' => $request->user()->id,
                'rating' => $request->validated('rating'),
                'comment' => $request->validated('comment'),
            ]);
        } catch (QueryException $e) {
            // 競合で validation をすり抜けた重複(unique違反)のみ握る。他のDBエラーは握り潰さない
            if (($e->errorInfo[1] ?? null) === 1062) {
                return redirect()->back()->with('error', 'この書籍には既にレビューを投稿しています。');
            }

            throw $e;
        }

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()->route('books.show', $review->book)->with('success', 'レビューを削除しました。');
    }
}
