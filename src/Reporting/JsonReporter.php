<?php

declare(strict_types=1);

namespace Pest\Flow\Reporting;

use JsonException;
use Pest\Flow\Impact\ImpactFeature;
use Pest\Flow\Impact\ImpactResult;
use Pest\Flow\Impact\ImpactRule;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Query\BehaviourQuery;

/**
 * Serializes the behaviour tree as a versioned JSON document.
 */
final class JsonReporter
{
    private const int SCHEMA_VERSION = 1;

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     *
     * @throws JsonException
     */
    public function render(array $features, array $standaloneScenarios, ?BehaviourQuery $query = null): string
    {
        $features = $query?->features() ?? $features;
        $standaloneScenarios = $query?->standaloneScenarios() ?? $standaloneScenarios;

        return $this->encode([
            'schema_version' => self::SCHEMA_VERSION,
            'features' => array_map(
                fn (FeatureNode $feature): array => $this->feature($feature, $query),
                $features,
            ),
            'standalone_scenarios' => array_map(
                fn (ScenarioNode $scenario): array => $this->scenario($scenario, $query),
                $standaloneScenarios,
            ),
        ]);
    }

    /**
     * Serializes an impact result without implying that its scenarios ran.
     *
     * @throws JsonException
     */
    public function renderImpact(ImpactResult $impact): string
    {
        return $this->encode([
            'schema_version' => self::SCHEMA_VERSION,
            'impact' => [
                'status' => $impact->status->value,
                'precision' => $impact->precision,
                'base' => $impact->base,
                'changed_files' => $impact->changedFiles,
                'affected_test_files' => $impact->affectedTestFiles,
                'unrepresented_test_files' => $impact->unrepresentedTestFiles,
                'stale_test_files' => $impact->staleTestFiles,
                'unknown_files' => $impact->unknownFiles,
                'unattributed_test_files' => $impact->unattributedTestFiles,
                'provenance' => (object) $impact->provenance,
                'diagnostics' => $impact->diagnostics,
                'summary' => [
                    'feature_count' => $impact->featureCount(),
                    'rule_count' => $impact->ruleCount(),
                    'scenario_count' => $impact->scenarioCount(),
                    'affected_test_file_count' => count($impact->affectedTestFiles),
                ],
            ],
            'features' => array_map($this->impactFeature(...), $impact->features),
            'standalone_scenarios' => array_map($this->impactScenario(...), $impact->standaloneScenarios),
        ]);
    }

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws JsonException
     */
    private function encode(array $document): string
    {
        $json = json_encode(
            $document,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR,
        );

        // JSON_PRETTY_PRINT uses four spaces; reduce each indentation level to two spaces.
        $json = preg_replace_callback(
            '/^(?: {4})+/m',
            static fn (array $matches): string => str_repeat('  ', intdiv(strlen($matches[0]), 4)),
            $json,
        );

        if ($json === null) {
            throw new JsonException('Unable to format the JSON report.');
        }

        return $json.PHP_EOL;
    }

    /**
     * @return array<string, mixed>
     */
    private function impactFeature(ImpactFeature $feature): array
    {
        return [
            'id' => $feature->node->id,
            'name' => $feature->node->name,
            'source' => $this->source($feature->node->source),
            'tags' => $feature->node->tags(),
            'rules' => array_map($this->impactRule(...), $feature->rules),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function impactRule(ImpactRule $rule): array
    {
        return [
            'id' => $rule->node->id,
            'name' => $rule->node->name,
            'source' => $this->source($rule->node->source),
            'tags' => $rule->node->tags(),
            'scenarios' => array_map($this->impactScenario(...), $rule->scenarios),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function impactScenario(ScenarioNode $scenario): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'source' => $this->source($scenario->source),
            'tags' => $scenario->tags(),
            'steps' => array_map(
                fn (StepNode $step): array => [
                    'id' => $step->id,
                    'type' => $step->type->value,
                    'text' => $step->description,
                    'source' => $this->source($step->source),
                ],
                $scenario->steps(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feature(FeatureNode $feature, ?BehaviourQuery $query): array
    {
        $rules = $query instanceof BehaviourQuery ? $query->rulesFor($feature) : $feature->rules();

        return [
            'id' => $feature->id,
            'name' => $feature->name,
            'source' => $this->source($feature->source),
            'tags' => $feature->tags(),
            'rules' => array_map(
                fn (RuleNode $rule): array => $this->rule($rule, $query),
                $rules,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rule(RuleNode $rule, ?BehaviourQuery $query): array
    {
        $scenarios = $query instanceof BehaviourQuery ? $query->scenariosFor($rule) : $rule->scenarios();

        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'source' => $this->source($rule->source),
            'tags' => $rule->tags(),
            'scenarios' => array_map(
                fn (ScenarioNode $scenario): array => $this->scenario($scenario, $query),
                $scenarios,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scenario(ScenarioNode $scenario, ?BehaviourQuery $query): array
    {
        $steps = $query instanceof BehaviourQuery ? $query->stepsFor($scenario) : $scenario->steps();

        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'source' => $this->source($scenario->source),
            'tags' => $scenario->tags(),
            'status' => $scenario->status->value,
            'duration' => $scenario->duration,
            'steps' => array_map(
                $this->step(...),
                $steps,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(StepNode $step): array
    {
        return [
            'id' => $step->id,
            'type' => $step->type->value,
            'text' => $step->description,
            'source' => $this->source($step->source),
            'status' => $step->status->value,
            'duration' => $step->duration,
        ];
    }

    /**
     * @return array{file: string, line: int}
     */
    private function source(SourceLocation $source): array
    {
        return [
            'file' => $source->file,
            'line' => $source->line,
        ];
    }
}
