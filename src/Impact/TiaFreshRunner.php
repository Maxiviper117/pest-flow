<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs Pest's fresh TIA baseline in a child process with coverage enabled.
 */
final class TiaFreshRunner
{
    public function run(string $projectRoot): int
    {
        if (! $this->coverageDriverAvailable()) {
            fwrite(STDERR, "Pest Flow needs PCOV or Xdebug loaded in this PHP CLI to record a TIA graph.\n");

            return 1;
        }

        $pestExecutable = $projectRoot
            .DIRECTORY_SEPARATOR.'vendor'
            .DIRECTORY_SEPARATOR.'bin'
            .DIRECTORY_SEPARATOR.'pest';

        if (! is_file($pestExecutable)) {
            fwrite(STDERR, "Pest Flow could not find vendor/bin/pest in the project root.\n");

            return 1;
        }

        $process = new Process(
            [PHP_BINARY, $pestExecutable, '--tia', '--fresh'],
            $projectRoot,
            ['XDEBUG_MODE' => 'coverage'],
            timeout: null,
        );

        try {
            return $process->run(static function (string $type, string $buffer): void {
                $stream = $type === Process::ERR ? STDERR : STDOUT;
                fwrite($stream, $buffer);
            });
        } catch (Throwable $exception) {
            fwrite(STDERR, 'Pest Flow could not start the TIA graph command: '.$exception->getMessage().PHP_EOL);

            return 1;
        }
    }

    private function coverageDriverAvailable(): bool
    {
        $pcovEnabled = function_exists('pcov\\start')
            && filter_var((string) ini_get('pcov.enabled'), FILTER_VALIDATE_BOOL);

        return $pcovEnabled || extension_loaded('xdebug');
    }
}
