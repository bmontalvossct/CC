<?php

declare(strict_types=1);

namespace NunoMaduro\Collision\Adapters\Phpunit;

use NunoMaduro\Collision\Adapters\Phpunit\Subscribers\EnsurePrinterIsRegisteredSubscriber;
use PHPUnit\Runner\Version;

if (PHP_SAPI === 'cli' && defined('PHPUNIT_COMPOSER_INSTALL')) {
    try {
        if (class_exists(Version::class) && method_exists(Version::class, 'series') && (int) Version::series() >= 10) {
            if (class_exists(EnsurePrinterIsRegisteredSubscriber::class)) {
                EnsurePrinterIsRegisteredSubscriber::register();
            }
        }
    } catch (\Throwable) {
        // Silently ignore
    }
}
