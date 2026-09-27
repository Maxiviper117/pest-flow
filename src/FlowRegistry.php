<?php

declare(strict_types=1);

namespace Pest\Flow;

use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\NodeIdentifier;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\StepNode;

/**
 * Provides access to the behaviour nodes discovered by Pest Flow.
 */
final class FlowRegistry
{
    /**
     * @var list<FeatureNode>
     */
    private static array $features = [];

    /**
     * @var list<ScenarioNode>
     */
    private static array $scenarios = [];

    private function __construct() {}

    public static function registerFeature(FeatureNode $feature): void
    {
        if (in_array($feature, self::$features, true)) {
            return;
        }

        $feature->id = NodeIdentifier::unique(
            NodeIdentifier::fromName($feature->name),
            array_map(static fn (FeatureNode $registered): string => $registered->id, self::$features),
        );

        self::$features[] = $feature;
    }

    /**
     * @return list<FeatureNode>
     */
    public static function features(): array
    {
        return self::$features;
    }

    /**
     * @return list<RuleNode>
     */
    public static function rules(): array
    {
        $rules = [];

        foreach (self::$features as $feature) {
            array_push($rules, ...$feature->rules());
        }

        return $rules;
    }

    public static function registerScenario(ScenarioNode $scenario): void
    {
        if (in_array($scenario, self::$scenarios, true)) {
            return;
        }

        if ($scenario->rule === null) {
            $rootScenarios = array_values(
                array_filter(
                    self::$scenarios,
                    static fn (ScenarioNode $registered): bool => $registered->rule === null,
                ),
            );

            $scenario->id = NodeIdentifier::unique(
                NodeIdentifier::fromName($scenario->name),
                array_map(static fn (ScenarioNode $registered): string => $registered->id, $rootScenarios),
            );
        }

        self::$scenarios[] = $scenario;
    }

    /**
     * @return list<ScenarioNode>
     */
    public static function scenarios(): array
    {
        return self::$scenarios;
    }

    /**
     * @return list<StepNode>
     */
    public static function steps(): array
    {
        $steps = [];

        foreach (self::$scenarios as $scenario) {
            array_push($steps, ...$scenario->steps());
        }

        return $steps;
    }
}
