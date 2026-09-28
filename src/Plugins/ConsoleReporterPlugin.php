<?php

declare(strict_types=1);

namespace Pest\Flow\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Flow\FlowRegistry;
use Pest\Flow\Reporting\ConsoleReporter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adds the optional --flow report to Pest's normal test output.
 */
final class ConsoleReporterPlugin implements AddsOutput, HandlesArguments
{
    private bool $enabled = false;

    private bool $parallel = false;

    public function __construct(private readonly OutputInterface $output) {}

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        $this->enabled = false;
        $this->parallel = false;
        $remaining = [];
        $afterSeparator = false;

        foreach ($arguments as $argument) {
            if ($argument === '--' && ! $afterSeparator) {
                $afterSeparator = true;
                $remaining[] = $argument;

                continue;
            }

            if (! $afterSeparator && $argument === '--flow') {
                $this->enabled = true;

                continue;
            }

            if (! $afterSeparator && (
                in_array($argument, ['--parallel', '-p'], true)
                || str_starts_with($argument, '--parallel=')
            )) {
                $this->parallel = true;
            }

            $remaining[] = $argument;
        }

        return array_values($remaining);
    }

    public function addOutput(int $exitCode): int
    {
        if (! $this->enabled) {
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
}
