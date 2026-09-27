<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * @internal
 */
final class NodeIdentifier
{
    /**
     * @param list<string> $siblings
     */
    public static function child(string $parent, string $name, array $siblings = []): string
    {
        return self::unique(rtrim($parent, '/') . '/' . self::fromName($name), $siblings);
    }

    public static function fromName(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'node-' . substr(hash('sha256', $name), 0, 12);
    }

    /**
     * @param list<string> $siblings
     */
    public static function unique(string $candidate, array $siblings): string
    {
        if (! in_array($candidate, $siblings, true)) {
            return $candidate;
        }

        $index = 2;
        $unique = $candidate . '-' . $index;

        while (in_array($unique, $siblings, true)) {
            $index++;
            $unique = $candidate . '-' . $index;
        }

        return $unique;
    }
}
