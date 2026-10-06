<?php

declare(strict_types=1);

$temporaryDirectory = null;
$reportUrl = null;
$reportFileUrl = null;
$server = null;

beforeAll(function () use (&$temporaryDirectory, &$reportUrl, &$reportFileUrl, &$server): void {
    $projectRoot = dirname(__DIR__, 2);
    $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-flow-browser-'.bin2hex(random_bytes(8));
    $fixture = $temporaryDirectory.DIRECTORY_SEPARATOR.'FlowViewerFixtureTest.php';
    $reportDirectory = $temporaryDirectory.DIRECTORY_SEPARATOR.'report';
    $reportPath = $reportDirectory.DIRECTORY_SEPARATOR.'index.html';
    $serverLog = $temporaryDirectory.DIRECTORY_SEPARATOR.'server.log';

    if (! mkdir($temporaryDirectory, 0777, true) && ! is_dir($temporaryDirectory)) {
        throw new RuntimeException('The Playwright report directory could not be created.');
    }

    try {
        $fixtureContents = <<<'PHP'
<?php

use function Pest\Flow\feature;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;

feature('Checkout', function (): void {
    rule('Card payments', function (): void {
        scenario('accepts a valid card', function (): void {
            then('a valid card is accepted', function (): void {
                expect(true)->toBeTrue();
            });
        });

        scenario('declines an expired card', function (): void {
            then('unique rejection action', function (): void {
                expect(false)->toBeTrue();
            });
        })->tags('cards');
    })->tags('critical');
})->tags('billing');

scenario('standalone health check', function (): void {
    then('the service responds', function (): void {
        expect(true)->toBeTrue();
    });
})->tags('smoke');
PHP;

        if (! mkdir($reportDirectory, 0777, true) && ! is_dir($reportDirectory)) {
            throw new RuntimeException('The Playwright report output directory could not be created.');
        }

        if (file_put_contents($fixture, $fixtureContents) !== strlen($fixtureContents)) {
            throw new RuntimeException('The Playwright Pest fixture could not be written.');
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
            throw new RuntimeException('The Pest report fixture process could not be started.');
        }

        fclose($pipes[0]);
        $consoleOutput = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        if ($exitCode !== 1 || ! is_file($reportPath) || ! str_contains($consoleOutput, 'Pest Flow living documentation written to')) {
            throw new RuntimeException('The Pest fixture did not generate the expected report.'.$consoleOutput);
        }

        $normalizedReportPath = str_replace('\\', '/', $reportPath);
        $pathSegments = array_map('rawurlencode', explode('/', $normalizedReportPath));

        if (preg_match('/^[A-Za-z]%3A$/', $pathSegments[0]) === 1) {
            $pathSegments[0] = substr($pathSegments[0], 0, 1).':';
        }

        $reportFileUrl = 'file://'.(str_starts_with($normalizedReportPath, '/') ? '' : '/').implode('/', $pathSegments);

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException('A local HTTP port could not be reserved: '.$errorMessage, $errorCode);
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr(strrchr($address, ':'), 1);

        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $reportDirectory],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $serverLog, 'a'],
                2 => ['file', $serverLog, 'a'],
            ],
            $serverPipes,
            $reportDirectory,
        );

        if (! is_resource($server)) {
            throw new RuntimeException('The report HTTP server could not be started.');
        }

        fclose($serverPipes[0]);
        $deadline = microtime(true) + 10;
        $ready = false;

        do {
            $connection = @fsockopen('127.0.0.1', $port, $serverErrorCode, $serverErrorMessage, 0.1);

            if (is_resource($connection)) {
                fclose($connection);
                $ready = true;

                break;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline && proc_get_status($server)['running']);

        if (! $ready) {
            throw new RuntimeException('The report HTTP server did not become ready. '.file_get_contents($serverLog));
        }

        $reportUrl = 'http://127.0.0.1:'.$port.'/index.html';
    } catch (Throwable $exception) {
        if (is_resource($server)) {
            proc_terminate($server);
            proc_close($server);
            $server = null;
        }

        @unlink($reportPath);
        @rmdir($reportDirectory);
        @unlink($fixture);
        @unlink($serverLog);
        @rmdir($temporaryDirectory);
        $temporaryDirectory = null;

        throw $exception;
    }
});

afterAll(function () use (&$temporaryDirectory, &$server): void {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }

    if ($temporaryDirectory === null) {
        return;
    }

    @unlink($temporaryDirectory.DIRECTORY_SEPARATOR.'report'.DIRECTORY_SEPARATOR.'index.html');
    @rmdir($temporaryDirectory.DIRECTORY_SEPARATOR.'report');
    @unlink($temporaryDirectory.DIRECTORY_SEPARATOR.'FlowViewerFixtureTest.php');
    @unlink($temporaryDirectory.DIRECTORY_SEPARATOR.'server.log');
    @rmdir($temporaryDirectory);
});

it('opens the generated report with its behaviour hierarchy and summary', function () use (&$reportUrl): void {
    $page = visit($reportUrl);

    $page->assertTitle('Pest Flow behaviour explorer')
        ->assertSeeIn('#filter-results', '3 of 3 scenarios shown.')
        ->assertSee('Application Behaviour')
        ->assertSee('Checkout')
        ->assertSee('Standalone scenarios')
        ->assertScript("document.querySelectorAll('.scenario').length === 3")
        ->assertScript("Array.from(document.querySelectorAll('.contents-details, .feature-details, .rule-details, .scenario-details, .standalone-details')).every(details => !details.open)");
});

