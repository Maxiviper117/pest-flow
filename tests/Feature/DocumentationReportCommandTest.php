<?php

declare(strict_types=1);

it('writes registered behaviour to the report even when a scenario fails', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-command-'.bin2hex(random_bytes(8));
    $fixture = $temporaryDirectory.DIRECTORY_SEPARATOR.'FlowReportFixtureTest.php';
    $reportDirectory = $temporaryDirectory.DIRECTORY_SEPARATOR.'report';
    $reportPath = $reportDirectory.DIRECTORY_SEPARATOR.'index.html';

    if (! mkdir($temporaryDirectory, 0777, true) && ! is_dir($temporaryDirectory)) {
        throw new RuntimeException('The Pest Flow command test directory could not be created.');
    }

    try {
        $fixtureContents = <<<'PHP'
<?php

use function Pest\Flow\feature;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;

feature('Flow report fixture', function (): void {
    rule('shows registered behaviour', function (): void {
        scenario('records a passing scenario', function (): void {
            then('the passing step is documented', function (): void {
                expect(true)->toBeTrue();
            });
        });

        scenario('documents a failing scenario too', function (): void {
            then('the failing step is documented', function (): void {
                expect(false)->toBeTrue();
            });
        });
    });
});
PHP;

        if (file_put_contents($fixture, $fixtureContents) !== strlen($fixtureContents)) {
            throw new RuntimeException('The Pest Flow command test fixture could not be written.');
        }

        $process = proc_open(
            [
                PHP_BINARY,
                $projectRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest',
                '--colors=never',
                '--flow-report='.$reportDirectory,
                $fixture,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            $projectRoot,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('The Pest Flow command test process could not be started.');
        }

        fclose($pipes[0]);
        $consoleOutput = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        expect($exitCode)->toBe(1)
            ->and($consoleOutput)->toContain('Pest Flow living documentation written to')
            ->and(is_file($reportPath))->toBeTrue();

        $html = file_get_contents($reportPath);

        expect($html)->toContain('Flow report fixture')
            ->and($html)->toContain('records a passing scenario')
            ->and($html)->toContain('documents a failing scenario too')
            ->and($html)->toContain('the passing step is documented')
            ->and($html)->toContain('the failing step is documented')
            ->and($html)->toContain('class="scenario status-failed"');
    } finally {
        if (is_file($reportPath)) {
            unlink($reportPath);
        }

        if (is_dir($reportDirectory)) {
            rmdir($reportDirectory);
        }

        if (is_file($fixture)) {
            unlink($fixture);
        }

        if (is_dir($temporaryDirectory)) {
            rmdir($temporaryDirectory);
        }
    }
});
