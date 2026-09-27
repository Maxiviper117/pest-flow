<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * @internal
 */
final readonly class StepNode
{
    public function __construct(
        public StepType $type,
        public string $description,
        public SourceLocation $source,
    ) {}
}
