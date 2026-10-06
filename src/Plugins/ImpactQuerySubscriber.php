<?php

declare(strict_types=1);

namespace Pest\Flow\Plugins;

use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;

/**
 * Emits read-only behaviour impact after collection and before test execution.
 *
 * @internal
 */
final readonly class ImpactQuerySubscriber implements LoadedSubscriber
{
    public function __construct(private ConsoleReporterPlugin $plugin) {}

    public function notify(Loaded $event): void
    {
        if (! $this->plugin->shouldRunImpactBeforeTests()) {
            return;
        }

        $this->plugin->runImpactBeforeTests();
    }
}
