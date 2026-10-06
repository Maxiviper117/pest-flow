<?php

declare(strict_types=1);

namespace Pest\Flow\Impact;

/**
 * @internal
 */
interface ImpactProvider
{
    /**
     * @param  list<string>  $changedFiles
     */
    public function analyze(array $changedFiles): ImpactProviderResult;
}
