<?php

namespace App\Services;

use App\Exceptions\AnalysisDeadlineExceeded;
use Closure;

class AnalysisDeadline
{
    private ?float $expiresAt = null;

    private float $reserveSeconds = 0.0;

    private readonly Closure $clock;

    public function __construct(
        ?Closure $clock = null
    ) {
        $this->clock = $clock
            ?? static fn (): float => microtime(true);
    }

    public function start(
        float $budgetSeconds,
        float $reserveSeconds = 0.0
    ): void {
        $budgetSeconds = max(
            0.1,
            $budgetSeconds
        );

        $this->reserveSeconds = max(
            0.0,
            min(
                $reserveSeconds,
                $budgetSeconds - 0.1
            )
        );

        $this->expiresAt =
            $this->now() + $budgetSeconds;
    }

    public function canStart(
        float $minimumSeconds = 0.5
    ): bool {
        if ($this->expiresAt === null) {
            return true;
        }

        return $this->availableSeconds()
            >= max(0.0, $minimumSeconds);
    }

    public function timeoutFor(
        float $preferredSeconds,
        float $minimumSeconds = 0.5,
        float $preserveSeconds = 0.0
    ): float {
        $preferredSeconds = max(
            $minimumSeconds,
            $preferredSeconds
        );

        if ($this->expiresAt === null) {
            return $preferredSeconds;
        }

        $available = max(
            0.0,
            $this->availableSeconds()
                - max(0.0, $preserveSeconds)
        );

        if ($available < $minimumSeconds) {
            throw new AnalysisDeadlineExceeded(
                'The analysis time budget has been exhausted.'
            );
        }

        return max(
            $minimumSeconds,
            min(
                $preferredSeconds,
                $available
            )
        );
    }

    public function remainingSeconds(): ?float
    {
        if ($this->expiresAt === null) {
            return null;
        }

        return max(
            0.0,
            $this->expiresAt - $this->now()
        );
    }

    private function availableSeconds(): float
    {
        return max(
            0.0,
            ($this->expiresAt ?? $this->now())
                - $this->now()
                - $this->reserveSeconds
        );
    }

    private function now(): float
    {
        return (float) ($this->clock)();
    }
}
