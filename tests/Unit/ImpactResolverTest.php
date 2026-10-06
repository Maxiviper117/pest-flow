<?php

declare(strict_types=1);

use Pest\Flow\Impact\ChangedFilesSource;
use Pest\Flow\Impact\ImpactProvider;
use Pest\Flow\Impact\ImpactProviderResult;
use Pest\Flow\Impact\ImpactResolver;
use Pest\Flow\Impact\ImpactStatus;
use Pest\Flow\Impact\PestTiaImpactProvider;
use Pest\Flow\Impact\ProjectPath;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Reporting\JsonReporter;

$createImpactTree = static function (): array {
    $feature = new FeatureNode('Checkout', new SourceLocation('/project/tests/Feature/CheckoutTest.php', 1));
    $rule = new RuleNode('Cards need authorization', new SourceLocation('/project/tests/Feature/CheckoutTest.php', 3), $feature);
    $feature->addRule($rule);

    $approved = new ScenarioNode(
        'accepts an approved card',
        new SourceLocation('/project/tests/Feature/CheckoutTest.php', 5),
        $rule,
        '/project/tests/Feature/CheckoutTest.php',
    );
    $declined = new ScenarioNode(
        'declines a blocked card',
        new SourceLocation('/project/tests/Support/CheckoutBehaviours.php', 12),
        $rule,
        '/project/tests/Feature/CheckoutTest.php',
    );
    $other = new ScenarioNode(
        'accepts a gift card',
        new SourceLocation('/project/tests/Feature/GiftCardTest.php', 5),
        $rule,
        '/project/tests/Feature/GiftCardTest.php',
    );
    $rule->addScenario($approved);
    $rule->addScenario($declined);
    $rule->addScenario($other);

    return [$feature, $approved, $declined, $other];
};

$createResolver = static function (ImpactProviderResult $providerResult): ImpactResolver {
    $source = new class implements ChangedFilesSource
    {
        public function since(?string $base): ?array
        {
            return [];
        }
    };

    $provider = new class($providerResult) implements ImpactProvider
    {
        public function __construct(private ImpactProviderResult $result) {}

        public function analyze(array $changedFiles): ImpactProviderResult
        {
            return $this->result;
        }
    };

    return new ImpactResolver('/project', $source, $provider);
};

$analyzeGraphFixture = static function (string $contents): ImpactProviderResult {
    $graphPath = tempnam(sys_get_temp_dir(), 'pest-flow-tia-graph-');

    if ($graphPath === false) {
        throw new RuntimeException('A temporary Pest TIA graph could not be created.');
    }

    try {
        if (file_put_contents($graphPath, $contents) !== strlen($contents)) {
            throw new RuntimeException('The temporary Pest TIA graph could not be written.');
        }

        return (new PestTiaImpactProvider('/project', $graphPath))
            ->analyze(['app/Payments/Authorizer.php']);
    } finally {
        unlink($graphPath);
    }
};

it('maps an affected test file to every Flow scenario declared in that file', function () use ($createImpactTree, $createResolver): void {
    [$feature] = $createImpactTree();
    $resolver = $createResolver(new ImpactProviderResult(
        true,
        ['tests/Feature/CheckoutTest.php'],
        provenance: ['tests/Feature/CheckoutTest.php' => ['app/Payments/Authorizer.php']],
    ));

    $result = $resolver->forFiles(['app/Payments/Authorizer.php'], [$feature], []);

    expect($result->status)->toBe(ImpactStatus::Resolved)
        ->and($result->precision)->toBe('test-file')
        ->and($result->affectedTestFiles)->toBe(['tests/Feature/CheckoutTest.php'])
        ->and($result->features)->toHaveCount(1)
        ->and($result->features[0]->rules[0]->scenarios)->toHaveCount(2)
        ->and($result->scenarioCount())->toBe(2)
        ->and($result->provenance['tests/Feature/CheckoutTest.php'])->toBe(['app/Payments/Authorizer.php']);
});

