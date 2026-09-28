<?php

declare(strict_types=1);

namespace Pest\Flow\Reporting;

use Pest\Flow\FlowRegistry;
use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\StepNode;

/**
 * Renders the registered behaviour tree for terminal output.
 */
final class ConsoleReporter
{
    /**
     * @var list<string>
     */
    private array $lines = [];

    public function render(bool $color = false): string
    {
        $this->lines = [];

        foreach (FlowRegistry::features() as $feature) {
            if ($this->lines !== []) {
                $this->lines[] = '';
            }

            $this->lines[] = 'Feature: '.$feature->name;

            foreach ($feature->rules() as $rule) {
                $this->lines[] = '';
                $this->lines[] = '  Rule: '.$rule->name;

                foreach ($rule->scenarios() as $scenario) {
                    $this->lines[] = '';
                    $this->appendScenario($scenario, 4, false, $color);
                }
            }
        }

        $standaloneScenarios = array_values(array_filter(
            FlowRegistry::scenarios(),
            static fn (ScenarioNode $scenario): bool => $scenario->rule === null,
        ));

        foreach ($standaloneScenarios as $index => $scenario) {
            if ($this->lines !== [] || $index > 0) {
                $this->lines[] = '';
            }

            $this->appendScenario($scenario, 0, true, $color);
        }

        return implode(PHP_EOL, $this->lines);
    }

    private function appendScenario(
        ScenarioNode $scenario,
        int $indent,
        bool $standalone = false,
        bool $color = false,
    ): void {
        $prefix = str_repeat(' ', $indent);
        $title = $standalone ? 'Scenario: '.$scenario->name : $scenario->name;

        $this->lines[] = $prefix.$this->symbol($scenario->status, $color).' '.$title;

        foreach ($scenario->steps() as $step) {
            $this->appendStep($step, $indent + 2, $color);
        }
    }

    private function appendStep(StepNode $step, int $indent, bool $color): void
    {
        $this->lines[] = str_repeat(' ', $indent)
            .$this->symbol($step->status, $color)
            .' '
            .ucfirst($step->type->value)
            .' '
            .$step->description;
    }

    private function symbol(ExecutionStatus $status, bool $color): string
    {
        $symbol = match ($status) {
            ExecutionStatus::Pending => '○',
            ExecutionStatus::Running => '…',
            ExecutionStatus::Passed => '✓',
            ExecutionStatus::Failed => '✗',
            ExecutionStatus::Skipped => '−',
        };

        if (! $color) {
            return $symbol;
        }

        $foreground = match ($status) {
            ExecutionStatus::Pending => 'cyan',
            ExecutionStatus::Running => 'yellow',
            ExecutionStatus::Passed => 'green',
            ExecutionStatus::Failed => 'red',
            ExecutionStatus::Skipped => 'white;options=dim',
        };

        return '<fg='.$foreground.'>'.$symbol.'</>';
    }
}
