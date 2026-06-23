<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        // ?status= は有効なEnum値のときだけ絞り込み（不正値は全件＝寛容）
        $currentStatus = ReadingPlanStatus::tryFrom((string) $request->query('status'))?->value;

        $readingPlans = $request->user()->readingPlans()
            ->with('book')
            ->when($currentStatus, fn ($query) => $query->where('status', $currentStatus))
            ->orderBy('target_date')
            ->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    public function create(): View
    {
        $books = Book::orderBy('title')->get();

        return view('reading-plans.create', compact('books'));
    }

    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        // status は migration の既定（未読）、user_id はリレーションで付与
        $request->user()->readingPlans()->create($request->validated());

        return redirect()->route('reading-plans.index')->with('success', '読書計画を登録しました。');
    }

    public function edit(ReadingPlan $plan): View
    {
        $this->authorize('update', $plan);
        abort_if($plan->status === ReadingPlanStatus::Completed, 403); // 読了済みは編集不可（Q62・暫定）

        return view('reading-plans.edit', ['readingPlan' => $plan]);
    }

    public function update(UpdateReadingPlanRequest $request, ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);
        abort_if($plan->status === ReadingPlanStatus::Completed, 403);

        $validated = $request->validated();
        // 期限切れの計画は期日再設定で再開（未読へ戻す）（Q62）
        if ($plan->status === ReadingPlanStatus::Expired) {
            $validated['status'] = ReadingPlanStatus::Pending;
        }

        $plan->update($validated);

        return redirect()->route('reading-plans.index')->with('success', '読書計画を更新しました。');
    }

    public function destroy(ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);
        $plan->delete();

        return redirect()->route('reading-plans.index')->with('success', '読書計画を削除しました。');
    }

    public function complete(ReadingPlan $plan): RedirectResponse
    {
        $this->authorize('complete', $plan);
        abort_if($plan->status === ReadingPlanStatus::Completed, 403); // 読了済みは再読了不可（Q62）

        $plan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()->route('reading-plans.index')->with('success', '読了にしました。');
    }
}