it('opens the self-contained report directly from the local filesystem', function () use (&$reportFileUrl, &$reportUrl): void {
    $page = visit($reportUrl);
    $page->page()->goto($reportFileUrl);

    $page->assertTitle('Pest Flow behaviour explorer')
        ->assertScript("window.location.protocol === 'file:'")
        ->assertScript("JSON.parse(document.getElementById('flow-document').textContent).schema_version === 1")
        ->fill('#filter-search', 'billing')
        ->assertSeeIn('#filter-results', '2 of 3 scenarios shown.')
        ->assertScript("document.querySelector('.feature-details').open && document.querySelector('.rule-details').open");
});

it('filters failing scenarios and expands their recorded steps', function () use (&$reportUrl): void {
    $page = visit($reportUrl);
    $page->select('#filter-status', 'failed')
        ->assertVisible('.scenario.status-failed')
        ->assertMissing('.standalone-group')
        ->assertSeeIn('.scenario.status-failed', 'unique rejection action')
        ->assertScript("Array.from(document.querySelectorAll('.scenario.status-passed')).every(scenario => scenario.hidden)")
        ->assertScript("document.querySelector('.scenario.status-failed .scenario-details').open");
});

it('searches step text while preserving ancestry and finds standalone scenarios', function () use (&$reportUrl): void {
    $page = visit($reportUrl);
    $page->fill('#filter-search', 'unique rejection action')
        ->assertVisible('.feature')
        ->assertVisible('.rule')
        ->assertVisible('.scenario.status-failed')
        ->assertMissing('.standalone-group')
        ->assertScript("Array.from(document.querySelectorAll('.scenario.status-passed')).every(scenario => scenario.hidden)")
        ->assertScript("document.querySelector('.scenario.status-failed .scenario-details').open");

    $page->click('#clear-filters')
        ->fill('#filter-search', 'standalone health check')
        ->assertVisible('.standalone-group')
        ->assertSeeIn('.standalone-group', 'standalone health check')
        ->assertMissing('.feature');
});

it('searches tags declared on features and rules and keeps their scenarios visible', function () use (&$reportUrl): void {
    $page = visit($reportUrl);
    $page->fill('#filter-search', 'billing')
        ->assertSeeIn('#filter-results', '2 of 3 scenarios shown.')
        ->assertScript("Array.from(document.querySelectorAll('.scenario')).filter(scenario => !scenario.hidden).length === 2")
        ->assertScript("Array.from(document.querySelectorAll('.feature-details, .rule-details')).filter(details => !details.open).length === 0");

    $page->click('#clear-filters')
        ->fill('#filter-search', 'critical')
        ->assertSeeIn('#filter-results', '2 of 3 scenarios shown.')
        ->assertScript("Array.from(document.querySelectorAll('.scenario')).filter(scenario => !scenario.hidden).length === 2");
});

it('filters by inherited tags, feature, rule, and source file', function () use (&$reportUrl): void {
    $page = visit($reportUrl);
    $page->select('#filter-tag', 'critical')
        ->assertScript("Array.from(document.querySelectorAll('.scenario')).filter(scenario => !scenario.hidden).length === 2")
        ->assertMissing('.standalone-group');

    $featureId = $page->script("Array.from(document.querySelector('#filter-feature').options).find(option => option.textContent === 'Checkout').value");
    $page->click('#clear-filters')
        ->select('#filter-feature', $featureId)
        ->assertVisible('.feature')
        ->assertMissing('.standalone-group');

    $ruleId = $page->script("Array.from(document.querySelector('#filter-rule').options).find(option => option.textContent.includes('Card payments')).value");
    $page->click('#clear-filters')
        ->select('#filter-rule', $ruleId)
        ->assertVisible('.scenario.status-failed')
        ->assertMissing('.standalone-group');

    $sourceFile = $page->script("Array.from(document.querySelector('#filter-source').options).find(option => option.textContent.includes('FlowViewerFixtureTest.php')).value");
    $page->click('#clear-filters')
        ->select('#filter-source', $sourceFile)
        ->assertCount('.scenario', 3);
});

it('opens a scenario when its navigation link is selected', function () use (&$reportUrl): void {
    $page = visit($reportUrl);
    $page->click("nav summary:has-text('Checkout')")
        ->click("nav summary:has-text('Card payments')")
        ->click("nav a:has-text('declines an expired card')");

    expect($page->script("document.querySelector('.scenario.status-failed .scenario-details').open"))->toBeTrue()
        ->and($page->script("document.querySelector('.feature-details').open && document.querySelector('.rule-details').open"))->toBeTrue();
});

it('copies the recorded source file and line', function () use (&$reportUrl): void {
    $page = visit($reportUrl);
    $page->script(<<<'JS'
window.__flowClipboard = [];
Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText: async (value) => window.__flowClipboard.push(value) },
});
JS);

    $location = $page->script("document.querySelector('.copy-source').dataset.copy");
    $page->click('.copy-source >> nth=0')->wait(0.1)->assertSeeIn('.copy-source >> nth=0', 'Copied');

    expect($location)->toMatch('/FlowViewerFixtureTest\.php:\d+$/')
        ->and($page->script('window.__flowClipboard.at(-1)'))->toBe($location);
});

it('explains empty results and clear filters restores the full report', function () use (&$reportUrl): void {
    $page = visit($reportUrl);
    $page->fill('#filter-search', 'no behaviour matches this phrase')
        ->assertSeeIn('#filter-results', 'No scenarios match these filters (0 of 3).')
        ->click('#clear-filters')
        ->assertSeeIn('#filter-results', '3 of 3 scenarios shown.')
        ->assertCount('.scenario', 3);
});
