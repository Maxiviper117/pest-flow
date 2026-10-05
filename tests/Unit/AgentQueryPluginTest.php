<?php

declare(strict_types=1);

use Pest\Flow\Plugins\ConsoleReporterPlugin;
use Symfony\Component\Console\Output\BufferedOutput;

it('consumes agent query options and suppresses Pest output', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments([
        '--flow-list',
        '--flow-search=invoice',
        '--flow-search-steps',
        '--flow-feature=billing',
        '--flow-rule=payments',
        '--flow-tag=critical',
        '--flow-status=failed',
        '--flow-source=InvoiceTest.php',
        '--flow-json',
        'tests/Feature/InvoiceTest.php',
    ]);

    expect($arguments)->toBe([
        'tests/Feature/InvoiceTest.php',
        '--no-output',
    ]);
});

it('leaves agent-like arguments untouched after the separator', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);

    expect($plugin->handleArguments(['--', '--flow-list', '--flow-search=invoice']))
        ->toBe(['--', '--flow-list', '--flow-search=invoice'])
        ->and($plugin->addOutput(0))->toBe(0);
});

it('inserts output suppression before the Pest argument separator', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);

    expect($plugin->handleArguments(['--flow-list', '--', 'tests/Feature/InvoiceTest.php']))
        ->toBe(['--no-output', '--', 'tests/Feature/InvoiceTest.php']);
});

it('rejects execution-dependent queries with parallel execution', function (): void {
    foreach ([
        ['--flow-search=invoice', '--flow-search-steps', '--parallel'],
        ['--flow-status=passed', '--parallel'],
    ] as $arguments) {
        $output = new BufferedOutput;
        $plugin = new ConsoleReporterPlugin($output);
        $plugin->handleArguments($arguments);

        expect($plugin->addOutput(0))->toBe(1)
            ->and($output->fetch())->toContain('unavailable with --parallel');
    }
});
