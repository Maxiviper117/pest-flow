<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

enum ExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
