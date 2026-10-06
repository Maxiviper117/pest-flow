<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\ScenarioNode;

/**
 * Maps Pest's affected test files to the registered Pest Flow behaviour tree.
 *
 * @internal
 */
final readonly class ImpactResolver
{
    public function __construct(
        private string $projectRoot,
        private ChangedFilesSource $changedFilesSource,
        private ImpactProvider $impactProvider,
    ) {}

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     */
    public function forWorkingTree(
        ?string $base,
        array $features,
        array $standaloneScenarios,
    ): ImpactResult {
        try {
            $changedFiles = $this->changedFilesSource->since($base);
        } catch (\Throwable) {
            return $this->unavailable(
                $base,
                [],
                $base === null
                    ? 'Pest Flow could not read changed files from Git.'
                    : 'Pest Flow could not read changes from the requested comparison base.',
            );
        }

        if ($changedFiles === null) {
            return $this->unavailable(
                $base,
                [],
                'The comparison base could not be resolved as an ancestor of HEAD.',
            );
        }

        return $this->forFiles($changedFiles, $features, $standaloneScenarios, $base);
    }

    /**
     * @param  list<string>  $files
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     */
    public function forFiles(
        array $files,
        array $features,
        array $standaloneScenarios,
        ?string $base = null,
    ): ImpactResult {
        $changedFiles = $this->normalizeInputPaths($files);

        if ($changedFiles === []) {
            return new ImpactResult(
                ImpactStatus::NoImpact,
                $base,
                [],
                [],
                [],
                [],
                [],
                [],
                [],
                [],
                [],
                [],
            );
        }

        try {
            $providerResult = $this->impactProvider->analyze($changedFiles);
        } catch (\Throwable) {
            return $this->unavailable(
                $base,
                $changedFiles,
                'Pest Flow could not read Pest test-impact data.',
            );
        }

        if (! $providerResult->available) {
            return new ImpactResult(
                ImpactStatus::Unavailable,
                $base,
                $changedFiles,
                [],
                [],
                [],
                [],
                [],
                [],
                [],
                [],
                $providerResult->diagnostics,
            );
        }

        $affectedTestFiles = $this->normalizePaths($providerResult->affectedTestFiles);
        $unknownFiles = $this->normalizeUnknownPaths($providerResult->unknownFiles);
        $staleTestFiles = $this->normalizePaths($providerResult->staleTestFiles);
        $provenance = $this->normalizeProvenance($providerResult->provenance);
        $missingTestFile = $this->hasScenarioWithoutTestFile($features, $standaloneScenarios);
        $scenariosByFile = $this->scenariosByFile($features, $standaloneScenarios);
        $affectedScenarioIds = [];
        $unrepresentedTestFiles = [];

        foreach ($affectedTestFiles as $testFile) {
            $scenarios = $scenariosByFile[$testFile] ?? [];

            if ($scenarios === []) {
                $unrepresentedTestFiles[] = $testFile;

                continue;
            }

            foreach ($scenarios as $scenario) {
                $affectedScenarioIds[spl_object_id($scenario)] = true;
            }
        }

        $impactFeatures = [];

        foreach ($features as $feature) {
            $impactRules = [];

            foreach ($feature->rules() as $rule) {
                $scenarios = array_values(array_filter(
                    $rule->scenarios(),
                    static fn (ScenarioNode $scenario): bool => isset($affectedScenarioIds[spl_object_id($scenario)]),
                ));

                if ($scenarios !== []) {
                    $impactRules[] = new ImpactRule($rule, $scenarios);
                }
            }

            if ($impactRules !== []) {
                $impactFeatures[] = new ImpactFeature($feature, $impactRules);
            }
        }

        $impactStandaloneScenarios = array_values(array_filter(
            $standaloneScenarios,
            static fn (ScenarioNode $scenario): bool => isset($affectedScenarioIds[spl_object_id($scenario)]),
        ));

        $unattributedTestFiles = array_values(array_filter(
            $affectedTestFiles,
            static fn (string $testFile): bool => ! isset($provenance[$testFile]),
        ));

        $diagnostics = $providerResult->diagnostics;

        if ($unknownFiles !== []) {
            $diagnostics[] = 'Some changed files have no direct dependency mapping in the Pest graph.';
        }

        if ($staleTestFiles !== []) {
            $diagnostics[] = 'Some affected test files no longer exist in the working tree.';
        }

        if ($missingTestFile) {
            $diagnostics[] = 'Pest Flow could not capture the owning Pest test file for some scenarios.';
        }

        $status = match (true) {
            $unknownFiles !== [] && $affectedTestFiles !== [] => ImpactStatus::PartiallyResolved,
            $unknownFiles !== [] => ImpactStatus::Unknown,
            $staleTestFiles !== [] || $missingTestFile => ImpactStatus::PartiallyResolved,
            $affectedTestFiles === [] => ImpactStatus::NoImpact,
            default => ImpactStatus::Resolved,
        };

        return new ImpactResult(
            $status,
            $base,
            $changedFiles,
            $affectedTestFiles,
            $impactFeatures,
            $impactStandaloneScenarios,
            $this->sortUnique($unrepresentedTestFiles),
            $staleTestFiles,
            $unknownFiles,
            $unattributedTestFiles,
            $provenance,
            $this->sortUnique($diagnostics),
        );
    }

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     * @return array<string, list<ScenarioNode>>
     */
    private function scenariosByFile(array $features, array $standaloneScenarios): array
    {
        $byFile = [];

        foreach ($features as $feature) {
            foreach ($feature->rules() as $rule) {
                foreach ($rule->scenarios() as $scenario) {
                    $path = ProjectPath::normalize(
                        $scenario->pestTestFile ?? '',
                        $this->projectRoot,
                    );

                    if ($path !== null) {
                        $byFile[$path][] = $scenario;
                    }
                }
            }
        }

        foreach ($standaloneScenarios as $scenario) {
            $path = ProjectPath::normalize(
                $scenario->pestTestFile ?? '',
                $this->projectRoot,
            );

            if ($path !== null) {
                $byFile[$path][] = $scenario;
            }
        }

        return $byFile;
    }

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     */
    private function hasScenarioWithoutTestFile(array $features, array $standaloneScenarios): bool
    {
        foreach ($features as $feature) {
            foreach ($feature->rules() as $rule) {
                foreach ($rule->scenarios() as $scenario) {
                    if ($scenario->pestTestFile === null) {
                        return true;
                    }
                }
            }
        }

        return array_any(
            $standaloneScenarios,
            static fn (ScenarioNode $scenario): bool => $scenario->pestTestFile === null,
        );
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function normalizePaths(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            $relative = ProjectPath::normalize($path, $this->projectRoot);

            if ($relative !== null) {
                $normalized[$relative] = true;
            }
        }

        return $this->sortUnique(array_keys($normalized));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function normalizeInputPaths(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            $relative = ProjectPath::normalize($path, $this->projectRoot);
            $path = $relative ?? trim(str_replace('\\', '/', $path));

            if ($path !== '') {
                $normalized[$path] = true;
            }
        }

        return $this->sortUnique(array_keys($normalized));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function normalizeUnknownPaths(array $paths): array
    {
        return $this->normalizeInputPaths($paths);
    }

    /**
     * @param  array<string, list<string>>  $provenance
     * @return array<string, list<string>>
     */
    private function normalizeProvenance(array $provenance): array
    {
        $normalized = [];

        foreach ($provenance as $testFile => $changedFiles) {
            $testPath = ProjectPath::normalize($testFile, $this->projectRoot);

            if ($testPath === null) {
                continue;
            }

            $paths = $this->normalizePaths($changedFiles);

            if ($paths !== []) {
                $normalized[$testPath] = $paths;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function sortUnique(array $paths): array
    {
        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * @param  list<string>  $changedFiles
     */
    private function unavailable(?string $base, array $changedFiles, string $diagnostic): ImpactResult
    {
        return new ImpactResult(
            ImpactStatus::Unavailable,
            $base,
            $changedFiles,
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [$diagnostic],
        );
    }
}