it('reports test files without Flow scenarios and unknown paths separately', function () use ($createImpactTree, $createResolver): void {
    [$feature] = $createImpactTree();
    $resolver = $createResolver(new ImpactProviderResult(
        true,
        ['tests/Feature/CheckoutTest.php', 'tests/Unit/AuthorizerTest.php'],
        ['app/NewPricing/Engine.php'],
    ));

    $result = $resolver->forFiles(
        ['app/NewPricing/Engine.php', 'app/Payments/Authorizer.php'],
        [$feature],
        [],
    );

    expect($result->status)->toBe(ImpactStatus::PartiallyResolved)
        ->and($result->unrepresentedTestFiles)->toBe(['tests/Unit/AuthorizerTest.php'])
        ->and($result->unknownFiles)->toBe(['app/NewPricing/Engine.php']);
});

it('returns a successful no-impact result for an empty changed-file list', function () use ($createImpactTree, $createResolver): void {
    [$feature] = $createImpactTree();
    $resolver = $createResolver(ImpactProviderResult::unavailable('Must not query the provider.'));

    $result = $resolver->forFiles([], [$feature], []);

    expect($result->status)->toBe(ImpactStatus::NoImpact)
        ->and($result->diagnostics)->toBe([])
        ->and($result->affectedTestFiles)->toBe([]);
});

it('reports no impact when known changed files select no test files', function () use ($createImpactTree, $createResolver): void {
    [$feature] = $createImpactTree();
    $resolver = $createResolver(new ImpactProviderResult(true));

    $result = $resolver->forFiles(['app/Payments/ReadModel.php'], [$feature], []);

    expect($result->status)->toBe(ImpactStatus::NoImpact)
        ->and($result->changedFiles)->toBe(['app/Payments/ReadModel.php'])
        ->and($result->affectedTestFiles)->toBe([]);
});

it('distinguishes unknown impact from a resolved no-impact result', function () use ($createImpactTree, $createResolver): void {
    [$feature] = $createImpactTree();
    $resolver = $createResolver(new ImpactProviderResult(
        true,
        unknownFiles: ['app/NewPricing/Engine.php'],
    ));

    $result = $resolver->forFiles(['app/NewPricing/Engine.php'], [$feature], []);

    expect($result->status)->toBe(ImpactStatus::Unknown)
        ->and($result->unknownFiles)->toBe(['app/NewPricing/Engine.php'])
        ->and($result->affectedTestFiles)->toBe([]);
});

it('marks removed affected test files as stale and partially resolved', function () use ($createImpactTree, $createResolver): void {
    [$feature] = $createImpactTree();
    $deletedTestFile = 'tests/Feature/DeletedCheckoutTest.php';
    $resolver = $createResolver(new ImpactProviderResult(
        true,
        affectedTestFiles: [$deletedTestFile],
        staleTestFiles: [$deletedTestFile],
    ));

    $result = $resolver->forFiles(['app/Payments/Authorizer.php'], [$feature], []);

    expect($result->status)->toBe(ImpactStatus::PartiallyResolved)
        ->and($result->staleTestFiles)->toBe([$deletedTestFile])
        ->and($result->unrepresentedTestFiles)->toBe([$deletedTestFile]);
});

it('normalizes Windows paths inside the project root', function (): void {
    expect(ProjectPath::normalize('c:\\repo\\tests\\Feature\\CheckoutTest.php', 'C:\\Repo'))
        ->toBe('tests/Feature/CheckoutTest.php');
});

it('rejects absolute paths outside the project root', function (): void {
    expect(ProjectPath::normalize('C:\\other\\CheckoutTest.php', 'C:\\Repo'))->toBeNull();
});

