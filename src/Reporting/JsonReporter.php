<?php

declare(strict_types=1);

namespace Pest\Flow\Reporting;

use JsonException;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;

/**
 * Serializes the behaviour tree as a versioned JSON document.
 */
final class JsonReporter
{
    private const SCHEMA_VERSION = 1;

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     *
     * @throws JsonException
     */
    public function render(array $features, array $standaloneScenarios): string
    {
        $json = json_encode(
            [
                'schema_version' => self::SCHEMA_VERSION,
                'features' => array_map(
                    $this->feature(...),
                    $features,
                ),
                'standalone_scenarios' => array_map(
                    $this->scenario(...),
                    $standaloneScenarios,
                ),
            ],
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
    private function feature(FeatureNode $feature): array
    {
        return [
            'id' => $feature->id,
            'name' => $feature->name,
            'source' => $this->source($feature->source),
            'tags' => [],
            'rules' => array_map(
                $this->rule(...),
                $feature->rules(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rule(RuleNode $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'source' => $this->source($rule->source),
            'tags' => [],
            'scenarios' => array_map(
                $this->scenario(...),
                $rule->scenarios(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scenario(ScenarioNode $scenario): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'source' => $this->source($scenario->source),
            'tags' => [],
            'status' => $scenario->status->value,
            'duration' => $scenario->duration,
            'steps' => array_map(
                $this->step(...),
                $scenario->steps(),
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
