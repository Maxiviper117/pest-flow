<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use Pest\Support\Backtrace;

/**
 * Gets the Pest test file that owns the current test declaration.
 *
 * @internal
 */
final class PestTestFileLocator
{
    public static function current(): ?string
    {
        if (! class_exists(Backtrace::class)) {
            return null;
        }

        try {
            return Backtrace::testFile();
        } catch (\Throwable) {
            return null;
        }
    }
}
