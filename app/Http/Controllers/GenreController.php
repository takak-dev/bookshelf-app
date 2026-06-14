<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(GenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return redirect()->route('genres.index')->with('success', 'ジャンルを登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()->with('genres')->latest()->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(GenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->exists()) {
            return redirect()->route('genres.index')->with('error', 'このジャンルは書籍に紐づいているため、削除できません。');
        }
        $genre->delete();

        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
