<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

enum ImpactStatus: string
{
    case Resolved = 'resolved';
    case NoImpact = 'no-impact';
    case PartiallyResolved = 'partially-resolved';
    case Unknown = 'unknown';
    case Unavailable = 'unavailable';
}
