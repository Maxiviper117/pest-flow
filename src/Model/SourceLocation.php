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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? $trace[1] ?? [];

        return new self(
            file: $caller['file'] ?? 'unknown',
            line: $caller['line'] ?? 0,
        );
    }
}
