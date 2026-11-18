<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChat\StoreAiChatConversationRequest;
use App\Http\Requests\AiChat\UpdateAiChatConversationRequest;
use App\Models\AiChatConversation;
use App\Models\Section;
use App\Services\AiChat\AiChatService;
use App\Services\AiChat\GeminiException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiChatConversationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|RedirectResponse
    {
        $latest = $request->user()
            ->aiChatConversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();

        if ($latest) {
            return redirect()->route('ai-chat.conversations.show', $latest);
        }

        return view('ai-chat.empty-state');
    }

    public function store(StoreAiChatConversationRequest $request, AiChatService $service): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $sectionId = $validated['section_id'] ?? null;
        $isExisting = false;
        $conversation = null;

        // 教材から始めた場合、同じ教材の会話が乱立しないよう既存会話を再利用
        if ($sectionId) {
            $conversation = $user->aiChatConversations()
                ->where('section_id', $sectionId)
                ->orderByDesc('last_message_at')
                ->first();
            $isExisting = $conversation !== null;
        }

        if (! $conversation) {
            $section = $sectionId ? Section::with('chapter.part')->find($sectionId) : null;
            $initialTitle = $section?->title
                ? Str::limit($section->title, 100, '')
                : (isset($validated['message']) && trim($validated['message']) !== ''
                    ? Str::limit(trim($validated['message']), 30, '')
                    : '新しい相談');

            $conversation = $user->aiChatConversations()->create([
                'enrollment_id' => $user->default_enrollment_id,
                'section_id' => $section?->id,
                'title' => $initialTitle,
                'last_message_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(
                ['conversation' => $conversation],
                $isExisting ? 200 : 201
            );
        }

        $firstMessage = trim((string) ($validated['message'] ?? ''));
        if ($firstMessage !== '') {
            try {
                $service->sendMessage($user, $conversation, $firstMessage);
            } catch (GeminiException) {
                // エラーバブルが記録されるため詳細画面へそのまま遷移
            }
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function show(AiChatConversation $conversation): View|JsonResponse
    {
        $this->authorize('view', $conversation);
        $conversation->load(['messages', 'enrollment.certification', 'section']);

        if (request()->expectsJson()) {
            return response()->json([
                'conversation' => $conversation,
                'messages' => $conversation->messages,
            ]);
        }

        return view('ai-chat.show', compact('conversation'));
    }

    public function update(UpdateAiChatConversationRequest $request, AiChatConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation->update(['title' => trim((string) $request->validated('title'))]);

        if ($request->expectsJson()) {
            return response()->json(['conversation' => $conversation]);
        }

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(AiChatConversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return redirect()
            ->route('ai-chat.index')
            ->with('success', '会話を削除しました。');
    }
}
