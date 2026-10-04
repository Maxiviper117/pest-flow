<?php

declare(strict_types=1);

namespace Pest\Flow\Dsl;

use Pest\Flow\Model\ScenarioNode;
use Pest\PendingCalls\TestCall;

/**
 * Adds tags to a scenario and forwards them to Pest's native groups.
 */
final readonly class TaggedScenarioCall
{
    /**
     * @param  list<string>  $inheritedTags
     */
    public function __construct(
        private ScenarioNode $scenario,
        private TestCall $testCall,
        private array $inheritedTags,
    ) {}

    public function tags(string ...$tags): self
    {
        $addedTags = $this->scenario->addTags(...$tags);
        $groups = array_values(array_diff($addedTags, $this->inheritedTags));

        if ($groups !== []) {
            $this->testCall->group(...$groups);
        }

        return $this;
    }
}
