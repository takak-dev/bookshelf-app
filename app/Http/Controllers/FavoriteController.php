<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $books = $request->user()->favoriteBooks()
            ->orderByPivot('created_at', 'desc')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $result = $request->user()->favoriteBooks()->toggle($book->id);

        $message = count($result['attached']) > 0 ? 'お気に入りに追加しました。' : 'お気に入りから削除しました。';

        return redirect()->back()->with('success', $message);
    }
}
