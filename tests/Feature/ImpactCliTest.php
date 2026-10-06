<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('emits clean JSON and stops before scenario callbacks when impact data is unavailable', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $fixturePath = __DIR__.DIRECTORY_SEPARATOR.'ImpactCliFixture-'.bin2hex(random_bytes(8)).'Test.php';
    $temporaryHome = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-impact-home-'.bin2hex(random_bytes(8));
    $markerPath = $temporaryHome.DIRECTORY_SEPARATOR.'scenario-executed';

    if (! mkdir($temporaryHome, 0777, true) && ! is_dir($temporaryHome)) {
        throw new RuntimeException('The isolated impact home directory could not be created.');
    }

    $source = <<<'PHP'
<?php

use function Pest\Flow\feature;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;

feature('Impact command fixture', function (): void {
    rule('Impact reports do not execute tests', function (): void {
        scenario('does not run its callback', function (): void {
            file_put_contents((string) getenv('PEST_FLOW_IMPACT_MARKER'), 'executed');
        });
    });
});
PHP;

    try {
        if (file_put_contents($fixturePath, $source) !== strlen($source)) {
            throw new RuntimeException('The impact CLI fixture could not be written.');
        }

        $process = new Process([
            PHP_BINARY,
            $projectRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest',
            '--flow-impact',
            '--flow-json',
        ], $projectRoot);
        $process->setEnv([
            'HOME' => $temporaryHome,
            'USERPROFILE' => $temporaryHome,
            'PEST_FLOW_IMPACT_MARKER' => $markerPath,
        ]);
        $process->setTimeout(60);
        $process->run();

        $document = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toBe('')
            ->and($document['impact']['status'])->toBe('unavailable')
            ->and($document['impact']['precision'])->toBe('test-file')
            ->and($document['impact']['diagnostics'][0])->toContain('dependency data is missing')
            ->and(is_file($markerPath))->toBeFalse();
    } finally {
        if (is_file($fixturePath)) {
            unlink($fixturePath);
        }

        if (is_file($markerPath)) {
            unlink($markerPath);
        }

        if (is_dir($temporaryHome)) {
            rmdir($temporaryHome);
        }
    }
});
