<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

use Throwable;

trait TracksExecution
{
    public ExecutionStatus $status = ExecutionStatus::Pending;

    /** Duration of the most recent execution, in seconds. */
    public ?float $duration = null;

    public ?Throwable $exception = null;

    private ?int $executionStartedAt = null;

    public function startExecution(): void
    {
        $this->status = ExecutionStatus::Running;
        $this->duration = null;
        $this->exception = null;
        $this->executionStartedAt = hrtime(true);
    }

    public function markPassed(): void
    {
        $this->finishExecution(ExecutionStatus::Passed);
    }

    public function markFailed(Throwable $exception): void
    {
        $this->finishExecution(ExecutionStatus::Failed, $exception);
    }

    public function markSkipped(?Throwable $exception = null): void
    {
        $this->finishExecution(ExecutionStatus::Skipped, $exception);
    }

    private function finishExecution(ExecutionStatus $status, ?Throwable $exception = null): void
    {
        if ($this->executionStartedAt !== null) {
            $this->duration = (hrtime(true) - $this->executionStartedAt) / 1_000_000_000;
            $this->executionStartedAt = null;
        }

        $this->status = $status;
        $this->exception = $exception;
    }
}
