<?php

declare(strict_types=1);

namespace Pest\Flow\Reporting;

use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;

/**
 * Renders the Pest Flow behaviour model as a self-contained HTML document.
 */
final class DocumentationReporter
{
    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     */
    public function render(array $features, array $standaloneScenarios): string
    {
        $scenarios = $this->scenarios($features, $standaloneScenarios);
        $rules = array_sum(array_map(
            static fn (FeatureNode $feature): int => count($feature->rules()),
            $features,
        ));
        $steps = array_sum(array_map(
            static fn (ScenarioNode $scenario): int => count($scenario->steps()),
            $scenarios,
        ));
        $statuses = array_fill_keys(array_map(
            static fn (ExecutionStatus $status): string => $status->value,
            ExecutionStatus::cases(),
        ), 0);

        foreach ($scenarios as $scenario) {
            $statuses[$scenario->status->value]++;
        }

        $lines = [
            '<!doctype html>',
            '<html lang="en">',
            '<head>',
            '  <meta charset="utf-8">',
            '  <meta name="viewport" content="width=device-width, initial-scale=1">',
            '  <meta name="color-scheme" content="light dark">',
            '  <title>Pest Flow living documentation</title>',
            '  <style>'.$this->styles().'</style>',
            '</head>',
            '<body>',
            '  <a class="skip-link" href="#main">Skip to behaviour</a>',
            '  <div class="layout">',
            '    <aside class="sidebar">',
            '      <h1>Pest Flow</h1>',
            '      <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Toggle color theme" aria-pressed="false">Toggle theme</button>',
            $this->navigation($features, $standaloneScenarios),
            '    </aside>',
            '    <main id="main">',
            '      <header class="page-header">',
            '        <h1>Application behaviour</h1>',
            '        <p>Features, rules, scenarios, and the steps recorded by Pest Flow.</p>',
            '      </header>',
            $this->summary(count($features), $rules, count($scenarios), $steps, $statuses),
            '      <div class="behaviour">',
        ];

        if ($features === [] && $standaloneScenarios === []) {
            $lines[] = '        <p class="empty-state">No Pest Flow behaviour was found.</p>';
        }

        foreach ($features as $feature) {
            $lines[] = $this->feature($feature);
        }

        if ($standaloneScenarios !== []) {
            $lines[] = '        <section class="standalone-group" aria-labelledby="standalone-scenarios">';
            $lines[] = '          <h2 id="standalone-scenarios">Standalone scenarios</h2>';

            foreach ($standaloneScenarios as $scenario) {
                $lines[] = $this->scenario($scenario, 3);
            }

            $lines[] = '        </section>';
        }

        array_push(
            $lines,
            '      </div>',
            '    </main>',
            '  </div>',
            '  <script>'.$this->themeToggle().'</script>',
            '</body>',
            '</html>',
            '',
        );

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     */
    private function navigation(array $features, array $standaloneScenarios): string
    {
        $lines = ['      <nav aria-label="Behaviour contents">', '        <h2>Contents</h2>', '        <ul class="contents">'];

        foreach ($features as $feature) {
            $lines[] = '          <li><a href="#'.$this->escape($feature->id).'">'.$this->escape($feature->name).'</a>';

            if ($feature->rules() !== []) {
                $lines[] = '            <ul>';

                foreach ($feature->rules() as $rule) {
                    $lines[] = '              <li><a href="#'.$this->escape($rule->id).'">'.$this->escape($rule->name).'</a>';

                    if ($rule->scenarios() !== []) {
                        $lines[] = '                <ul>';

                        foreach ($rule->scenarios() as $scenario) {
                            $lines[] = '                  <li><a href="#'.$this->escape($scenario->id).'">'.$this->escape($scenario->name).'</a></li>';
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
            $lines[] = '          <li><a href="#standalone-scenarios">Standalone scenarios</a>';
            $lines[] = '            <ul>';

            foreach ($standaloneScenarios as $scenario) {
                $lines[] = '              <li><a href="#'.$this->escape($scenario->id).'">'.$this->escape($scenario->name).'</a></li>';
            }

            array_push($lines, '            </ul>', '          </li>');
        }

        array_push($lines, '        </ul>', '      </nav>');

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, int>  $statuses
     */
    private function summary(int $features, int $rules, int $scenarios, int $steps, array $statuses): string
    {
        $lines = [
            '      <section class="summary" aria-labelledby="summary-title">',
            '        <h2 id="summary-title">Summary</h2>',
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
            $lines[] = '          <div class="status-count status-'.$status->value.'"><dt>'.$this->label($status).'</dt><dd>'.$statuses[$status->value].'</dd></div>';
        }

        array_push($lines, '        </dl>', '        <p class="summary-note">Counts include all loaded scenarios. Steps are counted only after Pest records them.</p>', '      </section>');

        return implode(PHP_EOL, $lines);
    }

    private function count(string $label, int $count): string
    {
        return '          <div><dt>'.$label.'</dt><dd>'.$count.'</dd></div>';
    }

    private function feature(FeatureNode $feature): string
    {
        $lines = [
            '        <section class="feature" id="'.$this->escape($feature->id).'" aria-labelledby="title-'.$this->escape($feature->id).'">',
            '          <header class="node-header">',
            '            <h2 id="title-'.$this->escape($feature->id).'">'.$this->escape($feature->name).'</h2>',
            $this->tags($feature->tags()),
            $this->source($feature->source),
            '          </header>',
        ];

        foreach ($feature->rules() as $rule) {
            $lines[] = $this->rule($rule);
        }

        array_push($lines, '        </section>');

        return implode(PHP_EOL, $lines);
    }

    private function rule(RuleNode $rule): string
    {
        $lines = [
            '          <section class="rule" id="'.$this->escape($rule->id).'" aria-labelledby="title-'.$this->escape($rule->id).'">',
            '            <header class="node-header">',
            '              <h3 id="title-'.$this->escape($rule->id).'">'.$this->escape($rule->name).'</h3>',
            $this->tags($rule->tags()),
            $this->source($rule->source),
            '            </header>',
        ];

        foreach ($rule->scenarios() as $scenario) {
            $lines[] = $this->scenario($scenario, 4);
        }

        array_push($lines, '          </section>');

        return implode(PHP_EOL, $lines);
    }

    private function scenario(ScenarioNode $scenario, int $headingLevel): string
    {
        $headingTag = 'h'.$headingLevel;
        $lines = [
            '            <article class="scenario status-'.$scenario->status->value.'" id="'.$this->escape($scenario->id).'" aria-labelledby="title-'.$this->escape($scenario->id).'">',
            '              <header class="scenario-header">',
            '                <'.$headingTag.' id="title-'.$this->escape($scenario->id).'">'.$this->escape($scenario->name).'</'.$headingTag.'>',
            '                <p class="status-label"><span>Status</span><strong>'.$this->label($scenario->status).'</strong></p>',
            $this->duration($scenario->duration),
            $this->tags($scenario->tags()),
            $this->source($scenario->source),
            '              </header>',
        ];

        if ($scenario->steps() !== []) {
            $lines[] = '              <ol class="steps">';

            foreach ($scenario->steps() as $step) {
                $lines[] = $this->step($step);
            }

            $lines[] = '              </ol>';
        } else {
            $lines[] = '              <p class="no-steps">No steps were recorded in this run.</p>';
        }

        $lines[] = '            </article>';

        return implode(PHP_EOL, $lines);
    }

    private function step(StepNode $step): string
    {
        return '                <li class="step status-'.$step->status->value.'">'
            .'<span class="status-label">'.$this->label($step->status).'</span> '
            .'<span class="step-type">'.$this->escape(ucfirst($step->type->value)).'</span> '
            .$this->escape($step->description)
            .$this->source($step->source)
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

    private function source(SourceLocation $source): string
    {
        if ($source->file === 'unknown' || $source->line < 1) {
            return '';
        }

        return '<p class="source"><span>Source</span> <code>'.$this->escape($source->file).':'.$source->line.'</code></p>';
    }

    private function duration(?float $duration): string
    {
        if ($duration === null) {
            return '';
        }

        return '<p class="duration"><span>Duration</span> '.number_format($duration, 3, '.', '').' s</p>';
    }

    private function label(ExecutionStatus $status): string
    {
        return ucfirst($status->value);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param  list<FeatureNode>  $features
     * @param  list<ScenarioNode>  $standaloneScenarios
     * @return list<ScenarioNode>
     */
    private function scenarios(array $features, array $standaloneScenarios): array
    {
        $scenarios = $standaloneScenarios;

        foreach ($features as $feature) {
            foreach ($feature->rules() as $rule) {
                array_push($scenarios, ...$rule->scenarios());
            }
        }

        return $scenarios;
    }

    private function styles(): string
    {
        return <<<'CSS'
:root{color-scheme:light dark;--bg:#f5f7fb;--panel:#fff;--ink:#182230;--muted:#58677a;--line:#d8e0ea;--accent:#2459a6;--good:#18794e;--bad:#b42318;--warn:#8b5e00;--skip:#58677a;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;font-variant-numeric:tabular-nums}:root[data-theme="light"]{color-scheme:light}:root[data-theme="dark"]{color-scheme:dark;--bg:#10151d;--panel:#171f2a;--ink:#eef3f9;--muted:#aab8c8;--line:#344252;--accent:#8ab4f8;--good:#64d49b;--bad:#ff8a80;--warn:#ffd166;--skip:#aab8c8}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);accent-color:var(--accent)}::selection{background:var(--accent);color:var(--panel)}a{color:var(--accent);text-decoration-thickness:.08em;text-underline-offset:.18em}a:focus-visible,.theme-toggle:focus-visible{outline:3px solid var(--accent);outline-offset:3px}.skip-link{position:absolute;left:-10000px;top:1rem;background:var(--panel);padding:.6rem}.skip-link:focus{left:1rem;z-index:2}.layout{display:grid;grid-template-columns:minmax(16rem,21rem) minmax(0,1fr);max-width:90rem;margin:auto;min-height:100vh}.sidebar{position:sticky;top:0;align-self:start;max-height:100vh;overflow:auto;padding:2rem 1.5rem;background:var(--panel);border-right:1px solid var(--line)}.sidebar h1{font-size:1.15rem;letter-spacing:-.02em;margin:0 0 1.5rem}.theme-toggle{display:block;margin:0 0 1.5rem;padding:.5rem .75rem;border:1px solid var(--line);border-radius:.4rem;background:var(--bg);color:var(--ink);font:inherit;cursor:pointer}.theme-toggle:hover{border-color:var(--accent);color:var(--accent)}.sidebar nav h2{font-size:.78rem;font-weight:700;letter-spacing:.04em;color:var(--muted);margin:0 0 .7rem}.contents,.contents ul{list-style:none;margin:.35rem 0;padding-left:.9rem}.contents{padding:0}.contents li{margin:.48rem 0}.contents a{display:block;overflow-wrap:anywhere;text-decoration:none;line-height:1.4}.contents a:hover{text-decoration:underline}.contents ul{border-left:1px solid var(--line)}main{min-width:0;padding:clamp(1.4rem,4vw,3.75rem)}.page-header{max-width:72ch;padding-bottom:1.6rem;border-bottom:1px solid var(--line)}.page-header h1{font-size:clamp(2rem,4vw,3.1rem);letter-spacing:-.03em;line-height:1.08;margin:0 0 .7rem}.page-header p{color:var(--muted);margin:0}.summary{max-width:72ch;padding:1.5rem 0;margin:1.4rem 0 2rem;border-bottom:1px solid var(--line)}.summary h2{font-size:1.1rem;letter-spacing:-.01em;margin:0 0 .85rem}.summary h3{font-size:.9rem;margin:1.2rem 0 .5rem}.count-grid,.status-grid{display:flex;flex-wrap:wrap;gap:.45rem 0;margin:0}.count-grid div,.status-count{display:flex;align-items:baseline;gap:.4rem;padding:.1rem .85rem;border-right:1px solid var(--line)}.count-grid div:first-child,.status-count:first-child{padding-left:0}.count-grid div:last-child,.status-count:last-child{border-right:0}.count-grid dt,.status-count dt{font-size:.83rem;color:var(--muted)}.count-grid dd,.status-count dd{font-size:.95rem;font-weight:700;margin:0}.summary-note{font-size:.82rem;color:var(--muted);margin:.85rem 0 0}.behaviour{max-width:72ch}.feature{border-top:1px solid var(--line);padding:1.6rem 0}.feature>.node-header{margin-bottom:1.2rem}.feature h2{font-size:1.65rem;letter-spacing:-.025em;line-height:1.2;margin:0 0 .4rem}.rule{padding:0;margin:1.5rem 0 1.75rem}.rule h3{font-size:1.12rem;letter-spacing:-.01em;margin:0 0 .35rem}.scenario{padding:1rem 0 .5rem;margin:.8rem 0 0;border-top:1px solid var(--line)}.scenario h3,.scenario h4{margin:0;font-size:1.02rem;font-weight:650}.scenario-header{display:flex;align-items:baseline;flex-wrap:wrap;gap:.35rem 1rem}.status-label{display:inline-flex;gap:.35rem;align-items:center;margin:0;color:var(--muted);font-size:.84rem}.status-label strong{color:var(--ink)}.status-passed .status-label strong{color:var(--good)}.status-failed .status-label strong{color:var(--bad)}.status-running .status-label strong{color:var(--warn)}.status-skipped .status-label strong{color:var(--skip)}.source,.duration{font-size:.8rem;color:var(--muted);margin:.35rem 0}.source span,.duration span{font-weight:600;margin-right:.3rem}.source code{overflow-wrap:anywhere}.tags{display:flex;flex-wrap:wrap;gap:.35rem;list-style:none;padding:0;margin:.5rem 0}.tags li{background:var(--bg);border:1px solid var(--line);border-radius:999px;padding:.08rem .5rem;font-size:.76rem}.steps{padding-left:1.8rem;margin:.7rem 0}.step{padding:.5rem 0;border-bottom:1px solid var(--line)}.step:last-child{border-bottom:0}.step .source{margin-left:1.7rem}.step-type{font-weight:700}.no-steps{font-size:.88rem;color:var(--muted);margin:.7rem 0}.standalone-group{border-top:1px solid var(--line);padding-top:1.4rem;margin-top:2rem}.standalone-group h2{font-size:1.5rem;letter-spacing:-.02em}.empty-state{padding:1rem 0;border-block:1px solid var(--line);color:var(--muted)}
@media(prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#10151d;--panel:#171f2a;--ink:#eef3f9;--muted:#aab8c8;--line:#344252;--accent:#8ab4f8;--good:#64d49b;--bad:#ff8a80;--warn:#ffd166;--skip:#aab8c8}}
@media(max-width:760px){.layout{display:block}.sidebar{position:static;max-height:none;border-right:0;border-bottom:1px solid var(--line);padding:1rem 1.2rem}.sidebar nav{max-height:15rem;overflow:auto}.sidebar h1{margin-bottom:.8rem}main{padding:1.3rem 1.2rem}.count-grid div,.status-count{padding-inline:.6rem}.count-grid div:first-child,.status-count:first-child{padding-left:0}}
@media print{body{background:#fff;color:#111}.layout{display:block;max-width:none}.sidebar{position:static;max-height:none;border:0;border-bottom:1px solid #999}.theme-toggle{display:none}.source,.duration,.summary-note{color:#444}a{color:#111;text-decoration:none}}
CSS;
    }

    private function themeToggle(): string
    {
        return <<<'JS'
const root = document.documentElement;
const toggle = document.getElementById('theme-toggle');
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
const isDark = () => root.dataset.theme === 'dark' || (!root.dataset.theme && prefersDark.matches);
const updatePressedState = () => toggle.setAttribute('aria-pressed', String(isDark()));

updatePressedState();
toggle.addEventListener('click', () => {
  root.dataset.theme = isDark() ? 'light' : 'dark';
  updatePressedState();
});

prefersDark.addEventListener('change', () => {
  if (!root.dataset.theme) updatePressedState();
});
JS;
    }
}
