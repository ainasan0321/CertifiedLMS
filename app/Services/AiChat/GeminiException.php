<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use RuntimeException;
use Throwable;

class GeminiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $upstreamStatus = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
