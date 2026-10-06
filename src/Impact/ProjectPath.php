<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

/**
 * Normalizes project paths to slash-separated, project-relative paths.
 *
 * @internal
 */
final class ProjectPath
{
    public static function normalize(string $path, string $projectRoot): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $root = rtrim(str_replace('\\', '/', $projectRoot), '/');

        if ($path === '' || $root === '') {
            return null;
        }

        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[a-z]:\//i', $path) === 1;

        if ($isAbsolute) {
            $prefix = $root.'/';
            $matchesRoot = preg_match('/^[a-z]:\//i', $root) === 1
                ? strncasecmp($path, $prefix, strlen($prefix)) === 0
                : str_starts_with($path, $prefix);

            if (! $matchesRoot) {
                return null;
            }

            $path = substr($path, strlen($prefix));
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}
