<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;

/**
 * @internal
 */
final readonly class ImpactRule
{
    /**
     * @param  list<ScenarioNode>  $scenarios
     */
    public function __construct(
        public RuleNode $node,
        public array $scenarios,
    ) {}
}
