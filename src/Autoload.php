<?php

declare(strict_types=1);

namespace Pest\Flow;

use Closure;
use LogicException;
use Pest\Flow\Dsl\TaggedGroupCall;
use Pest\Flow\Dsl\TaggedScenarioCall;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepType;
use Pest\Flow\Runtime\FlowContext;
use Pest\Flow\Runtime\ScenarioRunner;

function feature(string $name, Closure $definition): TaggedGroupCall
{
    if (FlowContext::currentFeature() instanceof FeatureNode) {
        throw new LogicException('Features cannot be nested.');
    }

    $feature = new FeatureNode($name, SourceLocation::capture());
    FlowRegistry::registerFeature($feature);

    return new TaggedGroupCall(
        $feature,
        \describe($name, static function () use ($feature, $definition): void {
            FlowContext::withFeature($feature, $definition);
        }),
    );
}

function rule(string $name, Closure $definition): TaggedGroupCall
{
    $feature = FlowContext::currentFeature();

    if (! $feature instanceof FeatureNode) {
        throw new LogicException('rule() must be declared inside feature().');
    }

    if (FlowContext::currentRule() instanceof RuleNode) {
        throw new LogicException('Rules cannot be nested.');
    }

    $rule = new RuleNode($name, SourceLocation::capture(), $feature);
    $feature->addRule($rule);

    return new TaggedGroupCall(
        $rule,
        \describe($name, static function () use ($rule, $definition): void {
            FlowContext::withRule($rule, $definition);
        }),
    );
}

function scenario(string $name, Closure $definition): TaggedScenarioCall
{
    $feature = FlowContext::currentFeature();
    $rule = FlowContext::currentRule();

    if ($feature instanceof FeatureNode && ! $rule instanceof RuleNode) {
        throw new LogicException('scenario() inside a feature() must be declared inside rule().');
    }

    $scenario = new ScenarioNode($name, SourceLocation::capture(), $rule);
    $rule?->addScenario($scenario);
    FlowRegistry::registerScenario($scenario);

    $testCall = \it($name, function () use ($scenario, $definition): void {
        ScenarioRunner::run($scenario, $this, $definition);
    });

    $inheritedTags = FlowContext::currentTags();

    if ($inheritedTags !== []) {
        $testCall->group(...$inheritedTags);
    }

    return new TaggedScenarioCall($scenario, $testCall, $inheritedTags);
}

function given(string $description, Closure $definition): void
{
    ScenarioRunner::step(StepType::Given, $description, $definition);
}

function when(string $description, Closure $definition): void
{
    ScenarioRunner::step(StepType::When, $description, $definition);
}

function then(string $description, Closure $definition): void
{
    ScenarioRunner::step(StepType::Then, $description, $definition);
}
