<?php

declare(strict_types=1);

namespace Pest\Flow\Dsl;

use Pest\Flow\Model\TaggableNode;
use Pest\PendingCalls\DescribeCall;

/**
 * Adds tags to a feature or rule declaration.
 */
final readonly class TaggedGroupCall
{
    public function __construct(
        private TaggableNode $node,
        private DescribeCall $declaration,
    ) {}

    public function tags(string ...$tags): self
    {
        $this->node->addTags(...$tags);

        return $this;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->declaration->{$name}(...$arguments);

        return $result === $this->declaration ? $this : $result;
    }
}
