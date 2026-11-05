<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlanStatus;
use App\Http\Requests\Plan\PlanStoreRequest;
use App\Http\Requests\Plan\PlanUpdateRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $keyword = $request->input('keyword');
        $status = $request->input('status');

        $query = Plan::withCount('users');

        if (filled($keyword)) {
            $query->where('name', 'like', '%'.$keyword.'%');
        }

        if (filled($status)) {
            $query->where('status', $status);
        }

        $plans = $query->orderBy('sort_order', 'asc')
            ->paginate(20)
            ->withQueryString();

        return view('plan.management.index', compact('plans', 'keyword', 'status'));
    }

    public function create(): View
    {
        return View('plan.management.create');
    }

    public function store(PlanStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['status'] = PlanStatus::Draft;
        $validated['created_by_user_id'] = Auth::id();
        $validated['updated_by_user_id'] = Auth::id();

        Plan::create($validated);

        return redirect()->route('admin.plans.index')->with('success', 'プランを下書きで新規作成しました。');
    }

    public function show(Plan $plan): View
    {
        $plan->load(['users', 'createdBy', 'updatedBy']);

        return view('plan.management.show', compact('plan'));
    }

    public function edit(Plan $plan): View
    {
        return view('plan.management.edit', compact('plan'));
    }

    public function update(PlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validated();
        $validated['updated_by_user_id'] = Auth::id();

        $plan->update($validated);

        return redirect()->route('admin.plans.show', $plan)->with('success', 'プラン情報を更新しました。');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', 'プランを削除しました。');
    }

    public function publish(Plan $plan): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $plan->update(['status' => PlanStatus::Published, 'updated_by_user_id' => Auth::id()]);

        return redirect()->route('admin.plans.show', $plan)->with('success', 'プランを公開しました。');
    }

    public function archive(Plan $plan): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $plan->update(['status' => PlanStatus::Archived, 'updated_by_user_id' => Auth::id()]);

        return redirect()->route('admin.plans.show', $plan)->with('success', 'プランをアーカイブしました。');
    }

    public function unarchive(Plan $plan): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $plan->update(['status' => PlanStatus::Published, 'updated_by_user_id' => Auth::id()]);

        return redirect()->route('admin.plans.show', $plan)->with('success', 'プランのアーカイブを解除しました。');
    }
}
