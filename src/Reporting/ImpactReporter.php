<?php

declare(strict_types=1);

namespace Pest\Flow\Reporting;

use Pest\Flow\Impact\ImpactFeature;
use Pest\Flow\Impact\ImpactResult;
use Pest\Flow\Impact\ImpactRule;
use Pest\Flow\Impact\ImpactStatus;
use Pest\Flow\Model\ScenarioNode;

/**
 * Renders test-file-level impact for developers and agents.
 */
final class ImpactReporter
{
    public function render(ImpactResult $impact): string
    {
        $lines = [
            'Pest Flow Impact',
            '',
            'Status: '.$impact->status->value,
            'Precision: '.$impact->precision,
            'Comparison base: '.($impact->base ?? 'working tree'),
            '',
            'Changed files',
            str_repeat('─', 40),
        ];

        $this->appendFiles($lines, $impact->changedFiles);

        $lines[] = '';
        $lines[] = 'Potentially affected behaviour';
        $lines[] = str_repeat('─', 40);

        foreach ($impact->features as $feature) {
            $this->appendFeature($lines, $feature);
        }

        foreach ($impact->standaloneScenarios as $scenario) {
            $this->appendScenario($lines, $scenario, 0);
        }

        if ($impact->features === [] && $impact->standaloneScenarios === []) {
            $lines[] = $impact->status === ImpactStatus::NoImpact
                ? 'No affected behaviour was found.'
                : 'No behaviour could be mapped from the current impact data.';
        }

        $lines[] = '';
        $lines[] = 'Affected Pest test files';
        $lines[] = str_repeat('─', 40);

        foreach ($impact->affectedTestFiles as $testFile) {
            $files = $impact->provenance[$testFile] ?? [];
            $suffix = $files === [] ? '' : ' (selected when analyzing: '.implode(', ', $files).')';
            $lines[] = $testFile.$suffix;
        }

        if ($impact->affectedTestFiles === []) {
            $lines[] = 'None';
        }

        $this->appendNamedFiles($lines, 'Affected test files with no Flow scenarios', $impact->unrepresentedTestFiles);
        $this->appendNamedFiles($lines, 'Stale affected test files', $impact->staleTestFiles);
        $this->appendNamedFiles($lines, 'Changed files without a direct graph mapping', $impact->unknownFiles);
        $this->appendNamedFiles($lines, 'Affected test files without per-file provenance', $impact->unattributedTestFiles);

        $lines[] = '';
        $lines[] = 'Summary';
        $lines[] = str_repeat('─', 40);
        $lines[] = count($impact->changedFiles).' changed files';
        $lines[] = count($impact->affectedTestFiles).' affected test files';
        $lines[] = $impact->featureCount().' features potentially affected';
        $lines[] = $impact->ruleCount().' rules potentially affected';
        $lines[] = $impact->scenarioCount().' scenarios associated with affected test files';

        if ($impact->diagnostics !== []) {
            $lines[] = '';
            $lines[] = 'Diagnostics';
            $lines[] = str_repeat('─', 40);

            foreach ($impact->diagnostics as $diagnostic) {
                $lines[] = '- '.$diagnostic;
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendFeature(array &$lines, ImpactFeature $feature): void
    {
        $lines[] = '';
        $lines[] = 'Feature: '.$feature->node->name;

        foreach ($feature->rules as $rule) {
            $this->appendRule($lines, $rule);
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendRule(array &$lines, ImpactRule $rule): void
    {
        $lines[] = '  Rule: '.$rule->node->name;

        foreach ($rule->scenarios as $scenario) {
            $this->appendScenario($lines, $scenario, 4);
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendScenario(array &$lines, ScenarioNode $scenario, int $indent): void
    {
        $source = $scenario->source->file.':'.$scenario->source->line;
        $lines[] = str_repeat(' ', $indent).'- '.$scenario->name.' ('.$source.')';
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $files
     */
    private function appendFiles(array &$lines, array $files): void
    {
        if ($files === []) {
            $lines[] = 'None';

            return;
        }

        foreach ($files as $file) {
            $lines[] = $file;
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $files
     */
    private function appendNamedFiles(array &$lines, string $heading, array $files): void
    {
        if ($files === []) {
            return;
        }

        $lines[] = '';
        $lines[] = $heading;

        foreach ($files as $file) {
            $lines[] = '- '.$file;
        }
    }
}
