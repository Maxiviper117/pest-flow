<?php

declare(strict_types=1);

namespace Pest\Flow\Plugins;

use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;

/**
 * Emits read-only behaviour queries after Pest has collected tests but before it runs them.
 *
 * @internal
 */
final readonly class AgentQuerySubscriber implements LoadedSubscriber
{
    public function __construct(private ConsoleReporterPlugin $plugin) {}

    public function notify(Loaded $event): void
    {
        if (! $this->plugin->shouldRunAgentQueryBeforeTests()) {
            return;
        }

        $this->plugin->runAgentQueryBeforeTests();
    }
}
