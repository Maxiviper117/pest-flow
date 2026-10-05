<?php

declare(strict_types=1);

namespace Pest\Flow\Plugins;

use JsonException;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\Bootable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Flow\FlowRegistry;
use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Query\BehaviourQuery;
use Pest\Flow\Reporting\AgentListReporter;
use Pest\Flow\Reporting\ConsoleReporter;
use Pest\Flow\Reporting\DocumentationReporter;
use Pest\Flow\Reporting\JsonReporter;
use PHPUnit\Event\Facade as EventFacade;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adds the optional Pest Flow reports.
 */
final class ConsoleReporterPlugin implements AddsOutput, Bootable, HandlesArguments
{
    private bool $enabled = false;

    private bool $parallel = false;

    private bool $jsonEnabled = false;

    private bool $documentationEnabled = false;

    private string $documentationOutputDirectory = 'build/pest-flow';

    private bool $jsonOutputToFile = false;

    private ?string $jsonOutputPath = null;

    private bool $agentQueryEnabled = false;

    private bool $agentIncludeSteps = false;

    private ?string $agentSearch = null;

    private ?string $agentFeatureFilter = null;

    private ?string $agentRuleFilter = null;

    private ?string $agentTagFilter = null;

    private ?string $agentStatusFilter = null;

    private ?string $agentSourceFilter = null;

    public function __construct(private readonly OutputInterface $output) {}

    public function boot(): void
    {
        EventFacade::instance()->registerSubscriber(new AgentQuerySubscriber($this));
    }

    public function shouldRunAgentQueryBeforeTests(): bool
    {
        if (! $this->agentQueryEnabled) {
            return false;
        }

        foreach ([
            $this->agentSearch,
            $this->agentFeatureFilter,
            $this->agentRuleFilter,
            $this->agentTagFilter,
            $this->agentStatusFilter,
            $this->agentSourceFilter,
        ] as $filter) {
            if ($filter !== null && trim($filter) === '') {
                return true;
            }
        }

        if ($this->agentStatusFilter !== null
            && ExecutionStatus::tryFrom(strtolower(trim($this->agentStatusFilter))) === null) {
            return true;
        }

        if ($this->agentIncludeSteps && $this->agentSearch === null) {
            return true;
        }

        return $this->agentStatusFilter === null && ! $this->agentIncludeSteps;
    }

    public function runAgentQueryBeforeTests(): never
    {
        exit($this->addAgentQueryOutput(0));
    }

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
        $this->agentQueryEnabled = false;
        $this->agentIncludeSteps = false;
        $this->agentSearch = null;
        $this->agentFeatureFilter = null;
        $this->agentRuleFilter = null;
        $this->agentTagFilter = null;
        $this->agentStatusFilter = null;
        $this->agentSourceFilter = null;
        $remaining = [];
        $afterSeparator = false;
        $hasNoOutput = false;

        foreach ($arguments as $argument) {
            if ($argument === '--' && ! $afterSeparator) {
                if (($this->agentQueryEnabled || ($this->jsonEnabled && ! $this->jsonOutputToFile)) && ! $hasNoOutput) {
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

            if (! $afterSeparator && $argument === '--flow-list') {
                $this->agentQueryEnabled = true;

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-search=')) {
                $this->agentQueryEnabled = true;
                $this->agentSearch = substr($argument, strlen('--flow-search='));

                continue;
            }

            if (! $afterSeparator && $argument === '--flow-search-steps') {
                $this->agentQueryEnabled = true;
                $this->agentIncludeSteps = true;

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-feature=')) {
                $this->agentQueryEnabled = true;
                $this->agentFeatureFilter = substr($argument, strlen('--flow-feature='));

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-rule=')) {
                $this->agentQueryEnabled = true;
                $this->agentRuleFilter = substr($argument, strlen('--flow-rule='));

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-tag=')) {
                $this->agentQueryEnabled = true;
                $this->agentTagFilter = substr($argument, strlen('--flow-tag='));

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-status=')) {
                $this->agentQueryEnabled = true;
                $this->agentStatusFilter = substr($argument, strlen('--flow-status='));

                continue;
            }

            if (! $afterSeparator && str_starts_with($argument, '--flow-source=')) {
                $this->agentQueryEnabled = true;
                $this->agentSourceFilter = substr($argument, strlen('--flow-source='));

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

        if (($this->agentQueryEnabled || ($this->jsonEnabled && ! $this->jsonOutputToFile)) && ! $hasNoOutput) {
            $remaining[] = '--no-output';
        }

        if ($this->agentQueryEnabled || ($this->jsonEnabled && ! $this->jsonOutputToFile)) {
            // Pest's Collision printer writes its own progress and recap regardless of --no-output.
            // Disable it so agent output can remain clean and predictable.
            unset($_SERVER['COLLISION_PRINTER']);
        }

        return $remaining;
    }

    public function addOutput(int $exitCode): int
    {
        if ($this->agentQueryEnabled) {
            return $this->addAgentQueryOutput($exitCode);
        }

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

    private function addAgentQueryOutput(int $exitCode): int
    {
        if ($this->parallel && ($this->agentStatusFilter !== null || $this->agentIncludeSteps)) {
            $this->writeError('Status filtering and step-text search are unavailable with --parallel because execution metadata is process-local.');

            return $exitCode === 0 ? 1 : $exitCode;
        }

        try {
            if ($this->agentIncludeSteps && $this->agentSearch === null) {
                throw new \InvalidArgumentException('--flow-search-steps requires --flow-search=QUERY.');
            }

            $query = new BehaviourQuery(
                FlowRegistry::features(),
                $this->standaloneScenarios(),
                search: $this->agentSearch,
                feature: $this->agentFeatureFilter,
                rule: $this->agentRuleFilter,
                tag: $this->agentTagFilter,
                status: $this->agentStatusFilter,
                source: $this->agentSourceFilter,
            );

            if ($this->jsonEnabled) {
                if ($this->jsonOutputToFile && ($this->jsonOutputPath === null || $this->jsonOutputPath === '')) {
                    $this->writeError('Pass a file path after --flow-json=.');

                    return $exitCode === 0 ? 1 : $exitCode;
                }

                $json = (new JsonReporter)->render(
                    $query->features(),
                    $query->standaloneScenarios(),
                    $query,
                );

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

            $this->output->write((new AgentListReporter)->render($query));

            return $exitCode;
        } catch (\InvalidArgumentException $exception) {
            $this->writeError('The Pest Flow agent query is invalid: '.$exception->getMessage());

            return $exitCode === 0 ? 1 : $exitCode;
        } catch (JsonException) {
            $this->writeError('The Pest Flow behaviour query could not be encoded as JSON.');

            return $exitCode === 0 ? 1 : $exitCode;
        }
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
