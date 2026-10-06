<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

/**
 * @internal
 */
interface ChangedFilesSource
{
    /**
     * @return list<string>|null Null means that the base cannot be resolved.
     */
    public function since(?string $base): ?array;
}
