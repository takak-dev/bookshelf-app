<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
    }

    public function index(SearchBookRequest $request): View
    {
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'newest';

        $query = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->when(
                $filters['keyword'] ?? null,
                fn ($query, $keyword) => $query->where(
                    fn ($query) => $query
                        ->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%")
                )
            )
            ->when(
                $filters['genre'] ?? null,
                fn ($query, $genre) => $query->whereHas(
                    'genres',
                    fn ($query) => $query->where('genres.id', $genre)
                )
            );

        match ($sort) {
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            'rating' => $query->orderByDesc('reviews_avg_rating'),// MySQLはDESCでNULL最後
            default => $query->latest(),// 正規化済みだが安全網として残す
        };

        $books = $query->paginate(10)->appends($filters); // 検索条件(keyword/genre)をページネーションリンクへ引き継ぐ
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $book = $request->user()->books()->create($validated);
        $book->genres()->sync($validated['genres']);

        return redirect()->route('books.index')->with('success', '書籍を登録しました。');
    }

    public function show(Book $book): View
    {
        $book->load(['genres', 'reviews.user', 'reviews.likedByUsers']);

        return view('books.show', compact('book'));
    }

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);
        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);
        $validated = $request->validated();
        $book->update($validated);
        $book->genres()->sync($validated['genres']);

        return redirect()->route('books.show', $book)->with('success', '書籍を更新しました。');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
