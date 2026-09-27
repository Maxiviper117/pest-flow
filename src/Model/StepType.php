<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

enum StepType: string
{
    case Given = 'given';
    case When = 'when';
    case Then = 'then';
}
