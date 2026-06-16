<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function toggle(Request $request, Review $review): RedirectResponse
    {
        if ($review->user_id === $request->user()->id) {
            return redirect()->back()->with('error', '自分のレビューにはいいねできません。');
        }

        $request->user()->likedReviews()->toggle($review->id);

        return redirect()->back();
    }
}
