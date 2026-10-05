<?php

declare(strict_types=1);

namespace Pest\Flow\Reporting;

use JsonException;
use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\ScenarioNode;

/**
 * Renders a self-contained interactive viewer for a versioned Pest Flow document.
 *
 * @phpstan-type FlowStatus 'pending'|'running'|'passed'|'failed'|'skipped'
 * @phpstan-type FlowSource array{file: string, line: int}
 * @phpstan-type FlowStep array{id: string, type: string, text: string, source: FlowSource, status: FlowStatus, duration: float|null}
 * @phpstan-type FlowScenario array{id: string, name: string, source: FlowSource, tags: list<string>, status: FlowStatus, duration: float|null, steps: list<FlowStep>}
 * @phpstan-type FlowRule array{id: string, name: string, source: FlowSource, tags: list<string>, scenarios: list<FlowScenario>}
 * @phpstan-type FlowFeature array{id: string, name: string, source: FlowSource, tags: list<string>, rules: list<FlowRule>}
 * @phpstan-type FlowDocument array{schema_version: int, features: list<FlowFeature>, standalone_scenarios: list<FlowScenario>}
 */
final class DocumentationReporter
{
    private const int SCHEMA_VERSION = 1;

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     *
     * @throws JsonException
     */
    public function render(array $features, array $standaloneScenarios): string
    {
        $document = (new JsonReporter)->render($features, $standaloneScenarios);

        return $this->renderDocument($document);
    }

    /**
     * @throws JsonException
     * @throws \UnexpectedValueException
     */
    private function renderDocument(string $json): string
    {
        /** @var FlowDocument $document */
        $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if ($document['schema_version'] !== self::SCHEMA_VERSION) {
            throw new \UnexpectedValueException('The Pest Flow document uses an unsupported schema version.');
        }

        $features = $document['features'];
        $standaloneScenarios = $document['standalone_scenarios'];

        $scenarios = $this->scenarios($features, $standaloneScenarios);
        $ruleCount = array_sum(array_map(
            static fn (array $feature): int => count($feature['rules']),
            $features,
        ));
        $stepCount = array_sum(array_map(
            static fn (array $scenario): int => count($scenario['steps']),
            $scenarios,
        ));
        $statuses = array_fill_keys(array_map(
            static fn (ExecutionStatus $status): string => $status->value,
            ExecutionStatus::cases(),
        ), 0);

        foreach ($scenarios as $scenario) {
            $statuses[$scenario['status']]++;
        }

        $embeddedDocument = json_encode(
            $document,
            JSON_HEX_TAG
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_HEX_AMP
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR,
        );

        return '<!doctype html>'.PHP_EOL
            .'<html lang="en">'.PHP_EOL
            .$this->head().PHP_EOL
            .'<body>'.PHP_EOL
            .'  <a class="skip-link" href="#main">Skip to behaviour</a>'.PHP_EOL
            .'  <div class="layout">'.PHP_EOL
            .'    <aside class="sidebar" aria-label="Viewer controls and contents">'.PHP_EOL
            .'      <h1>Pest Flow</h1>'.PHP_EOL
            .'      <button class="theme-toggle" id="theme-toggle" type="button" aria-pressed="false">Toggle theme</button>'.PHP_EOL
            .$this->filters().PHP_EOL
            .'      <noscript><p class="noscript-note">Search and filters need JavaScript. The behaviour remains readable below.</p></noscript>'.PHP_EOL
            .$this->navigation($features, $standaloneScenarios).PHP_EOL
            .'    </aside>'.PHP_EOL
            .'    <main id="main">'.PHP_EOL
            .'      <header class="page-header">'.PHP_EOL
            .'        <h1>Application behaviour</h1>'.PHP_EOL
            .'        <p>Explore features, rules, scenarios, and the steps recorded by Pest Flow.</p>'.PHP_EOL
            .'      </header>'.PHP_EOL
            .$this->summary(count($features), $ruleCount, count($scenarios), $stepCount, $statuses).PHP_EOL
            .'      <p class="filter-results" id="filter-results" role="status" aria-live="polite"></p>'.PHP_EOL
            .'      <div class="behaviour" id="behaviour">'.PHP_EOL
            .$this->behaviour($features, $standaloneScenarios).PHP_EOL
            .'      </div>'.PHP_EOL
            .'    </main>'.PHP_EOL
            .'  </div>'.PHP_EOL
            .'  <script id="flow-document" type="application/json">'.$embeddedDocument.'</script>'.PHP_EOL
            .'  <script>'.$this->viewerScript().'</script>'.PHP_EOL
            .'</body>'.PHP_EOL
            .'</html>'.PHP_EOL;
    }

