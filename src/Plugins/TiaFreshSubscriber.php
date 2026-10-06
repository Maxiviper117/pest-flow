<?php

declare(strict_types=1);

namespace Pest\Flow\Plugins;

use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;

/**
 * Runs the TIA graph setup command before the parent Pest process executes tests.
 *
 * @internal
 */
final readonly class TiaFreshSubscriber implements LoadedSubscriber
{
    public function __construct(private ConsoleReporterPlugin $plugin) {}

    public function notify(Loaded $event): void
    {
        if (! $this->plugin->shouldRunTiaFreshBeforeTests()) {
            return;
        }

        $this->plugin->runTiaFreshBeforeTests();
    }
}
