<?php

namespace App\Contracts;

interface AiBusinessClassifier
{
    public function name(): string;

    public function isConfigured(): bool;

    public function classify(array $businessProfile): array;
}
