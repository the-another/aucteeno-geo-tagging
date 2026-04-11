<?php
/**
 * PHPUnit bootstrap file for Aucteeno Nexus Geo-Tagging plugin tests.
 *
 * @package The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests
 */

declare(strict_types=1);

// Load Composer autoloader. Brain Monkey 2.x loads Patchwork on its own
// from Monkey\setUp() in individual test cases — no explicit require here.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Plugin classes are autoloaded via Composer classmap. WordPress function
// stubs are mocked per-test via Brain Monkey — nothing else is needed here.
