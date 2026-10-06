<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use Pest\Plugins\Tia\ChangedFiles;

/**
 * Adapts Pest's Git-based changed-file discovery.
 *
 * @internal
 */
final readonly class PestChangedFilesSource implements ChangedFilesSource
{
    public function __construct(private string $projectRoot) {}

    public function since(?string $base): ?array
    {
        $files = new ChangedFiles($this->projectRoot)->since($base);

        return $files === null ? null : array_values($files);
    }
}
