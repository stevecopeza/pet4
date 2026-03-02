<?php

declare(strict_types=1);

/**
 * PET Test Bootstrap
 *
 * Loads Composer autoloader and stubs WordPress functions/constants
 * so domain-layer unit tests can run without a WordPress environment.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ── WordPress constant stubs (only define if not already defined) ──
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wp-stub/');
}

// ── WordPress function stubs used by domain/application code ──
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return 1;
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($gmt ? 'UTC' : 'UTC')))->format(
            $type === 'mysql' ? 'Y-m-d H:i:s' : 'U'
        );
    }
}
