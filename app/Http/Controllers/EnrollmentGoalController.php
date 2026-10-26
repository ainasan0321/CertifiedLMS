<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EnrollmentGoal;
use App\Models\Enrollment;
use App\Http\Requests\Enrollment\EnrollmentGoalStoreRequest;
use App\Http\Requests\Enrollment\EnrollmentGoalUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollmentGoalController extends Controller
{
    public function store(EnrollmentGoalStoreRequest $request, Enrollment $enrollment): RedirectResponse
    {
        // 認可
        $this->authorize('create', [EnrollmentGoal::class, $enrollment]);

        // バリデーションチェック
        $validated = $request->validated();

        $goal = EnrollmentGoal::create([
            'enrollment_id' => $enrollment->id,
            'title' => $validated['title'],
            'target_date' => $validated['target_date'],
            'description' => $validated['description'],
        ]);

        return redirect()->back()->with('success', '目標を追加しました。');
    }

    public function edit(EnrollmentGoal $goal): View
    {
        // 認可
        $this->authorize('update', $goal);

        return view('enrollment-goal.edit', compact('goal'));

    }

    public function update(EnrollmentGoalUpdateRequest $request, EnrollmentGoal $goal): RedirectResponse
    {
        // 認可
        $this->authorize('update', $goal);

        // バリデーションチェック
        $validated = $request->validated();

        $goal->update([
            'title' => $validated['title'],
            'target_date' => $validated['target_date'],
            'description' => $validated['description'],
        ]);

        return redirect()->route('learning.enrollments.show', $goal->enrollment_id)
            ->with('success', '目標を更新しました。');
    }

    public function destroy(EnrollmentGoal $goal): RedirectResponse
    {
        // 認可
        $this->authorize('delete', $goal);

        $goal->delete();

        return redirect()->back()->with('success', '目標を削除しました。');
    }

    public function markAchieved(EnrollmentGoal $goal): RedirectResponse
    {
        // 認可
        $this->authorize('markAchieved', $goal);

        $goal->update(['achieved_at', now()]);

        return redirect()->back()->with('success', '目標を達成しました');
    }

    public function unmarkAchieved(EnrollmentGoal $goal): RedirectResponse
    {
        // 認可
        $this->authorize('unmarkAchieved', $goal);

        $goal->update(['achieved_at', null]);

        return redirect()->back()->with('success', '目標の達成を解除しました。');
    }
}
