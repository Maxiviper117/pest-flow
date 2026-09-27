<?php

declare(strict_types=1);

namespace Pest\Flow\Runtime;

use Pest\Flow\Model\ScenarioNode;

/**
 * @internal
 */
final readonly class ScenarioContext
{
    public function __construct(
        public ScenarioNode $scenario,
        public object $testCase,
    ) {}
}
