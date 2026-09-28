<?php

declare(strict_types=1);

namespace Pest\Flow\Runtime;

use Closure;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;

/**
 * @internal
 */
final class FlowContext
{
    /**
     * @var list<FeatureNode>
     */
    private static array $features = [];

    /**
     * @var list<RuleNode>
     */
    private static array $rules = [];

    /**
     * @var list<ScenarioContext>
     */
    private static array $scenarios = [];

    public static function currentFeature(): ?FeatureNode
    {
        return self::$features === [] ? null : self::$features[array_key_last(self::$features)];
    }

    public static function currentRule(): ?RuleNode
    {
        return self::$rules === [] ? null : self::$rules[array_key_last(self::$rules)];
    }

    /**
     * @return list<string>
     */
    public static function currentTags(): array
    {
        $tags = self::currentFeature()?->tags() ?? [];
        $rule = self::currentRule();

        if ($rule instanceof RuleNode) {
            array_push($tags, ...$rule->tags());
        }

        return array_values(array_unique($tags));
    }

    public static function currentScenario(): ?ScenarioContext
    {
        return self::$scenarios === [] ? null : self::$scenarios[array_key_last(self::$scenarios)];
    }

    public static function withFeature(FeatureNode $feature, Closure $definition): void
    {
        self::$features[] = $feature;

        try {
            $definition();
        } finally {
            array_pop(self::$features);
        }
    }

    public static function withRule(RuleNode $rule, Closure $definition): void
    {
        self::$rules[] = $rule;

        try {
            $definition();
        } finally {
            array_pop(self::$rules);
        }
    }

    public static function withScenario(ScenarioContext $scenario, Closure $definition): void
    {
        self::$scenarios[] = $scenario;

        try {
            $definition();
        } finally {
            array_pop(self::$scenarios);
        }
    }
}
