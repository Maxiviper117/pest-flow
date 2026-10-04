<?php

declare(strict_types=1);

namespace Pest\Flow\Plugins;

use JsonException;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Flow\FlowRegistry;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Reporting\ConsoleReporter;
use Pest\Flow\Reporting\DocumentationReporter;
use Pest\Flow\Reporting\JsonReporter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adds the optional Pest Flow reports.
 */
final class ConsoleReporterPlugin implements AddsOutput, HandlesArguments
{
    private bool $enabled = false;

    private bool $parallel = false;

    private bool $jsonEnabled = false;

    private bool $documentationEnabled = false;

    private string $documentationOutputDirectory = 'build/pest-flow';

    private bool $jsonOutputToFile = false;

    private ?string $jsonOutputPath = null;

    public function __construct(private readonly OutputInterface $output) {}

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        $this->enabled = false;
        $this->parallel = false;
        $this->jsonEnabled = false;
        $this->documentationEnabled = false;
        $this->documentationOutputDirectory = 'build/pest-flow';
        $this->jsonOutputToFile = false;
        $this->jsonOutputPath = null;
        $remaining = [];
        $afterSeparator = false;
        $hasNoOutput = false;

        foreach ($arguments as $argument) {
            if ($argument === '--' && ! $afterSeparator) {
                if ($this->jsonEnabled && ! $this->jsonOutputToFile && ! $hasNoOutput) {
                    $remaining[] = '--no-output';
                    $hasNoOutput = true;
                }

                $afterSeparator = true;
                $remaining[] = $argument;

                continue;
            }

            if (! $afterSeparator && $argument === '--flow') {
                $this->enabled = true;

                continue;
            }

            if (! $afterSeparator && $argument === '--flow-report') {
                $this->documentationEnabled = true;

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-report=')) {
                $this->documentationEnabled = true;
                $this->documentationOutputDirectory = substr($argument, strlen('--flow-report='));

                continue;
            }

            if (! $afterSeparator && $argument === '--flow-json') {
                $this->jsonEnabled = true;
                $this->jsonOutputToFile = false;
                $this->jsonOutputPath = null;

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-json=')) {
                $this->jsonEnabled = true;
                $this->jsonOutputToFile = true;
                $this->jsonOutputPath = substr($argument, strlen('--flow-json='));

                continue;
            }

            if (! $afterSeparator && $argument === '--no-output') {
                $hasNoOutput = true;
            }

            if (! $afterSeparator && (
                in_array($argument, ['--parallel', '-p'], true)
                || str_starts_with($argument, '--parallel=')
            )) {
                $this->parallel = true;
            }

            $remaining[] = $argument;
        }

        if ($this->jsonEnabled && ! $this->jsonOutputToFile && ! $hasNoOutput) {
            $remaining[] = '--no-output';
        }

        if ($this->jsonEnabled && ! $this->jsonOutputToFile) {
            // Pest's Collision printer writes its own progress and recap regardless of --no-output.
            // Disable it so this plugin can reserve stdout for the JSON document.
            unset($_SERVER['COLLISION_PRINTER']);
        }

        return $remaining;
    }

    public function addOutput(int $exitCode): int
    {
        if ($this->jsonEnabled) {
            $exitCode = $this->addJsonOutput($exitCode);
        }

        if ($this->documentationEnabled) {
            $exitCode = $this->addDocumentationOutput($exitCode);
        }

        if ($this->jsonEnabled || ! $this->enabled) {
            return $exitCode;
        }

        if ($this->parallel) {
            $this->output->writeln([
                '',
                '  <comment>Pest Flow output is unavailable with --parallel because execution data is process-local.</comment>',
            ]);

            return $exitCode;
        }

        $report = (new ConsoleReporter)->render($this->output->isDecorated());

        if ($report === '') {
            return $exitCode;
        }

        $this->output->writeln(['', $report]);

        return $exitCode;
    }

    private function addDocumentationOutput(int $exitCode): int
    {
        if ($this->parallel) {
            $this->writeError('Living documentation is unavailable with --parallel because registry data is process-local.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        if ($this->documentationOutputDirectory === '') {
            $this->writeError('Pass a directory after --flow-report=.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        try {
            $html = (new DocumentationReporter)->render(
                FlowRegistry::features(),
                $this->standaloneScenarios(),
            );
        } catch (\Throwable $exception) {
            $this->writeError('The Pest Flow living documentation could not be rendered: '.$exception->getMessage());

            return $exitCode === 0 ? 1 : $exitCode;
        }

        $directory = $this->documentationOutputDirectory;

        if (file_exists($directory) && ! is_dir($directory)) {
            $this->writeError('The Pest Flow documentation output path is not a directory.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        if (! is_dir($directory)) {
            set_error_handler(static fn (int $severity, string $message, string $file, int $line): bool => true);

            try {
                $directoryCreated = mkdir($directory, 0777, true);
            } finally {
                restore_error_handler();
            }

            if (! $directoryCreated && ! is_dir($directory)) {
                $this->writeError('The Pest Flow documentation output directory could not be created.');

                return $exitCode === 0 ? 1 : $exitCode;
            }
        }

        $directory = rtrim($directory, '/\\');

        if ($directory === '') {
            $directory = DIRECTORY_SEPARATOR;
        }

        $reportPath = $directory.DIRECTORY_SEPARATOR.'index.html';
        set_error_handler(static fn (int $severity, string $message, string $file, int $line): bool => true);

        try {
            $bytesWritten = file_put_contents($reportPath, $html);
        } finally {
            restore_error_handler();
        }

        if ($bytesWritten !== strlen($html)) {
            $this->writeError('The Pest Flow living documentation could not be written to the output directory.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        if (! $this->jsonEnabled || $this->jsonOutputToFile) {
            $this->output->writeln(
                'Pest Flow living documentation written to '.$reportPath,
                OutputInterface::OUTPUT_PLAIN,
            );
        }

        return $exitCode;
    }

    private function addJsonOutput(int $exitCode): int
    {
        if ($this->parallel) {
            $this->writeError('JSON output is unavailable with --parallel because registry data is process-local.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        if ($this->jsonOutputToFile && ($this->jsonOutputPath === null || $this->jsonOutputPath === '')) {
            $this->writeError('Pass a file path after --flow-json=.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        try {
            $json = (new JsonReporter)->render(
                FlowRegistry::features(),
                $this->standaloneScenarios(),
            );
        } catch (JsonException) {
            $this->writeError('The Pest Flow behaviour tree could not be encoded as JSON.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        if ($this->jsonOutputToFile) {
            $bytesWritten = @file_put_contents($this->jsonOutputPath, $json);

            if ($bytesWritten !== strlen($json)) {
                $this->writeError('The Pest Flow JSON report could not be written to the requested file.');

                return $exitCode === 0 ? 1 : $exitCode;
            }

            return $exitCode;
        }

        $this->output->write($json);

        return $exitCode;
    }

    /**
     * @return list<ScenarioNode>
     */
    private function standaloneScenarios(): array
    {
        $scenarios = [];

        foreach (FlowRegistry::scenarios() as $scenario) {
            if (! $scenario->rule instanceof RuleNode) {
                $scenarios[] = $scenario;
            }
        }

        return $scenarios;
    }

    private function writeError(string $message): void
    {
        $output = $this->output instanceof ConsoleOutputInterface
            ? $this->output->getErrorOutput()
            : $this->output;

        $output->writeln('<error>'.$message.'</error>');
    }
}
