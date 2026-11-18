<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChat\StoreAiChatMessageRequest;
use App\Models\AiChatConversation;
use App\Services\AiChat\AiChatService;
use App\Services\AiChat\GeminiException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class AiChatMessageController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreAiChatMessageRequest $request, AiChatConversation $conversation, AiChatService $service): JsonResponse
    {
        $this->authorize('update', $conversation);

        $content = trim((string) $request->validated('content'));

        try {
            $result = $service->sendMessage($request->user(), $conversation, $content);
        } catch (GeminiException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'upstream_status' => $e->upstreamStatus,
            ], 502);
        }

        return response()->json([
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'conversation' => $conversation->fresh(),
        ]);
    }
}
