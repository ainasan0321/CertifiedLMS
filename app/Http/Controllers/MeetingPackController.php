<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Http\Requests\MeetingPack\StoreMeetingPackRequest;
use App\Http\Requests\MeetingPack\UpdateMeetingPackRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class MeetingPackController extends Controller
{
    public function index(Request $request): View
    {

        $keyword = $request->input('keyword');
        $status = $request->input('status');

        $query = MeetingPack::query();

        if (filled($keyword)) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        if (filled($status)) {
            $query->where('status', $status);
        }
        $plans = $query->ordered()->paginate(20)->withQueryString();

    return view('meeting-pack.management.index', compact('plans', 'keyword', 'status'));
    }

    public function create(): View
    {
        return view('meeting-pack.management.create');
    }

    public function store(StoreMeetingPackRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['status'] = MeetingPackStatus::Draft;
        $validated['created_by_user_id'] = $request->user()->id;
        $validated['updated_by_user_id'] = $request->user()->id;

        MeetingPack::create($validated);

        return redirect()->route('admin.meeting-packs.index')
            ->with('success', '面談パックを下書き状態で作成しました。');
    }

    public function show(MeetingPack $plan): View
    {
        $this->authorize('view', $plan);

        $plan->load(['createdBy', 'updatedBy']);

        return view('meeting-pack.management.show', compact('plan'));
    }

    public function edit(MeetingPack $plan)
    {
        $this->authorize('update', $plan);
        
        return view('meeting-pack.management.edit', compact('plan'));
    }

    public function update(UpdateMeetingPackRequest $request, MeetingPack $plan): RedirectResponse
    {
        $validated = $request->validated();

        $validated['updated_by_user_id'] = $request->user()->id;

        $plan->update($validated);

        return redirect()->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックの基本情報を更新しました。');
    }

    public function destroy(MeetingPack $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);
        $plan->delete();

        return redirect()->route('admin.meeting-packs.index')
            ->with('success', '面談パックを削除しました。');
    }

    public function publish(Request $request, MeetingPack $plan): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $plan->update([
            'status' => MeetingPackStatus::Published,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを公開しました。');
    }

    public function archive(Request $request, MeetingPack $plan): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $plan->update([
            'status' => MeetingPackStatus::Archived,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックをアーカイブしました。');
    }

    public function unarchive(Request $request, MeetingPack $plan): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $plan->update([
            'status' => MeetingPackStatus::Draft,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを下書き状態に戻しました.');
    }

}
