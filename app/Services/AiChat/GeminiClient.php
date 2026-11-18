<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeminiClient
{
    /**
     * @param array<int, array{role: string, parts: array<int, array{text: string}>}> $contents
     *
     * @return array{text: string, model: string, input_tokens: ?int, output_tokens: ?int, response_time_ms: int}
     */
    public function generateContent(array $contents, ?string $systemInstruction = null): array
    {
        $apiKey = (string) config('ai-chat.gemini.api_key');
        if ($apiKey === '') {
            throw new GeminiException('Gemini API key is not configured.', 503);
        }

        $model = (string) config('ai-chat.gemini.model', 'gemini-2.5-flash');
        $baseUrl = rtrim((string) config('ai-chat.gemini.base_url'), '/');
        $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

        $payload = ['contents' => $contents];
        if ($systemInstruction !== null && $systemInstruction !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $start = microtime(true);
        try {
            $response = Http::timeout((int) config('ai-chat.gemini.timeout', 30))
                ->retry(
                    times: (int) config('ai-chat.gemini.retry_times', 2),
                    sleepMilliseconds: (int) config('ai-chat.gemini.retry_sleep_ms', 100),
                    when: function (\Throwable $exception): bool {
                        if ($exception instanceof RequestException) {
                            return $exception->response->serverError();
                        }

                        return true;
                    },
                    throw: false,
                )
                ->acceptJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            throw new GeminiException($e->getMessage(), 503, $e);
        }
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            throw new GeminiException(
                "HTTP {$response->status()}: ".$response->body(),
                $response->status()
            );
        }

        $json = $response->json();
        $text = data_get($json, 'candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new GeminiException('Empty candidate response', 502);
        }

        return [
            'text' => $text,
            'model' => $model,
            'input_tokens' => data_get($json, 'usageMetadata.promptTokenCount'),
            'output_tokens' => data_get($json, 'usageMetadata.candidatesTokenCount'),
            'response_time_ms' => $elapsedMs,
        ];
    }
}
