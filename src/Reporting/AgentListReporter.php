<?php

declare(strict_types=1);

namespace Pest\Flow\Reporting;

use Pest\Flow\Query\BehaviourQuery;

/**
 * Renders a compact plain-text behaviour listing for developers and tools.
 *
 * @internal
 */
final class AgentListReporter
{
    public function render(BehaviourQuery $query): string
    {
        if (! $query->hasResults()) {
            return 'No matching Pest Flow behaviour found.'.PHP_EOL;
        }

        $lines = [];

        foreach ($query->features() as $feature) {
            $lines[] = 'Feature: '.$feature->name;

            foreach ($query->rulesFor($feature) as $rule) {
                $lines[] = '  Rule: '.$rule->name;

                foreach ($query->scenariosFor($rule) as $scenario) {
                    $lines[] = sprintf(
                        '    Scenario [%s]: %s (%s:%d)',
                        $scenario->status->value,
                        $scenario->name,
                        $scenario->source->file,
                        $scenario->source->line,
                    );

                    foreach ($query->stepsFor($scenario) as $step) {
                        $lines[] = sprintf(
                            '      %s [%s]: %s (%s:%d)',
                            ucfirst($step->type->value),
                            $step->status->value,
                            $step->description,
                            $step->source->file,
                            $step->source->line,
                        );
                    }
                }
            }
        }

        foreach ($query->standaloneScenarios() as $scenario) {
            $lines[] = sprintf(
                'Scenario [%s]: %s (%s:%d)',
                $scenario->status->value,
                $scenario->name,
                $scenario->source->file,
                $scenario->source->line,
            );

            foreach ($query->stepsFor($scenario) as $step) {
                $lines[] = sprintf(
                    '  %s [%s]: %s (%s:%d)',
                    ucfirst($step->type->value),
                    $step->status->value,
                    $step->description,
                    $step->source->file,
                    $step->source->line,
                );
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