    private function head(): string
    {
        return implode(PHP_EOL, [
            '<head>',
            '  <meta charset="utf-8">',
            '  <meta name="viewport" content="width=device-width, initial-scale=1">',
            '  <meta name="color-scheme" content="light dark">',
            '  <title>Pest Flow behaviour explorer</title>',
            '  <style>'.$this->styles().'</style>',
            '</head>',
        ]);
    }

    private function filters(): string
    {
        return implode(PHP_EOL, [
            '      <section class="filters" aria-labelledby="filters-title">',
            '        <h2 id="filters-title">Find behaviour</h2>',
            '        <label for="filter-search">Search</label>',
            '        <input id="filter-search" type="search" placeholder="Feature, rule, scenario, step, or tag">',
            '        <label for="filter-status">Status</label>',
            '        <select id="filter-status">',
            '          <option value="">Any status</option>',
            '          <option value="failed">Failed</option>',
            '          <option value="passed">Passed</option>',
            '          <option value="skipped">Skipped</option>',
            '          <option value="pending">Pending</option>',
            '          <option value="running">Running</option>',
            '        </select>',
            '        <label for="filter-tag">Tag</label>',
            '        <select id="filter-tag"><option value="">All tags</option></select>',
            '        <label for="filter-feature">Feature</label>',
            '        <select id="filter-feature"><option value="">All features</option></select>',
            '        <label for="filter-rule">Rule</label>',
            '        <select id="filter-rule"><option value="">All rules</option></select>',
            '        <label for="filter-source">Source file</label>',
            '        <select id="filter-source"><option value="">All source files</option></select>',
            '        <button class="clear-filters" id="clear-filters" type="button">Clear filters</button>',
        ]);
    }

