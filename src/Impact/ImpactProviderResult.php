<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

/**
 * @internal
 */
final readonly class ImpactProviderResult
{
    /**
     * @param  list<string>  $affectedTestFiles
     * @param  list<string>  $unknownFiles
     * @param  array<string, list<string>>  $provenance  Test file => changed files.
     * @param  list<string>  $staleTestFiles
     * @param  list<string>  $diagnostics
     */
    public function __construct(
        public bool $available,
        public array $affectedTestFiles = [],
        public array $unknownFiles = [],
        public array $provenance = [],
        public array $staleTestFiles = [],
        public array $diagnostics = [],
    ) {}

    public static function unavailable(string $diagnostic): self
    {
        return new self(false, diagnostics: [$diagnostic]);
    }
}
