<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use Pest\Flow\Model\FeatureNode;

/**
 * @internal
 */
final readonly class ImpactFeature
{
    /**
     * @param  list<ImpactRule>  $rules
     */
    public function __construct(
        public FeatureNode $node,
        public array $rules,
    ) {}
}
