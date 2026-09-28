<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

use InvalidArgumentException;

/**
 * Stores tags for a behaviour node.
 */
trait HasTags
{
    /**
     * @var list<string>
     */
    private array $tags = [];

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * @return list<string> The tags newly added to this node.
     */
    public function addTags(string ...$tags): array
    {
        $normalizedTags = array_map(trim(...), $tags);

        $hasComma = array_filter($normalizedTags, static fn (string $tag): bool => str_contains($tag, ',')) !== [];

        if (in_array('', $normalizedTags, true) || $hasComma) {
            throw new InvalidArgumentException('Tags must be non-empty and cannot contain commas.');
        }

        $addedTags = [];

        foreach ($normalizedTags as $tag) {
            if (in_array($tag, $this->tags, true)) {
                continue;
            }

            $this->tags[] = $tag;
            $addedTags[] = $tag;
        }

        return $addedTags;
    }
}
