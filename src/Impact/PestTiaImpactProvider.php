<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use JsonException;
use Pest\Plugins\Tia as TiaPlugin;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Storage;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;
use Pest\Support\View;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reads Pest 5's internal TIA graph without running tests.
 *
 * @internal Pest's TIA APIs are internal and are isolated here by design.
 */
final readonly class PestTiaImpactProvider implements ImpactProvider
{
    private const int MAX_PROVENANCE_FILE_COUNT = 25;

    /**
     * @param  string|null  $graphPath  Optional graph path for isolated tests.
     */
    public function __construct(
        private string $projectRoot,
        private ?string $graphPath = null,
        private ?OutputInterface $diagnosticOutput = null,
    ) {}

    public function analyze(array $changedFiles): ImpactProviderResult
    {
        if (! class_exists(Graph::class)
            || ! class_exists(Fingerprint::class)
            || ! class_exists(Storage::class)) {
            return ImpactProviderResult::unavailable(
                'The installed Pest version does not expose the TIA operations required by Pest Flow.',
            );
        }

        try {
            $graphPath = $this->graphPath ?? Storage::tempDir($this->projectRoot)
                .DIRECTORY_SEPARATOR.TiaPlugin::KEY_GRAPH;
        } catch (\Throwable) {
            return ImpactProviderResult::unavailable(
                'Pest TIA storage could not be located for this project.',
            );
        }

        if (! is_file($graphPath)) {
            return ImpactProviderResult::unavailable(
                'Pest TIA dependency data is missing. Run vendor/bin/pest --tia to record a graph.',
            );
        }

        $json = @file_get_contents($graphPath);

        if (! is_string($json)) {
            return ImpactProviderResult::unavailable(
                'Pest TIA dependency data could not be read. Run vendor/bin/pest --tia --fresh to rebuild it.',
            );
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ImpactProviderResult::unavailable(
                'Pest TIA dependency data is malformed. Run vendor/bin/pest --tia --fresh to rebuild it.',
            );
        }

        if (! is_array($document)
            || ($document['schema'] ?? null) !== 1
            || ! is_array($document['fingerprint'] ?? null)
            || ! is_array($document['files'] ?? null)
            || ! is_array($document['edges'] ?? null)) {
            return ImpactProviderResult::unavailable(
                'Pest TIA dependency data has an unsupported format. Run vendor/bin/pest --tia --fresh to rebuild it.',
            );
        }

        try {
            $graph = Graph::decode($json, $this->projectRoot);
            $currentFingerprint = Fingerprint::compute($this->projectRoot);
        } catch (\Throwable) {
            return ImpactProviderResult::unavailable(
                'Pest TIA dependency data could not be loaded by the installed Pest version.',
            );
        }

        if (! $graph instanceof Graph) {
            return ImpactProviderResult::unavailable(
                'Pest TIA dependency data could not be decoded. Run vendor/bin/pest --tia --fresh to rebuild it.',
            );
        }

        if (! Fingerprint::structuralMatches($graph->fingerprint(), $currentFingerprint)) {
            return ImpactProviderResult::unavailable(
                'Pest TIA dependency data does not match the current project structure. Run vendor/bin/pest --tia --fresh to rebuild it.',
            );
        }

        try {
            $watchPatterns = Container::getInstance()->get(WatchPatterns::class);

            if (! $watchPatterns instanceof WatchPatterns) {
                return ImpactProviderResult::unavailable(
                    'Pest TIA watch configuration is unavailable in this Pest run.',
                );
            }

            // Pest normally loads these defaults when TIA is enabled. The impact
            // query uses the same fallback rules without enabling test execution.
            $watchPatterns->useDefaults($this->projectRoot);

            $normalizedFiles = [];
            $unknownFiles = [];

            foreach ($changedFiles as $file) {
                $path = ProjectPath::normalize($file, $this->projectRoot);

                if ($path === null) {
                    $unknownFiles[] = str_replace('\\', '/', $file);

                    continue;
                }

                $normalizedFiles[$path] = true;
            }

            $normalizedFiles = array_keys($normalizedFiles);

            // Pest may emit fallback-resolution warnings. Keep them off stdout
            // so --flow-json remains valid JSON.
            View::renderUsing($this->diagnosticOutput ?? new NullOutput);
            $affectedTestFiles = $this->normalizeFiles($graph->affected($normalizedFiles));

            $directlyKnown = $this->directlyKnownFiles($document);
            $allTestFiles = $this->normalizeFiles($graph->allTestFiles());
            $allTestFileSet = array_fill_keys($allTestFiles, true);
            $provenance = [];
            $captureProvenance = count($normalizedFiles) <= self::MAX_PROVENANCE_FILE_COUNT;

            foreach ($normalizedFiles as $changedFile) {
                $matchedFallback = $watchPatterns->matchedDirectories($this->projectRoot, [$changedFile]) !== [];
                $isDirectlyKnown = isset($directlyKnown[$changedFile]) || isset($allTestFileSet[$changedFile]);

                if (! $isDirectlyKnown && ! $matchedFallback) {
                    $unknownFiles[] = $changedFile;
                }

                if (! $captureProvenance) {
                    continue;
                }

                $singleFileImpact = $this->normalizeFiles($graph->affected([$changedFile]));

                foreach ($singleFileImpact as $testFile) {
                    if (! in_array($testFile, $affectedTestFiles, true)) {
                        continue;
                    }

                    $provenance[$testFile][] = $changedFile;
                }
            }

            foreach ($provenance as $testFile => $files) {
                $provenance[$testFile] = $this->sortUnique($files);
            }

            ksort($provenance);
            $staleTestFiles = array_values(array_filter(
                $affectedTestFiles,
                fn (string $testFile): bool => ! is_file($this->projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $testFile)),
            ));

            $diagnostics = [];

            if ($captureProvenance && $affectedTestFiles !== [] && count($provenance) !== count($affectedTestFiles)) {
                $diagnostics[] = 'Pest TIA did not provide per-file provenance for every affected test file.';
            }

            if (! $captureProvenance) {
                $diagnostics[] = 'Pest Flow omitted per-file provenance because the changed-file list is large.';
            }

            return new ImpactProviderResult(
                true,
                $affectedTestFiles,
                $this->sortUnique($unknownFiles),
                $provenance,
                $staleTestFiles,
                $diagnostics,
            );
        } catch (\Throwable) {
            return ImpactProviderResult::unavailable(
                'Pest Flow could not query the Pest TIA graph with the installed Pest version.',
            );
        }
    }

    /**
     * @param  array<mixed>  $document
     * @return array<string, true>
     */
    private function directlyKnownFiles(array $document): array
    {
        $files = [];
        $sourceFiles = $document['files'] ?? [];

        if (! is_array($sourceFiles)) {
            $sourceFiles = [];
        }

        foreach ($sourceFiles as $file) {
            if (is_string($file)) {
                $path = ProjectPath::normalize($file, $this->projectRoot);

                if ($path !== null) {
                    $files[$path] = true;
                }
            }
        }

        foreach (array_keys(is_array($document['js_file_to_components'] ?? null) ? $document['js_file_to_components'] : []) as $file) {
            if (! is_string($file)) {
                continue;
            }

            $path = ProjectPath::normalize($file, $this->projectRoot);

            if ($path !== null) {
                $files[$path] = true;
            }
        }

        return $files;
    }

    /**
     * @param  array<array-key, string>  $files
     * @return list<string>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $file) {
            $path = ProjectPath::normalize($file, $this->projectRoot);

            if ($path !== null) {
                $normalized[$path] = true;
            }
        }

        return $this->sortUnique(array_keys($normalized));
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    private function sortUnique(array $files): array
    {
        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }
}
