<?php

namespace App\Services;

use App\Contracts\AiBusinessClassifier;

class AiBusinessClassifierManager
{
    public function __construct(
        private readonly GeminiBusinessClassifier $gemini
    ) {
    }

    public function driver(): ?AiBusinessClassifier
    {
        $provider = mb_strtolower(
            trim(
                (string) config(
                    'ai.provider',
                    'gemini'
                )
            )
        );

        return match ($provider) {
            'gemini' => $this->gemini,
            'none',
            'heuristic',
            'disabled',
            '' => null,
            default => null,
        };
    }
}
