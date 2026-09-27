<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * A step node in the discovered behaviour registry.
 */
final class StepNode
{
    public string $id;

    public function __construct(
        public readonly StepType $type,
        public readonly string $description,
        public readonly SourceLocation $source,
        ?string $id = null,
    ) {
        $this->id = $id ?? NodeIdentifier::fromName($type->value.'-'.$description);
    }
}
