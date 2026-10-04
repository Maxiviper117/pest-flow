<?php

declare(strict_types=1);

use Pest\Flow\Plugins\ConsoleReporterPlugin;
use Symfony\Component\Console\Output\BufferedOutput;

it('writes to the default directory when the report flag has no path', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-default-report-'.bin2hex(random_bytes(8));
    $reportPath = $directory.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'pest-flow'.DIRECTORY_SEPARATOR.'index.html';
    $originalDirectory = getcwd();

    if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('The default report test directory could not be created.');
    }

    try {
        expect(chdir($directory))->toBeTrue();

        $plugin = new ConsoleReporterPlugin(new BufferedOutput);

        expect($plugin->handleArguments(['--flow-report']))
            ->toBe([])
            ->and($plugin->addOutput(0))->toBe(0)
            ->and(is_file($reportPath))->toBeTrue();
    } finally {
        chdir($originalDirectory);

        if (is_file($reportPath)) {
            unlink($reportPath);
        }

        $reportDirectory = dirname($reportPath);
        $buildDirectory = dirname($reportDirectory);

        if (is_dir($reportDirectory)) {
            rmdir($reportDirectory);
        }

        if (is_dir($buildDirectory)) {
            rmdir($buildDirectory);
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('writes a static report to the requested directory and preserves Pest failures', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-report-'.bin2hex(random_bytes(8));

    try {
        $output = new BufferedOutput;
        $plugin = new ConsoleReporterPlugin($output);
        $arguments = $plugin->handleArguments(['--flow-report='.$directory, '--colors=never']);
        $exitCode = $plugin->addOutput(1);
        $reportPath = $directory.DIRECTORY_SEPARATOR.'index.html';

        expect($arguments)->toBe(['--colors=never'])
            ->and($exitCode)->toBe(1)
            ->and(is_file($reportPath))->toBeTrue()
            ->and(file_get_contents($reportPath))->toContain('<!doctype html>')
            ->and($output->fetch())->toContain('Pest Flow living documentation written to');
    } finally {
        if (isset($reportPath) && is_file($reportPath)) {
            unlink($reportPath);
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('reports that documentation cannot be generated with parallel execution', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-report-'.bin2hex(random_bytes(8));
    $output = new BufferedOutput;
    $plugin = new ConsoleReporterPlugin($output);
    $plugin->handleArguments(['--flow-report='.$directory, '--parallel']);

    expect($plugin->addOutput(0))->toBe(1)
        ->and($output->fetch())->toContain('unavailable with --parallel')
        ->and(is_dir($directory))->toBeFalse();
});

it('fails clearly when it cannot create the output directory', function (): void {
    $parentFile = tempnam(sys_get_temp_dir(), 'pest-flow-report-');
    $directory = $parentFile.DIRECTORY_SEPARATOR.'output';
    $output = new BufferedOutput;
    $plugin = new ConsoleReporterPlugin($output);

    try {
        $plugin->handleArguments(['--flow-report='.$directory]);

        expect($plugin->addOutput(0))->toBe(1)
            ->and($output->fetch())->toContain('output directory could not be created');
    } finally {
        if (is_file($parentFile)) {
            unlink($parentFile);
        }
    }
});

it('does not consume flow report arguments after the separator', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-report-'.bin2hex(random_bytes(8));
    $output = new BufferedOutput;
    $plugin = new ConsoleReporterPlugin($output);

    expect($plugin->handleArguments(['--', '--flow-report='.$directory]))
        ->toBe(['--', '--flow-report='.$directory])
        ->and($plugin->addOutput(0))->toBe(0)
        ->and(is_dir($directory))->toBeFalse();
});
