<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use Pest\Flow\Model\ScenarioNode;

/**
 * A behaviour impact report with test-file-level precision.
 *
 * @internal
 */
final readonly class ImpactResult
{
    /**
     * @param  list<string>  $changedFiles
     * @param  list<string>  $affectedTestFiles
     * @param  list<ImpactFeature>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     * @param  list<string>  $unrepresentedTestFiles
     * @param  list<string>  $staleTestFiles
     * @param  list<string>  $unknownFiles
     * @param  list<string>  $unattributedTestFiles
     * @param  array<string, list<string>>  $provenance  Test file => changed files.
     * @param  list<string>  $diagnostics
     */
    public function __construct(
        public ImpactStatus $status,
        public ?string $base,
        public array $changedFiles,
        public array $affectedTestFiles,
        public array $features,
        public array $standaloneScenarios,
        public array $unrepresentedTestFiles,
        public array $staleTestFiles,
        public array $unknownFiles,
        public array $unattributedTestFiles,
        public array $provenance,
        public array $diagnostics,
        public string $precision = 'test-file',
    ) {}

    public function featureCount(): int
    {
        return count($this->features);
    }

    public function ruleCount(): int
    {
        return array_sum(array_map(
            static fn (ImpactFeature $feature): int => count($feature->rules),
            $this->features,
        ));
    }

    public function scenarioCount(): int
    {
        $count = count($this->standaloneScenarios);

        foreach ($this->features as $feature) {
            foreach ($feature->rules as $rule) {
                $count += count($rule->scenarios);
            }
        }

        return $count;
    }
}
