<?php

declare(strict_types=1);

use Pest\Flow\Plugins\ConsoleReporterPlugin;
use Symfony\Component\Console\Output\BufferedOutput;

$withoutOuterTiaArgument = static function (callable $callback): mixed {
    $originalArguments = $_SERVER['argv'] ?? null;

    if (is_array($originalArguments)) {
        $_SERVER['argv'] = array_values(array_filter(
            $originalArguments,
            static fn (mixed $argument): bool => $argument !== '--tia',
        ));
    }

    try {
        return $callback();
    } finally {
        if ($originalArguments === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $originalArguments;
        }
    }
};

it('consumes agent query options and suppresses Pest output', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments([
        '--flow-list',
        '--flow-search=invoice',
        '--flow-search-in=step',
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
        '--filter=billing.*payments',
    ]);
});

it('consumes behaviour impact options and preserves the read-only query hook', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments(['--flow-impact=origin/main', '--flow-json']);

    expect($arguments)->toBe(['--no-output'])
        ->and($plugin->shouldRunImpactBeforeTests())->toBeTrue();
});

it('consumes the fresh TIA graph command before Pest runs tests', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);

    expect($plugin->handleArguments(['--flow-tia-fresh']))->toBe([])
        ->and($plugin->shouldRunTiaFreshBeforeTests())->toBeTrue()
        ->and($plugin->shouldRunImpactBeforeTests())->toBeFalse();
});

it('does not treat Pest’s argv script path as an explicit test path', function () use ($withoutOuterTiaArgument): void {
    $withoutOuterTiaArgument(function (): void {
        $output = new BufferedOutput;
        $plugin = new ConsoleReporterPlugin($output);
        $script = $_SERVER['argv'][0] ?? 'pest';
        $plugin->handleArguments([$script, '--flow-impact', '--flow-list']);
        $exitCode = $plugin->addOutput(0);
        $message = $output->fetch();

        expect($exitCode)->toBe(1)
            ->and($message)->toContain('Use --flow-impact separately')
            ->and($message)->not->toContain('Do not pass test paths');
    });
});

it('rejects behaviour impact combined with another Flow query', function () use ($withoutOuterTiaArgument): void {
    $withoutOuterTiaArgument(function (): void {
        $output = new BufferedOutput;
        $plugin = new ConsoleReporterPlugin($output);
        $plugin->handleArguments(['--flow-impact', '--flow-list']);

        expect($plugin->addOutput(0))->toBe(1)
            ->and($output->fetch())->toContain('Use --flow-impact separately');
    });
});

it('does not parse impact-like arguments after the Pest separator', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);

    expect($plugin->handleArguments(['--', '--flow-impact']))
        ->toBe(['--', '--flow-impact'])
        ->and($plugin->shouldRunImpactBeforeTests())->toBeFalse()
        ->and($plugin->addOutput(0))->toBe(0);
});

it('uses Pest test-name filters to narrow execution-dependent queries', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments([
        '--flow-status=passed',
        '--flow-feature=Invoice approvals',
        '--flow-rule=Purchase orders',
        'tests/Feature/InvoiceTest.php',
    ]);

    expect($arguments)->toContain('--filter=Invoice.*approvals.*Purchase.*orders')
        ->and($plugin->shouldRunAgentQueryBeforeTests())->toBeFalse();
});

it('respects an existing Pest test selector when running agent queries', function (): void {
    $plugin = new ConsoleReporterPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments([
        '--flow-status=passed',
        '--flow-feature=Invoice',
        '--filter=invoice-test',
    ]);

    expect($arguments)->toBe(['--filter=invoice-test', '--no-output']);
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
