<?php

namespace Tests\Unit;

use App\Exceptions\AnalysisDeadlineExceeded;
use App\Services\AnalysisDeadline;
use PHPUnit\Framework\TestCase;

class AnalysisDeadlineTest extends TestCase
{
    public function test_provider_timeout_is_capped_by_the_shared_request_budget(): void
    {
        $now = 100.0;
        $deadline = new AnalysisDeadline(
            static function () use (&$now): float {
                return $now;
            }
        );

        $deadline->start(10, 2);
        $now = 106.5;

        $this->assertEqualsWithDelta(
            1.5,
            $deadline->timeoutFor(8),
            0.001
        );
        $this->assertEqualsWithDelta(
            0.5,
            $deadline->timeoutFor(8, 0.5, 1),
            0.001
        );
        $this->assertEqualsWithDelta(
            3.5,
            $deadline->remainingSeconds(),
            0.001
        );
    }

    public function test_provider_call_is_rejected_after_the_usable_budget_is_exhausted(): void
    {
        $now = 100.0;
        $deadline = new AnalysisDeadline(
            static function () use (&$now): float {
                return $now;
            }
        );

        $deadline->start(5, 1);
        $now = 104.25;

        $this->assertFalse($deadline->canStart(1));

        $this->expectException(
            AnalysisDeadlineExceeded::class
        );

        $deadline->timeoutFor(6);
    }
}
