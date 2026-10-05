<?php

declare(strict_types=1);

namespace Pest\Flow\Query;

use InvalidArgumentException;
use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\StepNode;

/**
 * Selects read-only views of the registered behaviour tree.
 *
 * @internal
 */
final readonly class BehaviourQuery
{
    /**
     * @var list<FeatureNode>
     */
    private array $features;

    /**
     * @var array<int, list<RuleNode>>
     */
    private array $rulesByFeature;

    /**
     * @var array<int, list<ScenarioNode>>
     */
    private array $scenariosByRule;

    /**
     * @var array<int, list<StepNode>>
     */
    private array $stepsByScenario;

    /**
     * @var list<ScenarioNode>
     */
    private array $standaloneScenarios;

    private ?string $search;

    private ?string $featureFilter;

    private ?string $ruleFilter;

    private ?string $tagFilter;

    private ?string $statusFilter;

    private ?string $sourceFilter;

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     */
    public function __construct(
        array $features,
        array $standaloneScenarios,
        ?string $search = null,
        ?string $feature = null,
        ?string $rule = null,
        ?string $tag = null,
        ?string $status = null,
        ?string $source = null,
    ) {
        $this->search = $this->normalize($search, 'Search text');
        $this->featureFilter = $this->normalize($feature, 'Feature filter');
        $this->ruleFilter = $this->normalize($rule, 'Rule filter');
        $this->tagFilter = $this->normalize($tag, 'Tag filter');
        $normalizedStatus = $this->normalize($status, 'Status filter');
        $this->statusFilter = $normalizedStatus === null ? null : strtolower($normalizedStatus);
        $this->sourceFilter = $this->normalize($source, 'Source filter');

        if ($this->statusFilter !== null && ExecutionStatus::tryFrom($this->statusFilter) === null) {
            $statuses = implode(', ', array_map(static fn (ExecutionStatus $executionStatus): string => $executionStatus->value, ExecutionStatus::cases()));

            throw new InvalidArgumentException(sprintf('Unsupported status "%s". Expected one of: %s.', $this->statusFilter, $statuses));
        }

        $selectedFeatures = [];
        $selectedRulesByFeature = [];
        $selectedScenariosByRule = [];
        $selectedStepsByScenario = [];

        foreach ($features as $featureNode) {
            $selectedRules = [];

            foreach ($featureNode->rules() as $ruleNode) {
                $selectedScenarios = [];

                foreach ($ruleNode->scenarios() as $scenarioNode) {
                    if (! $this->matchesScenario($featureNode, $ruleNode, $scenarioNode)) {
                        continue;
                    }

                    $selectedScenarios[] = $scenarioNode;
                    $selectedStepsByScenario[spl_object_id($scenarioNode)] = $scenarioNode->steps();
                }

                if ($selectedScenarios !== []) {
                    $selectedRules[] = $ruleNode;
                    $selectedScenariosByRule[spl_object_id($ruleNode)] = $selectedScenarios;

                    continue;
                }

                if ($ruleNode->scenarios() === [] && $this->matchesContainer($featureNode, $ruleNode)) {
                    $selectedRules[] = $ruleNode;
                    $selectedScenariosByRule[spl_object_id($ruleNode)] = [];
                }
            }

            if ($selectedRules !== [] || ($featureNode->rules() === [] && $this->matchesContainer($featureNode, null))) {
                $selectedFeatures[] = $featureNode;
                $selectedRulesByFeature[spl_object_id($featureNode)] = $selectedRules;
            }
        }

        $selectedStandaloneScenarios = [];

        foreach ($standaloneScenarios as $scenario) {
            if (! $this->matchesScenario(null, null, $scenario)) {
                continue;
            }

            $selectedStandaloneScenarios[] = $scenario;
            $selectedStepsByScenario[spl_object_id($scenario)] = $scenario->steps();
        }

        $this->features = $selectedFeatures;
        $this->rulesByFeature = $selectedRulesByFeature;
        $this->scenariosByRule = $selectedScenariosByRule;
        $this->stepsByScenario = $selectedStepsByScenario;
        $this->standaloneScenarios = $selectedStandaloneScenarios;
    }

    /**
     * @return list<FeatureNode>
     */
    public function features(): array
    {
        return $this->features;
    }

    /**
     * @return list<RuleNode>
     */
    public function rulesFor(FeatureNode $feature): array
    {
        return $this->rulesByFeature[spl_object_id($feature)] ?? [];
    }

    /**
     * @return list<ScenarioNode>
     */
    public function scenariosFor(RuleNode $rule): array
    {
        return $this->scenariosByRule[spl_object_id($rule)] ?? [];
    }

    /**
     * @return list<StepNode>
     */
    public function stepsFor(ScenarioNode $scenario): array
    {
        return $this->stepsByScenario[spl_object_id($scenario)] ?? [];
    }

    /**
     * @return list<ScenarioNode>
     */
    public function standaloneScenarios(): array
    {
        return $this->standaloneScenarios;
    }

    public function hasResults(): bool
    {
        return $this->features !== [] || $this->standaloneScenarios !== [];
    }

    private function matchesScenario(?FeatureNode $feature, ?RuleNode $rule, ScenarioNode $scenario): bool
    {
        if ($this->featureFilter !== null && (! $feature instanceof FeatureNode || ! $this->contains($feature->name, $this->featureFilter))) {
            return false;
        }

        if ($this->ruleFilter !== null && (! $rule instanceof RuleNode || ! $this->contains($rule->name, $this->ruleFilter))) {
            return false;
        }

        $nodes = array_values(array_filter([$feature, $rule, $scenario]));
        $steps = $scenario->steps();

        if ($this->search !== null && ! $this->matchesSearch($nodes, $steps)) {
            return false;
        }

        if ($this->tagFilter !== null && ! $this->matchesTag($nodes)) {
            return false;
        }

        if ($this->sourceFilter !== null && ! $this->matchesSource($nodes, $steps)) {
            return false;
        }

        return $this->statusFilter === null || $this->matchesStatus($scenario, $steps);
    }

    private function matchesContainer(FeatureNode $feature, ?RuleNode $rule): bool
    {
        if ($this->statusFilter !== null) {
            return false;
        }

        if ($this->featureFilter !== null && ! $this->contains($feature->name, $this->featureFilter)) {
            return false;
        }

        if ($this->ruleFilter !== null && (! $rule instanceof RuleNode || ! $this->contains($rule->name, $this->ruleFilter))) {
            return false;
        }

        $nodes = $rule instanceof RuleNode ? [$feature, $rule] : [$feature];

        if ($this->search !== null && ! $this->matchesSearch($nodes, [])) {
            return false;
        }

        if ($this->tagFilter !== null && ! $this->matchesTag($nodes)) {
            return false;
        }

        return $this->sourceFilter === null || $this->matchesSource($nodes, []);
    }

    /**
     * @param  list<FeatureNode|RuleNode|ScenarioNode>  $nodes
     * @param  list<StepNode>  $steps
     */
    private function matchesSearch(array $nodes, array $steps): bool
    {
        foreach ($nodes as $node) {
            if ($this->contains($node->name, $this->search ?? '')) {
                return true;
            }

            foreach ($node->tags() as $tag) {
                if ($this->contains($tag, $this->search ?? '')) {
                    return true;
                }
            }
        }

        return array_any($steps, fn (StepNode $step): bool => $this->contains($step->description, $this->search ?? ''));
    }

    /**
     * @param  list<FeatureNode|RuleNode|ScenarioNode>  $nodes
     */
    private function matchesTag(array $nodes): bool
    {
        foreach ($nodes as $node) {
            foreach ($node->tags() as $tag) {
                if (strcasecmp($tag, $this->tagFilter ?? '') === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<FeatureNode|RuleNode|ScenarioNode>  $nodes
     * @param  list<StepNode>  $steps
     */
    private function matchesSource(array $nodes, array $steps): bool
    {
        foreach ($nodes as $node) {
            if ($this->contains($node->source->file, $this->sourceFilter ?? '')) {
                return true;
            }
        }

        return array_any($steps, fn (StepNode $step): bool => $this->contains($step->source->file, $this->sourceFilter ?? ''));
    }

    /**
     * @param  list<StepNode>  $steps
     */
    private function matchesStatus(ScenarioNode $scenario, array $steps): bool
    {
        if ($scenario->status->value === $this->statusFilter) {
            return true;
        }

        return array_any($steps, fn (StepNode $step): bool => $step->status->value === $this->statusFilter);
    }

    private function contains(string $value, string $needle): bool
    {
        return stripos($value, $needle) !== false;
    }

    private function normalize(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s cannot be empty.', $field));
        }

        return $value;
    }
}
