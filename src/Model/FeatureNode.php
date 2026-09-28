<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * A feature node in the discovered behaviour registry.
 */
final class FeatureNode implements TaggableNode
{
    use HasTags;

    /**
     * @var list<RuleNode>
     */
    private array $rules = [];

    public string $id;

    public function __construct(
        public readonly string $name,
        public readonly SourceLocation $source,
    ) {
        $this->id = NodeIdentifier::fromName($name);
    }

    public function addRule(RuleNode $rule): void
    {
        if ($rule->feature !== $this) {
            throw new \LogicException('A rule can only be added to its owning feature.');
        }

        $rule->id = NodeIdentifier::child(
            $this->id,
            $rule->name,
            array_map(static fn (RuleNode $existing): string => $existing->id, $this->rules),
        );

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
