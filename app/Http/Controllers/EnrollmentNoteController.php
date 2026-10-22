<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Http\Requests\Enrollment\StoreEnrollmentNoteRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentNoteRequest;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollmentNoteController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEnrollmentNoteRequest $request, Enrollment $enrollment): RedirectResponse
    {
        //認可
        $this->authorize('create', [EnrollmentNote::class, $enrollment]);

        //バリデーションチェック
        $validated = $request->validated();

        $enrollment->notes()->create([
            'author_id' => auth()->id(),
            'body' => $validated['body']
        ]);

        return redirect()->back()->with('success', 'メモを追加しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(EnrollmentNote $note): View
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EnrollmentNote $note): View
    {
        //認可
        $this->authorize('update', $note);

        return view('enrollment-note.edit', compact('note'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEnrollmentNoteRequest $request, EnrollmentNote $note): RedirectResponse
    {
        //認可
        $this->authorize('update', $note);

        //バリデーションチェック
        $validated = $request->validated();

        $note->update([
            'body' => $validated['body']
        ]);

        return redirect()->route('enrollments.show', $note->enrollment_id)->with('success', 'メモを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EnrollmentNote $note): RedirectResponse
    {
        //認可
        $this->authorize('delete', $note);

        $note->delete();

        return redirect()->route('enrollments.show', $note->enrollment_id)->with('success', 'メモを削除しました。');
    }
}
