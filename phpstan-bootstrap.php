<?php

/**
 * PHPStan bootstrap: define WordPress constants that are missing from the stubs.
 *
 * This file is loaded before analysis via phpstan.neon bootstrapFiles.
 * It does NOT run at runtime; it exists solely so PHPStan knows about these
 * constants when analysing code that calls them.
 */
if (!defined('ARRAY_A'))            define('ARRAY_A',             'ARRAY_A');
if (!defined('ARRAY_N'))            define('ARRAY_N',             'ARRAY_N');
if (!defined('OBJECT'))             define('OBJECT',              'OBJECT');
if (!defined('ABSPATH'))            define('ABSPATH',             '/');
if (!defined('WP_CONTENT_DIR'))     define('WP_CONTENT_DIR',      '/wp-content');
if (!defined('MINUTE_IN_SECONDS'))  define('MINUTE_IN_SECONDS',   60);
if (!defined('HOUR_IN_SECONDS'))    define('HOUR_IN_SECONDS',     3600);
if (!defined('DAY_IN_SECONDS'))     define('DAY_IN_SECONDS',      86400);
if (!defined('WEEK_IN_SECONDS'))    define('WEEK_IN_SECONDS',     604800);
if (!defined('MONTH_IN_SECONDS'))   define('MONTH_IN_SECONDS',    2592000);
if (!defined('YEAR_IN_SECONDS'))    define('YEAR_IN_SECONDS',     31536000);
if (!defined('WPINC'))              define('WPINC',               'wp-includes');
if (!defined('WP_DEBUG'))           define('WP_DEBUG',            false);
