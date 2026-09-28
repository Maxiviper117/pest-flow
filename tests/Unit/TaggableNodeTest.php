<?php

declare(strict_types=1);

use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\SourceLocation;

it('normalizes and deduplicates tags', function (): void {
    $feature = new FeatureNode('Payments', new SourceLocation('feature.php', 1));

    expect($feature->addTags('payments', ' critical ', 'payments'))->toBe(['payments', 'critical'])
        ->and($feature->tags())->toBe(['payments', 'critical'])
        ->and($feature->addTags('critical'))->toBe([]);
});

it('rejects empty tags and comma-separated group names', function (): void {
    $feature = new FeatureNode('Payments', new SourceLocation('feature.php', 1));

    expect(fn () => $feature->addTags(''))
        ->toThrow(InvalidArgumentException::class, 'Tags must be non-empty and cannot contain commas.')
        ->and(fn () => $feature->addTags('payments,critical'))
        ->toThrow(InvalidArgumentException::class, 'Tags must be non-empty and cannot contain commas.');
});
