<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * @internal
 */
final readonly class SourceLocation
{
    public function __construct(
        public string $file,
        public int $line,
    ) {}

    public static function capture(): self
    {
        $sourceDirectory = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/').'/';

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }

            $file = str_replace('\\', '/', $frame['file']);

            if (str_starts_with($file, $sourceDirectory)) {
                continue;
            }

            return new self($frame['file'], $frame['line'] ?? 0);
        }

        return new self('unknown', 0);
    }
}