it('marks missing owning-test-file metadata as incomplete instead of matching the declaration file', function (): void {
    $feature = new FeatureNode('Shipping', new SourceLocation('/project/tests/Feature/ShippingTest.php', 1));
    $rule = new RuleNode('Orders ship once', new SourceLocation('/project/tests/Feature/ShippingTest.php', 3), $feature);
    $feature->addRule($rule);
    $scenario = new ScenarioNode(
        'ships an order',
        new SourceLocation('/project/tests/Support/ShippingScenarios.php', 5),
        $rule,
    );
    $rule->addScenario($scenario);
    $source = new class implements ChangedFilesSource
    {
        public function since(?string $base): ?array
        {
            return null;
        }
    };
    $provider = new class implements ImpactProvider
    {
        public function analyze(array $changedFiles): ImpactProviderResult
        {
            return new ImpactProviderResult(true, ['tests/Feature/ShippingTest.php']);
        }
    };
    $result = (new ImpactResolver('/project', $source, $provider))
        ->forFiles(['app/Shipping/Dispatcher.php'], [$feature], []);

    expect($result->status)->toBe(ImpactStatus::PartiallyResolved)
        ->and($result->features)->toBe([])
        ->and($result->unrepresentedTestFiles)->toBe(['tests/Feature/ShippingTest.php'])
        ->and($result->diagnostics)->toContain('Pest Flow could not capture the owning Pest test file for some scenarios.');
});

it('reports a comparison base that Pest cannot resolve', function () use ($createImpactTree): void {
    [$feature] = $createImpactTree();
    $source = new class implements ChangedFilesSource
    {
        public function since(?string $base): ?array
        {
            return null;
        }
    };
    $provider = new class implements ImpactProvider
    {
        public function analyze(array $changedFiles): ImpactProviderResult
        {
            return ImpactProviderResult::unavailable('Must not query the provider.');
        }
    };
    $resolver = new ImpactResolver('/project', $source, $provider);

    $result = $resolver->forWorkingTree('origin/missing', [$feature], []);

    expect($result->status)->toBe(ImpactStatus::Unavailable)
        ->and($result->base)->toBe('origin/missing')
        ->and($result->diagnostics[0])->toContain('ancestor of HEAD');
});

it('reports an unavailable result when the Pest TIA graph is missing', function (): void {
    $result = (new PestTiaImpactProvider('/project', '/missing/pest-flow/graph.json'))
        ->analyze(['app/Payments/Authorizer.php']);

    expect($result->available)->toBeFalse()
        ->and($result->diagnostics[0])->toContain('dependency data is missing');
});

it('reports malformed Pest TIA data as unavailable', function () use ($analyzeGraphFixture): void {
    $result = $analyzeGraphFixture('{');

    expect($result->available)->toBeFalse()
        ->and($result->diagnostics[0])->toContain('dependency data is malformed');
});

it('reports unsupported Pest TIA graph schemas as unavailable', function () use ($analyzeGraphFixture): void {
    $result = $analyzeGraphFixture(json_encode([
        'schema' => 999,
        'fingerprint' => [],
        'files' => [],
        'edges' => [],
    ], JSON_THROW_ON_ERROR));

    expect($result->available)->toBeFalse()
        ->and($result->diagnostics[0])->toContain('unsupported format');
});

it('keeps impact JSON separate from execution status', function () use ($createImpactTree, $createResolver): void {
    [$feature] = $createImpactTree();
    $scenario = $feature->rules()[0]->scenarios()[0];
    $scenario->startExecution();
    $scenario->markFailed(new RuntimeException('Existing execution state.'));
    $resolver = $createResolver(new ImpactProviderResult(
        true,
        ['tests/Feature/CheckoutTest.php'],
    ));
    $impact = $resolver->forFiles(['app/Payments/Authorizer.php'], [$feature], []);
    $document = json_decode((new JsonReporter)->renderImpact($impact), true, 512, JSON_THROW_ON_ERROR);
    $jsonScenario = $document['features'][0]['rules'][0]['scenarios'][0];

    expect($document['impact']['status'])->toBe('resolved')
        ->and($document['impact']['precision'])->toBe('test-file')
        ->and($jsonScenario['name'])->toBe('accepts an approved card')
        ->and($jsonScenario)->not->toHaveKey('status')
        ->and($jsonScenario)->not->toHaveKey('duration');
});
