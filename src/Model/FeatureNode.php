<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * @internal
 */
final class FeatureNode
{
    /**
     * @var list<RuleNode>
     */
    private array $rules = [];

    public function __construct(
        public readonly string $name,
        public readonly SourceLocation $source,
    ) {}

    public function addRule(RuleNode $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return list<RuleNode>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
