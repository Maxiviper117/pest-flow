<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * @return array{string, string, string, string}
 */
$createAgentFixture = static function (): array {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-agent-'.bin2hex(random_bytes(8));

    if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('The agent CLI fixture directory could not be created.');
    }

    $fixturePath = $directory.DIRECTORY_SEPARATOR.'AgentQueryFixtureTest.php';
    $markerPath = $directory.DIRECTORY_SEPARATOR.'scenario-executed';
    $otherMarkerPath = $directory.DIRECTORY_SEPARATOR.'other-scenario-executed';
    $source = <<<'PHP'
<?php

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;

feature('Invoice approvals', function (): void {
    rule('Invoices need an approved purchase order', function (): void {
        scenario('rejects an invoice without approval', function (): void {
            file_put_contents((string) getenv('PEST_FLOW_MARKER'), 'executed');
            given('an invoice without approval', function (): void {
                expect(true)->toBeTrue();
            });
        });
    });
});

feature('Refund workflows', function (): void {
    rule('Refunds require review', function (): void {
        scenario('approves an eligible refund', function (): void {
            file_put_contents((string) getenv('PEST_FLOW_OTHER_MARKER'), 'executed');
            given('an eligible refund request', function (): void {
                expect(true)->toBeTrue();
            });
        });
    });
});
PHP;

    if (file_put_contents($fixturePath, $source) !== strlen($source)) {
        throw new RuntimeException('The agent CLI fixture could not be written.');
    }

    return [$directory, $fixturePath, $markerPath, $otherMarkerPath];
};

/**
 * @param  list<string>  $arguments
 */
$runAgentFixture = static function (array $arguments, string $fixturePath, string $markerPath, string $otherMarkerPath): Process {
    $projectRoot = dirname(__DIR__, 2);
    $process = new Process([
        PHP_BINARY,
        $projectRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest',
        ...$arguments,
        $fixturePath,
    ], $projectRoot);
    $process->setEnv([
        'PEST_FLOW_MARKER' => $markerPath,
        'PEST_FLOW_OTHER_MARKER' => $otherMarkerPath,
    ]);
    $process->setTimeout(30);
    $process->run();

    return $process;
};

it('lists collected behaviours as JSON without running scenario callbacks', function () use ($createAgentFixture, $runAgentFixture): void {
    [$directory, $fixturePath, $markerPath, $otherMarkerPath] = $createAgentFixture();

    try {
        $process = $runAgentFixture(['--flow-list', '--flow-json'], $fixturePath, $markerPath, $otherMarkerPath);
        $document = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $scenario = $document['features'][0]['rules'][0]['scenarios'][0];

        expect($process->getExitCode())->toBe(0)
            ->and($process->getErrorOutput())->toBe('')
            ->and($document['schema_version'])->toBe(1)
            ->and($document['features'][0]['name'])->toBe('Invoice approvals')
            ->and($scenario['status'])->toBe('pending')
            ->and($scenario['steps'])->toBe([])
            ->and(is_file($markerPath))->toBeFalse()
            ->and(is_file($otherMarkerPath))->toBeFalse();
    } finally {
        if (is_file($markerPath)) {
            unlink($markerPath);
        }

        if (is_file($otherMarkerPath)) {
            unlink($otherMarkerPath);
        }

        if (is_file($fixturePath)) {
            unlink($fixturePath);
        }

        rmdir($directory);
    }
});

it('searches recorded step descriptions when explicitly asked to run tests', function () use ($createAgentFixture, $runAgentFixture): void {
    [$directory, $fixturePath, $markerPath, $otherMarkerPath] = $createAgentFixture();

    try {
        $process = $runAgentFixture(['--flow-search=invoice without approval', '--flow-search-in=step', '--flow-json'], $fixturePath, $markerPath, $otherMarkerPath);
        $document = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $scenario = $document['features'][0]['rules'][0]['scenarios'][0];

        expect($process->getExitCode())->toBe(0)
            ->and($process->getErrorOutput())->toBe('')
            ->and($scenario['status'])->toBe('passed')
            ->and($scenario['steps'])->toHaveCount(1)
            ->and($scenario['steps'][0]['text'])->toBe('an invoice without approval')
            ->and(is_file($markerPath))->toBeTrue();
    } finally {
        if (is_file($markerPath)) {
            unlink($markerPath);
        }

        if (is_file($otherMarkerPath)) {
            unlink($otherMarkerPath);
        }

        if (is_file($fixturePath)) {
            unlink($fixturePath);
        }

        rmdir($directory);
    }
});

it('scopes status-query execution to matching feature filters', function () use ($createAgentFixture, $runAgentFixture): void {
    [$directory, $fixturePath, $markerPath, $otherMarkerPath] = $createAgentFixture();

    try {
        $process = $runAgentFixture(['--flow-status=passed', '--flow-feature=Invoice', '--flow-rule=Invoices', '--flow-json'], $fixturePath, $markerPath, $otherMarkerPath);
        $document = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getErrorOutput())->toBe('')
            ->and($document['features'])->toHaveCount(1)
            ->and($document['features'][0]['name'])->toBe('Invoice approvals')
            ->and($document['features'][0]['rules'][0]['name'])->toBe('Invoices need an approved purchase order')
            ->and(is_file($markerPath))->toBeTrue()
            ->and(is_file($otherMarkerPath))->toBeFalse();
    } finally {
        if (is_file($markerPath)) {
            unlink($markerPath);
        }

        if (is_file($otherMarkerPath)) {
            unlink($otherMarkerPath);
        }

        if (is_file($fixturePath)) {
            unlink($fixturePath);
        }

        rmdir($directory);
    }
});
