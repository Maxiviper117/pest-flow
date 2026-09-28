<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * A behaviour node that can be assigned tags.
 */
interface TaggableNode
{
    /**
     * @return list<string>
     */
    public function tags(): array;

    /**
     * @return list<string> The tags newly added to this node.
     */
    public function addTags(string ...$tags): array;
}