    /**
     * @param  list<FlowFeature>  $features
     * @param  list<FlowScenario>  $standaloneScenarios
     */
    private function navigation(array $features, array $standaloneScenarios): string
    {
        $lines = ['      <nav aria-label="Behaviour contents">', '        <h2>Contents</h2>', '        <ul class="contents">'];

        foreach ($features as $feature) {
            $featureTarget = $this->featureTarget($feature);
            $lines[] = '          <li data-target-id="'.$this->escape($featureTarget).'">'
                .'<a href="#'.$this->escape($featureTarget).'">'.$this->escape($feature['name']).'</a>';

            if ($feature['rules'] !== []) {
                $lines[] = '            <ul>';

                foreach ($feature['rules'] as $rule) {
                    $ruleTarget = $this->ruleTarget($rule);
                    $lines[] = '              <li data-target-id="'.$this->escape($ruleTarget).'">'
                        .'<a href="#'.$this->escape($ruleTarget).'">'.$this->escape($rule['name']).'</a>';

                    if ($rule['scenarios'] !== []) {
                        $lines[] = '                <ul>';

                        foreach ($rule['scenarios'] as $scenario) {
                            $scenarioTarget = $this->scenarioTarget($scenario);
                            $lines[] = '                  <li data-target-id="'.$this->escape($scenarioTarget).'">'
                                .'<a href="#'.$this->escape($scenarioTarget).'">'.$this->escape($scenario['name']).'</a></li>';
                        }

                        $lines[] = '                </ul>';
                    }

                    $lines[] = '              </li>';
                }

                $lines[] = '            </ul>';
            }

            $lines[] = '          </li>';
        }

        if ($standaloneScenarios !== []) {
            $lines[] = '          <li data-target-id="standalone-scenarios"><a href="#standalone-scenarios">Standalone scenarios</a>';
            $lines[] = '            <ul>';

            foreach ($standaloneScenarios as $scenario) {
                $scenarioTarget = $this->standaloneTarget($scenario);
                $lines[] = '              <li data-target-id="'.$this->escape($scenarioTarget).'">'
                    .'<a href="#'.$this->escape($scenarioTarget).'">'.$this->escape($scenario['name']).'</a></li>';
            }

            $lines[] = '            </ul>';
            $lines[] = '          </li>';
        }

        $lines[] = '        </ul>';
        $lines[] = '      </nav>';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, int>  $statuses
     */
    private function summary(int $features, int $rules, int $scenarios, int $steps, array $statuses): string
    {
        $lines = [
            '      <section class="summary" aria-labelledby="summary-title">',
            '        <h2 id="summary-title">Suite overview</h2>',
            '        <dl class="count-grid">',
            $this->count('Features', $features),
            $this->count('Rules', $rules),
            $this->count('Scenarios', $scenarios),
            $this->count('Recorded steps', $steps),
            '        </dl>',
            '        <h3>Scenario status in this run</h3>',
            '        <dl class="status-grid">',
        ];

        foreach (ExecutionStatus::cases() as $status) {
            $lines[] = '          <div class="status-count status-'.$status->value.'"><dt>'.$this->label($status->value).'</dt><dd>'.$statuses[$status->value].'</dd></div>';
        }

        $lines[] = '        </dl>';
        $lines[] = '        <p class="summary-note">Counts include all loaded scenarios. Steps are counted only after Pest records them.</p>';
        $lines[] = '      </section>';

        return implode(PHP_EOL, $lines);
    }

    private function count(string $label, int $count): string
    {
        return '          <div><dt>'.$label.'</dt><dd>'.$count.'</dd></div>';
    }

    /**
     * @param  list<FlowFeature>  $features
     * @param  list<FlowScenario>  $standaloneScenarios
     */
    private function behaviour(array $features, array $standaloneScenarios): string
    {
        if ($features === [] && $standaloneScenarios === []) {
            return '        <p class="empty-state">No behaviour definitions were loaded. Add Pest Flow feature, rule, or standalone scenario declarations to include them here.</p>';
        }

        $lines = [];

        foreach ($features as $feature) {
            $lines[] = $this->feature($feature);
        }

        if ($standaloneScenarios !== []) {
            $lines[] = '        <section class="standalone-group" id="standalone-scenarios" aria-labelledby="standalone-title">';
            $lines[] = '          <h2 id="standalone-title">Standalone scenarios</h2>';

            foreach ($standaloneScenarios as $scenario) {
                $lines[] = $this->scenario($scenario, null, null, 3);
            }

            $lines[] = '        </section>';
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  FlowFeature  $feature
     */
    private function feature(array $feature): string
    {
        $featureTarget = $this->featureTarget($feature);
        $rules = $feature['rules'];
        $scenarios = [];

        foreach ($rules as $rule) {
            array_push($scenarios, ...$rule['scenarios']);
        }

        $steps = array_sum(array_map(static fn (array $scenario): int => count($scenario['steps']), $scenarios));
        $lines = [
            '        <section class="feature" id="'.$this->escape($featureTarget).'" aria-labelledby="title-'.$this->escape($featureTarget).'"'
                .' data-model-id="'.$this->escape($feature['id']).'">',
            '          <header class="node-header">',
            '            <h2 id="title-'.$this->escape($featureTarget).'">'.$this->escape($feature['name']).'</h2>',
            '            <dl class="node-summary">'.$this->count('Rules', count($rules)).$this->count('Scenarios', count($scenarios)).$this->count('Recorded steps', $steps).'</dl>',
            '            <p class="execution-summary">Scenario status: '.$this->statusSummary($scenarios).'</p>',
            $this->tags($feature['tags']),
            $this->source($feature['source']),
            '          </header>',
        ];

        foreach ($rules as $rule) {
            $lines[] = $this->rule($rule, $feature);
        }

        $lines[] = '        </section>';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  FlowRule  $rule
     * @param  FlowFeature  $feature
     */
    private function rule(array $rule, array $feature): string
    {
        $ruleTarget = $this->ruleTarget($rule);
        $lines = [
            '          <section class="rule" id="'.$this->escape($ruleTarget).'" aria-labelledby="title-'.$this->escape($ruleTarget).'"'
                .' data-model-id="'.$this->escape($rule['id']).'">',
            '            <header class="node-header">',
            '              <p class="parent-link">Feature: <a href="#'.$this->escape($this->featureTarget($feature)).'">'.$this->escape($feature['name']).'</a></p>',
            '              <h3 id="title-'.$this->escape($ruleTarget).'">'.$this->escape($rule['name']).'</h3>',
            '              <p class="rule-summary">'.$this->countText('Scenarios', count($rule['scenarios'])).'</p>',
            '              <p class="execution-summary">Scenario status: '.$this->statusSummary($rule['scenarios']).'</p>',
            $this->tags($rule['tags']),
            $this->source($rule['source']),
            '            </header>',
        ];

        foreach ($rule['scenarios'] as $scenario) {
            $lines[] = $this->scenario($scenario, $feature, $rule, 4);
        }

        $lines[] = '          </section>';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  FlowScenario  $scenario
     * @param  FlowFeature|null  $feature
     * @param  FlowRule|null  $rule
     */
    private function scenario(array $scenario, ?array $feature, ?array $rule, int $headingLevel): string
    {
        $target = $feature === null ? $this->standaloneTarget($scenario) : $this->scenarioTarget($scenario);
        $headingTag = 'h'.$headingLevel;
        $featureId = $feature['id'] ?? '';
        $ruleId = $rule['id'] ?? '';
        $lines = [
            '            <article class="scenario status-'.$this->status($scenario['status']).'" id="'.$this->escape($target).'" aria-labelledby="title-'.$this->escape($target).'"'
                .' data-model-id="'.$this->escape($scenario['id']).'" data-feature-id="'.$this->escape($featureId).'" data-rule-id="'.$this->escape($ruleId).'">',
            '              <details class="scenario-details">',
            '                <summary class="scenario-header">',
            '                  <'.$headingTag.' id="title-'.$this->escape($target).'">'.$this->escape($scenario['name']).'</'.$headingTag.'>',
            '                  <span class="status-label">'.$this->label($scenario['status']).'</span>',
            '                </summary>',
            '                <div class="scenario-metadata">',
            $this->duration($scenario['duration']),
            $this->tags($scenario['tags']),
            $this->source($scenario['source']),
            '                </div>',
        ];

        if ($scenario['steps'] !== []) {
            $lines[] = '                <ol class="steps flow" aria-label="Given, When, Then behavioural flow">';

            foreach ($scenario['steps'] as $step) {
                $lines[] = $this->step($step);
            }

            $lines[] = '                </ol>';
        } else {
            $lines[] = '                <p class="no-steps">No steps were recorded in this run.</p>';
        }

        $lines[] = '              </details>';
        $lines[] = '            </article>';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  FlowStep  $step
     */
    private function step(array $step): string
    {
        $target = 'step-'.$step['id'];

        return '                  <li class="step flow-step status-'.$this->status($step['status']).'" id="'.$this->escape($target).'"'
            .'>'
            .'<span class="step-status">'.$this->label($step['status']).'</span> '
            .'<span class="step-type">'.$this->escape(ucfirst($step['type'])).'</span> '
            .'<span class="step-text">'.$this->escape($step['text']).'</span>'
            .$this->source($step['source'])
            .'</li>';
    }

    /**
     * @param  list<string>  $tags
     */
    private function tags(array $tags): string
    {
        if ($tags === []) {
            return '';
        }

        $items = array_map(
            fn (string $tag): string => '<li>'.$this->escape($tag).'</li>',
            $tags,
        );

        return '            <ul class="tags" aria-label="Tags">'.implode('', $items).'</ul>';
    }

    /**
     * @param  FlowSource  $source
     */
    private function source(array $source): string
    {
        if ($source['file'] === 'unknown' || $source['line'] < 1) {
            return '';
        }

        $location = $source['file'].':'.$source['line'];

        return '<p class="source"><span>Source</span> <code>'.$this->escape($location).'</code> '
            .'<button class="copy-source" type="button" data-copy="'.$this->escape($location).'">Copy location</button></p>';
    }

    private function duration(?float $duration): string
    {
        if ($duration === null) {
            return '';
        }

        return '<p class="duration"><span>Duration</span> '.number_format($duration, 3, '.', '').' s</p>';
    }

    private function countText(string $label, int $count): string
    {
        return $count.' '.$label;
    }

    /**
     * @param  list<FlowScenario>  $scenarios
     */
    private function statusSummary(array $scenarios): string
    {
        $counts = array_fill_keys(array_map(
            static fn (ExecutionStatus $status): string => $status->value,
            ExecutionStatus::cases(),
        ), 0);

        foreach ($scenarios as $scenario) {
            $counts[$scenario['status']]++;
        }

        return implode(', ', array_map(
            fn (ExecutionStatus $status): string => $counts[$status->value].' '.$status->value,
            ExecutionStatus::cases(),
        ));
    }

    private function label(string $status): string
    {
        return ucfirst($status);
    }

    private function status(string $status): string
    {
        $knownStatuses = array_map(static fn (ExecutionStatus $value): string => $value->value, ExecutionStatus::cases());

        return in_array($status, $knownStatuses, true) ? $status : ExecutionStatus::Pending->value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param  list<FlowFeature>  $features
     * @param  list<FlowScenario>  $standaloneScenarios
     * @return list<FlowScenario>
     */
    private function scenarios(array $features, array $standaloneScenarios): array
    {
        $scenarios = $standaloneScenarios;

        foreach ($features as $feature) {
            foreach ($feature['rules'] as $rule) {
                $scenarios = [...$scenarios, ...$rule['scenarios']];
            }
        }

        return $scenarios;
    }

    /**
     * @param  FlowFeature  $feature
     */
    private function featureTarget(array $feature): string
    {
        return 'feature-'.$feature['id'];
    }

    /**
     * @param  FlowRule  $rule
     */
    private function ruleTarget(array $rule): string
    {
        return 'rule-'.$rule['id'];
    }

    /**
     * @param  FlowScenario  $scenario
     */
    private function scenarioTarget(array $scenario): string
    {
        return 'scenario-'.$scenario['id'];
    }

    /**
     * @param  FlowScenario  $scenario
     */
    private function standaloneTarget(array $scenario): string
    {
        return 'standalone-scenario-'.$scenario['id'];
    }

    private function styles(): string
    {
        return <<<'CSS'
:root{color-scheme:light dark;--bg:#f5f7fb;--panel:#fff;--ink:#182230;--muted:#58677a;--line:#d8e0ea;--accent:#2459a6;--good:#18794e;--bad:#b42318;--warn:#8b5e00;--skip:#58677a;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;font-variant-numeric:tabular-nums}
:root[data-theme="light"]{color-scheme:light}:root[data-theme="dark"]{color-scheme:dark;--bg:#10151d;--panel:#171f2a;--ink:#eef3f9;--muted:#aab8c8;--line:#344252;--accent:#8ab4f8;--good:#64d49b;--bad:#ff8a80;--warn:#ffd166;--skip:#aab8c8}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);accent-color:var(--accent)}::selection{background:var(--accent);color:var(--panel)}a{color:var(--accent);text-decoration-thickness:.08em;text-underline-offset:.18em}
body{caret-color:var(--accent)}*{scrollbar-color:var(--muted) var(--panel);scrollbar-width:thin}.page-header h1{font-size:2.5rem}
a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,summary:focus-visible{outline:3px solid var(--accent);outline-offset:3px}
.skip-link{position:absolute;left:-10000px;top:1rem;background:var(--panel);padding:.6rem}.skip-link:focus{left:1rem;z-index:2}
.layout{display:grid;grid-template-columns:minmax(17rem,22rem) minmax(0,1fr);max-width:100rem;margin:auto;min-height:100vh}.sidebar{position:sticky;top:0;align-self:start;max-height:100vh;overflow:auto;padding:1.6rem 1.3rem;background:var(--panel);border-right:1px solid var(--line)}
.sidebar h1{font-size:1.15rem;letter-spacing:-.02em;margin:0 0 .7rem}.theme-toggle,.clear-filters{display:block;margin:.5rem 0 1rem;padding:.5rem .75rem;border:1px solid var(--line);border-radius:.4rem;background:var(--bg);color:var(--ink);font:inherit;cursor:pointer}.theme-toggle:hover,.clear-filters:hover{border-color:var(--accent);color:var(--accent)}
.filters{display:grid;gap:.35rem;margin:1.25rem 0 1.7rem}.filters h2,.sidebar nav h2{font-size:.85rem;letter-spacing:.02em;margin:0 0 .2rem}.filters label{font-size:.78rem;font-weight:650;margin-top:.25rem}.filters input,.filters select{width:100%;min-height:2.4rem;padding:.35rem .5rem;border:1px solid var(--line);border-radius:.35rem;background:var(--bg);color:var(--ink);font:inherit;font-size:.84rem}.clear-filters{width:100%;margin:.7rem 0 0}
.contents,.contents ul{list-style:none;margin:.35rem 0;padding-left:.9rem}.contents{padding:0}.contents li{margin:.48rem 0}.contents a{display:block;overflow-wrap:anywhere;text-decoration:none;line-height:1.4}.contents a:hover{text-decoration:underline}.contents ul{border-left:1px solid var(--line)}[hidden]{display:none!important}
main{min-width:0;padding:clamp(1.3rem,4vw,3.75rem)}.page-header{max-width:76ch;padding-bottom:1.5rem;border-bottom:1px solid var(--line)}.eyebrow{color:var(--accent);font-size:.78rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin:0 0 .4rem}.page-header h1{font-size:clamp(2rem,4vw,3.1rem);letter-spacing:-.03em;line-height:1.08;margin:0 0 .7rem}.page-header p:last-child{color:var(--muted);margin:0}.summary{max-width:76ch;padding:1.3rem 0;margin:1.3rem 0 .5rem;border-bottom:1px solid var(--line)}.summary h2{font-size:1.1rem;margin:0 0 .8rem}.summary h3{font-size:.9rem;margin:1.1rem 0 .45rem}.count-grid,.status-grid,.node-summary{display:flex;flex-wrap:wrap;gap:.4rem 0;margin:0}.count-grid div,.status-count,.node-summary div{display:flex;align-items:baseline;gap:.35rem;padding:.1rem .8rem;border-right:1px solid var(--line)}.count-grid div:first-child,.status-count:first-child,.node-summary div:first-child{padding-left:0}.count-grid div:last-child,.status-count:last-child,.node-summary div:last-child{border-right:0}.count-grid dt,.status-count dt,.node-summary dt{font-size:.82rem;color:var(--muted)}.count-grid dd,.status-count dd,.node-summary dd{font-size:.92rem;font-weight:700;margin:0}.summary-note,.filter-results{font-size:.82rem;color:var(--muted);margin:.7rem 0 0}.filter-results{max-width:76ch;min-height:1.3em}.behaviour{max-width:76ch}.feature{border-top:1px solid var(--line);padding:1.5rem 0}.feature>.node-header{margin-bottom:1rem}.feature h2{font-size:1.55rem;letter-spacing:-.025em;line-height:1.2;margin:0 0 .45rem}.node-summary{font-size:.82rem;margin:.45rem 0}.rule{padding:0;margin:1.4rem 0 1.7rem}.rule h3{font-size:1.1rem;letter-spacing:-.01em;margin:0 0 .3rem}.parent-link{font-size:.8rem;color:var(--muted);margin:.2rem 0}.rule-summary{font-size:.8rem;color:var(--muted);margin:.25rem 0}.scenario{padding:.9rem 0 .55rem;margin:.8rem 0 0;border-top:1px solid var(--line)}.scenario-details>summary{display:flex;align-items:center;flex-wrap:wrap;gap:.35rem .9rem;cursor:pointer;list-style-position:outside}.scenario-details>summary::marker{color:var(--accent)}.scenario h4,.scenario h3{margin:0;font-size:1rem;font-weight:650}.status-label{display:inline-flex;align-items:center;padding:.05rem .45rem;border:1px solid var(--line);border-radius:999px;color:var(--ink);font-size:.75rem;font-weight:650}.status-passed .status-label{border-color:var(--good)}.status-failed .status-label{border-color:var(--bad)}.status-running .status-label{border-color:var(--warn)}.status-skipped .status-label{border-color:var(--skip)}
.execution-summary{font-size:.78rem;color:var(--muted);margin:.25rem 0}.scenario-metadata{padding:.35rem 0 .1rem}.source,.duration{font-size:.8rem;color:var(--muted);margin:.3rem 0}.source span,.duration span{font-weight:650;margin-right:.3rem}.source code{overflow-wrap:anywhere}.copy-source{margin-left:.45rem;padding:.12rem .4rem;border:1px solid var(--line);border-radius:.3rem;background:var(--panel);color:var(--ink);font:inherit;font-size:.74rem;cursor:pointer}.copy-source:hover{border-color:var(--accent)}
.tags{display:flex;flex-wrap:wrap;gap:.35rem;list-style:none;padding:0;margin:.4rem 0}.tags li{background:var(--bg);border:1px solid var(--line);border-radius:999px;padding:.06rem .48rem;font-size:.74rem}.steps{padding-left:1.8rem;margin:.8rem 0 .3rem}.flow-step{position:relative;padding:.55rem .25rem .75rem;border-bottom:1px solid var(--line)}.flow-step:last-child{border-bottom:0}.flow-step:not(:last-child)::after{content:"↓";position:absolute;bottom:-.68rem;left:-1.25rem;z-index:1;color:var(--muted);background:var(--bg);padding:0 .2rem}.step-status{display:inline-block;min-width:4.2rem;color:var(--muted);font-size:.75rem;font-weight:650}.status-passed .step-status{color:var(--good)}.status-failed .step-status{color:var(--bad)}.status-running .step-status{color:var(--warn)}.step-type{font-weight:700}.step .source{margin:.3rem 0 0 4.2rem}.no-steps{font-size:.88rem;color:var(--muted);margin:.8rem 0}.standalone-group{border-top:1px solid var(--line);padding-top:1.4rem;margin-top:2rem}.standalone-group h2{font-size:1.45rem;letter-spacing:-.02em}.empty-state{padding:1rem 0;border-block:1px solid var(--line);color:var(--muted)}
@media(prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#10151d;--panel:#171f2a;--ink:#eef3f9;--muted:#aab8c8;--line:#344252;--accent:#8ab4f8;--good:#64d49b;--bad:#ff8a80;--warn:#ffd166;--skip:#aab8c8}}
@media(max-width:760px){.layout{display:block}.sidebar{position:static;max-height:none;border-right:0;border-bottom:1px solid var(--line);padding:1rem 1.1rem}.sidebar nav{max-height:14rem;overflow:auto}main{padding:1.2rem}.page-header h1{font-size:2rem}.count-grid div,.status-count,.node-summary div{padding-inline:.55rem}.count-grid div:first-child,.status-count:first-child,.node-summary div:first-child{padding-left:0}}
@media(print){body{background:#fff;color:#111}.layout{display:block;max-width:none}.sidebar{position:static;max-height:none;border:0;border-bottom:1px solid #999}.filters,.theme-toggle,.copy-source{display:none}.source,.duration,.summary-note{color:#444}a{color:#111;text-decoration:none}.scenario-details:not([open])>*:not(summary){display:block}}
CSS;
    }

    private function viewerScript(): string
    {
        return <<<'JS_WRAP'
        const root = document.documentElement;
        const toggle = document.getElementById('theme-toggle');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        const isDark = () => root.dataset.theme === 'dark' || (!root.dataset.theme && prefersDark.matches);
        const updateTheme = () => toggle.setAttribute('aria-pressed', String(isDark()));

        updateTheme();
        toggle.addEventListener('click', () => {
          root.dataset.theme = isDark() ? 'light' : 'dark';
          updateTheme();
        });
        prefersDark.addEventListener('change', () => {
          if (!root.dataset.theme) updateTheme();
        });

        const documentElement = document.getElementById('flow-document');
        const results = document.getElementById('filter-results');
        const controls = {
          search: document.getElementById('filter-search'),
          status: document.getElementById('filter-status'),
          tag: document.getElementById('filter-tag'),
          feature: document.getElementById('filter-feature'),
          rule: document.getElementById('filter-rule'),
          source: document.getElementById('filter-source'),
        };

        let behaviourDocument;
        try {
          behaviourDocument = JSON.parse(documentElement.textContent);
          if (behaviourDocument.schema_version !== 1) throw new Error('Unsupported schema version.');
        } catch (error) {
          results.textContent = `The behaviour document could not be loaded: ${error.message}`;
          throw error;
        }

        const addOptions = (select, entries) => {
          for (const [value, label] of entries) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            select.append(option);
          }
        };
        const tags = new Set();
        const sourceFiles = new Set();
        const metadataByNode = new Map();
        const unique = (values) => [...new Set(values)];
        const sourceOf = (node) => {
          if (node?.source?.file && node.source.file !== 'unknown') sourceFiles.add(node.source.file);
        };
        const sourceFilesOf = (nodes) => nodes
          .map((node) => node?.source?.file)
          .filter((file) => file && file !== 'unknown');
        const statusesOf = (scenarios) => scenarios.flatMap((scenario) => [
          scenario.status,
          ...(scenario.steps ?? []).map((step) => step.status),
        ]);
        const addNodeMetadata = (target, searchText, nodeTags, nodes, statuses) => {
          const element = document.getElementById(target);
          if (!element) return;
          metadataByNode.set(element, {
            searchText: searchText.join(' ').toLocaleLowerCase(),
            tags: unique(nodeTags),
            sourceFiles: unique(sourceFilesOf(nodes)),
            statuses: unique(statuses),
          });
        };
        const scenarioData = (scenario) => {
          scenario.tags?.forEach((tag) => tags.add(tag));
          sourceOf(scenario);
          scenario.steps?.forEach((step) => sourceOf(step));
        };

        for (const feature of behaviourDocument.features ?? []) {
          feature.tags?.forEach((tag) => tags.add(tag));
          sourceOf(feature);
          addOptions(controls.feature, [[feature.id, feature.name]]);
          const featureStatuses = [];
          for (const rule of feature.rules ?? []) {
            rule.tags?.forEach((tag) => tags.add(tag));
            sourceOf(rule);
            addOptions(controls.rule, [[rule.id, `${feature.name} / ${rule.name}`]]);
            const ruleStatuses = [];
            for (const scenario of rule.scenarios ?? []) {
              scenarioData(scenario);
              const scenarioStatuses = statusesOf([scenario]);
              const ancestors = [feature, rule];
              addNodeMetadata(
                `scenario-${scenario.id}`,
                [feature.name, rule.name, scenario.name, ...scenario.tags, ...(scenario.steps ?? []).flatMap((step) => [step.type, step.text])],
                [...feature.tags, ...rule.tags, ...scenario.tags],
                [...ancestors, scenario, ...(scenario.steps ?? [])],
                scenarioStatuses,
              );
              ruleStatuses.push(...scenarioStatuses);
            }
            featureStatuses.push(...ruleStatuses);
            addNodeMetadata(
              `rule-${rule.id}`,
              [feature.name, rule.name, ...feature.tags, ...rule.tags],
              [...feature.tags, ...rule.tags],
              [feature, rule],
              ruleStatuses,
            );
          }
          addNodeMetadata(`feature-${feature.id}`, [feature.name, ...feature.tags], feature.tags, [feature], featureStatuses);
        }
        for (const scenario of behaviourDocument.standalone_scenarios ?? []) {
          scenarioData(scenario);
          addNodeMetadata(
            `standalone-scenario-${scenario.id}`,
            [scenario.name, ...scenario.tags, ...(scenario.steps ?? []).flatMap((step) => [step.type, step.text])],
            scenario.tags,
            [scenario, ...(scenario.steps ?? [])],
            statusesOf([scenario]),
          );
        }
        addOptions(controls.tag, [...tags].sort((a, b) => a.localeCompare(b)).map((tag) => [tag, tag]));
        addOptions(controls.source, [...sourceFiles].sort((a, b) => a.localeCompare(b)).map((file) => [file, file]));

        const commonMatch = (element, filters) => {
          const metadata = metadataByNode.get(element);
          if (!metadata) return false;
          const searchMatches = !filters.search || metadata.searchText.includes(filters.search);
          const statusMatches = !filters.status || metadata.statuses.includes(filters.status);
          const tagMatches = !filters.tag || metadata.tags.includes(filters.tag);
          const sourceMatches = !filters.source || metadata.sourceFiles.includes(filters.source);
          return searchMatches && statusMatches && tagMatches && sourceMatches;
        };
        const applyFilters = () => {
          const filters = {
            search: controls.search.value.trim().toLocaleLowerCase(),
            status: controls.status.value,
            tag: controls.tag.value,
            feature: controls.feature.value,
            rule: controls.rule.value,
            source: controls.source.value,
          };
          const scenarios = [...document.querySelectorAll('.scenario')];

          for (const scenario of scenarios) {
            const featureMatches = !filters.feature || scenario.dataset.featureId === filters.feature;
            const ruleMatches = !filters.rule || scenario.dataset.ruleId === filters.rule;
            scenario.hidden = !(featureMatches && ruleMatches && commonMatch(scenario, filters));
            if (!scenario.hidden && (filters.search || filters.status === 'failed')) scenario.querySelector('.scenario-details').open = true;
          }

          for (const rule of document.querySelectorAll('.rule')) {
            const childVisible = [...rule.querySelectorAll('.scenario')].some((scenario) => !scenario.hidden);
            const selected = (!filters.feature || rule.closest('.feature').dataset.modelId === filters.feature)
              && (!filters.rule || rule.dataset.modelId === filters.rule);
            rule.hidden = !(selected && (childVisible || commonMatch(rule, filters)));
          }
          for (const feature of document.querySelectorAll('.feature')) {
            const childVisible = [...feature.querySelectorAll('.rule')].some((rule) => !rule.hidden);
            const selected = !filters.feature || feature.dataset.modelId === filters.feature;
            feature.hidden = !(selected && (childVisible || commonMatch(feature, filters)));
          }
          for (const group of document.querySelectorAll('.standalone-group')) {
            const visible = [...group.querySelectorAll('.scenario')].some((scenario) => !scenario.hidden);
            group.hidden = !visible;
          }

          for (const item of document.querySelectorAll('[data-target-id]')) {
            const target = document.getElementById(item.dataset.targetId);
            item.hidden = !target || target.hidden;
          }

          const visibleCount = scenarios.filter((scenario) => !scenario.hidden).length;
          results.textContent = visibleCount === 0 && scenarios.length > 0
            ? `No scenarios match these filters (0 of ${scenarios.length}).`
            : `${visibleCount} of ${scenarios.length} scenarios shown.`;
        };

        for (const control of Object.values(controls)) {
          control.addEventListener(control === controls.search ? 'input' : 'change', applyFilters);
        }
        document.getElementById('clear-filters').addEventListener('click', () => {
          for (const control of Object.values(controls)) control.value = '';
          applyFilters();
          controls.search.focus();
        });
        applyFilters();

        document.querySelector('nav').addEventListener('click', (event) => {
          const link = event.target.closest('a[href^="#"]');
          if (!link) return;
          const target = document.getElementById(link.hash.slice(1));
          const details = target?.querySelector('.scenario-details');
          if (details) details.open = true;
        });

        const copyFallback = (value) => {
          const input = document.createElement('textarea');
          input.value = value;
          input.setAttribute('readonly', '');
          input.style.position = 'fixed';
          input.style.opacity = '0';
          document.body.append(input);
          input.select();
          const copied = document.execCommand('copy');
          input.remove();
          if (!copied) throw new Error('Copy is not available in this browser.');
        };
        document.addEventListener('click', async (event) => {
          const button = event.target.closest('.copy-source');
          if (!button) return;
          try {
            if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(button.dataset.copy);
            else copyFallback(button.dataset.copy);
            button.textContent = 'Copied';
          } catch {
            try {
              copyFallback(button.dataset.copy);
              button.textContent = 'Copied';
            } catch {
              button.textContent = 'Copy failed';
            }
          }
        });
        JS_WRAP;
    }
}
