# Aucteeno Nexus Geo-Tagging Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `aucteeno-nexus-geo-tagging` WordPress plugin — an opt-in Cloudflare-header-based country/subdivision filter for the Aucteeno Query Loop block — and add one filter hook to the base `aucteeno` plugin to make it work.

**Architecture:** Lightweight extension plugin with three PHP classes (no DI container, no Mozart), autoloaded via PSR-4. The base `aucteeno` plugin gets one new filter (`aucteeno_query_loop_location`) wrapped in a `! $has_product_ids` guard in `blocks/query-loop/render.php`. Editor UI is injected via the standard WordPress `blocks.registerBlockType` + `editor.BlockEdit` JS filters — no base-plugin block.json changes.

**Tech Stack:** PHP 8.3, WordPress 6.9+, PHPUnit 11, Brain Monkey, `@wordpress/scripts` build pipeline, `@wordpress/hooks` / `@wordpress/components` / `@wordpress/block-editor` / `@wordpress/element`, Docker-wrapped Makefile, depot-ubuntu-24.04 GitHub runners.

**Source of truth:** The design spec at `wp-content/plugins/aucteeno-nexus-geo-tagging/docs/superpowers/specs/2026-04-11-aucteeno-nexus-geo-tagging-design.md`. If anything in this plan contradicts the spec, the spec wins — stop and ask.

---

## Repository map

This plan modifies **two repositories**:

1. **`wp-content/plugins/aucteeno-nexus-geo-tagging/`** — new plugin, most tasks. Work directly on `master` (new plugin, not yet released).
2. **`wp-content/plugins/aucteeno/`** — base plugin, one small feature-branch PR. Work on branch `feat/query-loop-geo-tagging-hooks`.

Commit to the correct repo per task. Each task explicitly names which repo it's in.

## Preconditions

Before starting Task 1, verify:

1. **`aucteeno-nexus-geo-tagging` already exists as a git repository** with remote `origin` pointing to `git@github.com:aucteeno/aucteeno-nexus-geo-tagging.git`, and is currently on `master`. Verify with:
   ```bash
   cd wp-content/plugins/aucteeno-nexus-geo-tagging
   git remote -v     # must show aucteeno/aucteeno-nexus-geo-tagging
   git branch --show-current    # must show "master"
   git status         # working tree should be clean; the only tracked content is docs/
   ```
   If the repo does not exist yet, STOP and ask the user to create it — this plan assumes the repo is already initialized and the design spec commit is already pushed.

2. **`aucteeno` is on `master` with a clean working tree.** Verify with `git status` and `git branch --show-current`. If it's not, STOP and resolve before continuing.

3. **Your shell's current working directory at the start of the plan is `/Volumes/DevExtreme/Aucteeno/WordPress/globalag`** (the WordPress root that contains both plugins). All task-level `cd` commands are relative to this.

---

## File structure — what each file is responsible for

### `aucteeno-nexus-geo-tagging` (new plugin)

| File | Responsibility |
|---|---|
| `aucteeno-nexus-geo-tagging.php` | Plugin header, constants, PHP/WP version check, Composer autoloader include, boot hook on `before_woocommerce_init` priority 30. |
| `composer.json` | PSR-4 autoload, dev dependencies (PHPUnit, Brain Monkey, WPCS, VIPCS), scripts. |
| `package.json` | `@wordpress/scripts` dev dep, build/start/lint scripts. |
| `phpunit.xml.dist` | PHPUnit 11 config, tests in `./tests`, source in `./includes`. |
| `.phpcs.xml.dist` | WordPress + VIPCS ruleset, PHP 8.3 target, minimum WP 6.9. |
| `Makefile` | Docker-wrapped targets: `install`, `install-dev`, `build`, `lint`, `format`, `test`, `all`, `clean`, `release`. |
| `Dockerfile` | PHP 8.3-cli + Composer + Node 24 for reproducible builds. |
| `.dockerignore` / `.gitignore` | Standard ignores. |
| `.github/workflows/ci.yml` | CI: build + phpunit + phpcs + lint-js, all on depot-ubuntu-24.04. |
| `.github/workflows/package.yml` | Release: tag + zip + GitHub Release on push to master. |
| `includes/class-cloudflare-headers.php` | Reads and validates `$_SERVER['HTTP_CF_IPCOUNTRY']` and `$_SERVER['HTTP_CF_REGION_CODE']`. Pure, stateless. |
| `includes/class-bot-detector.php` | Matches User-Agent against a curated bot pattern list. Pure, stateless. |
| `includes/class-geo-tagging.php` | Plugin entry point. Wires the `aucteeno_query_loop_location` filter and `enqueue_block_editor_assets` action. Owns `filter_location()` which composes the two helper classes. |
| `assets/js/query-loop-inspector.js` | Registers `blocks.registerBlockType` filter to add attributes + `editor.BlockEdit` HOC filter to inject Inspector panel. |
| `dist/query-loop-inspector.js` | Built artifact (git-ignored, produced by `npm run build`). |
| `dist/query-loop-inspector.asset.php` | Auto-generated asset file for dependency resolution. |
| `tests/bootstrap.php` | Composer autoloader include, Brain Monkey bootstrap, minimal WP function stubs. |
| `tests/Unit/Cloudflare_Headers_Test.php` | ~8 tests for the CF reader. |
| `tests/Unit/Bot_Detector_Test.php` | ~10 tests for UA matching. |
| `tests/Unit/Geo_Tagging_Test.php` | ~10 tests for filter wiring + precedence, using stubs for the two helper classes. |
| `README.md` | Developer docs: dev setup, build commands, test commands. |
| `readme.txt` | WordPress.org-style readme: description, installation, FAQ, changelog. |
| `CLAUDE.md` | Short Claude Code guide pointing at the spec and this plan. |

### `aucteeno` (feature branch `feat/query-loop-geo-tagging-hooks`)

| File | Responsibility |
|---|---|
| `aucteeno.php` | Patch-level version bump. Actual version read dynamically in Task 25. |
| `blocks/query-loop/render.php` | Call `Query_Loop_Location_Filter::apply()` inside a `! $has_product_ids` guard, between current lines 208 and 210. |
| `includes/blocks/class-query-loop-location-filter.php` (new) | Static helper that wraps the `apply_filters( 'aucteeno_query_loop_location', ... )` call and sanitizes the return value. Extracted so the logic is unit-testable in isolation. |
| `tests/Query_Loop_Location_Filter_Test.php` (new) | PHPUnit test for the helper. |
| `CHANGELOG.md` | Patch-level entry noting the new filter hook and helper. |

---

## Chunk 1: Scaffold the new plugin

**Repo:** `wp-content/plugins/aucteeno-nexus-geo-tagging/` (master)

### Task 1: Create `.gitignore` and `.dockerignore`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/.gitignore`
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/.dockerignore`

- [ ] **Step 1: Write `.gitignore`**

```
/vendor/
/node_modules/
/dist/
/build/
/.phpunit.cache/
/.idea/
/.vscode/
.DS_Store
*.log
```

- [ ] **Step 2: Write `.dockerignore`**

```
.git/
vendor/
node_modules/
dist/
build/
.phpunit.cache/
```

- [ ] **Step 3: Commit**

```bash
cd wp-content/plugins/aucteeno-nexus-geo-tagging
git add .gitignore .dockerignore
git commit -m "chore: add gitignore and dockerignore"
```

### Task 2: Create `composer.json`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/composer.json`

- [ ] **Step 1: Write the file**

```json
{
    "name": "theanother/aucteeno-nexus-geo-tagging",
    "description": "Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "version": "0.1.0",
    "author": {
        "name": "The Another",
        "email": "hello@theanother.org",
        "url": "https://theanother.org"
    },
    "homepage": "https://theanother.org/plugin/aucteeno-nexus-geo-tagging/",
    "support": {
        "issues": "https://github.com/aucteeno/aucteeno-nexus-geo-tagging/issues",
        "source": "https://github.com/aucteeno/aucteeno-nexus-geo-tagging"
    },
    "require": {
        "php": ">=8.3"
    },
    "require-dev": {
        "automattic/vipwpcs": "^3.0",
        "brain/monkey": "^2.6",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
        "phpunit/phpunit": "^11.0",
        "squizlabs/php_codesniffer": "^3.9",
        "wp-coding-standards/wpcs": "^3.3"
    },
    "autoload": {
        "classmap": [
            "includes/"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "The_Another\\Plugin\\Aucteeno_Nexus_Geo_Tagging\\Tests\\": "tests/"
        }
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        },
        "sort-packages": true
    },
    "scripts": {
        "test": "phpunit",
        "phpcs": "phpcs",
        "phpcbf": "phpcbf"
    }
}
```

**Why classmap, not PSR-4, for includes/:** The base `aucteeno-nexus` plugin uses classmap with the WordPress `class-name.php` naming convention. We match that pattern — PSR-4 would require file naming like `Cloudflare_Headers.php` which breaks WPCS conventions.

- [ ] **Step 2: Commit**

```bash
git add composer.json
git commit -m "chore: add composer.json with dev deps and autoload"
```

### Task 3: Create `phpunit.xml.dist`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/phpunit.xml.dist`

- [ ] **Step 1: Write the file**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         failOnRisky="true"
         failOnWarning="true"
         requireCoverageMetadata="false"
         beStrictAboutCoverageMetadata="true"
         beStrictAboutOutputDuringTests="true"
         processIsolation="false"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Aucteeno Nexus Geo-Tagging Test Suite">
            <directory>./tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">./includes</directory>
        </include>
        <exclude>
            <directory>./vendor</directory>
        </exclude>
    </source>
    <php>
        <ini name="error_reporting" value="E_ALL"/>
        <ini name="display_errors" value="1"/>
        <ini name="display_startup_errors" value="1"/>
    </php>
</phpunit>
```

- [ ] **Step 2: Commit**

```bash
git add phpunit.xml.dist
git commit -m "chore: add phpunit.xml.dist"
```

### Task 4: Create `.phpcs.xml.dist`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/.phpcs.xml.dist`

- [ ] **Step 1: Write the file**

```xml
<?xml version="1.0"?>
<ruleset name="Aucteeno Nexus Geo-Tagging">
    <description>WordPress Coding Standards and Automattic VIP Coding Standards for Aucteeno Nexus Geo-Tagging plugin</description>

    <file>./includes</file>
    <file>./aucteeno-nexus-geo-tagging.php</file>
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>
    <exclude-pattern>*/dist/*</exclude-pattern>
    <exclude-pattern>*/build/*</exclude-pattern>
    <exclude-pattern>*/.git/*</exclude-pattern>

    <arg value="sp"/>
    <arg name="basepath" value="./"/>
    <arg name="colors"/>
    <arg name="extensions" value="php"/>
    <arg name="parallel" value="8"/>

    <config name="testVersion" value="8.3-"/>
    <config name="minimum_supported_wp_version" value="6.9"/>

    <rule ref="WordPress">
        <exclude name="WordPress.Files.FileName"/>
        <exclude name="WordPress.NamingConventions.PrefixAllGlobals"/>
    </rule>

    <rule ref="WordPress-VIP-Go">
        <exclude name="WordPressVIPMinimum.Functions.RestrictedFunctions"/>
        <exclude name="WordPressVIPMinimum.JS"/>
    </rule>

    <rule ref="WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid">
        <severity>5</severity>
    </rule>
</ruleset>
```

- [ ] **Step 2: Commit**

```bash
git add .phpcs.xml.dist
git commit -m "chore: add phpcs ruleset"
```

### Task 5: Install Composer dependencies

**Files:**
- Touches: `wp-content/plugins/aucteeno-nexus-geo-tagging/vendor/` (git-ignored), `composer.lock` (committed)

- [ ] **Step 1: Run composer install**

```bash
cd wp-content/plugins/aucteeno-nexus-geo-tagging
composer install
```

Expected: successful installation, creates `vendor/` and `composer.lock`. No errors.

- [ ] **Step 2: Verify PHPCS sees WordPress standards**

```bash
./vendor/bin/phpcs -i
```

Expected output contains: `WordPress`, `WordPress-Core`, `WordPress-VIP-Go`.

- [ ] **Step 3: Commit `composer.lock`**

```bash
git add composer.lock
git commit -m "chore: lock composer dependencies"
```

### Task 6: Create main plugin file `aucteeno-nexus-geo-tagging.php`

**Files:**
- Modify: `wp-content/plugins/aucteeno-nexus-geo-tagging/aucteeno-nexus-geo-tagging.php` (currently a 6-byte stub)

- [ ] **Step 1: Replace the stub with the full plugin file**

```php
<?php
/**
 * Plugin Name: Aucteeno Nexus Geo-Tagging
 * Plugin URI: https://theanother.org/plugin/aucteeno-nexus-geo-tagging/
 * Description: Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.
 * Version: 0.1.0
 * Author: The Another
 * Author URI: https://theanother.org
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Requires Plugins: aucteeno
 * Text Domain: aucteeno-nexus-geo-tagging
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/aucteeno/aucteeno-nexus-geo-tagging
 * Primary Branch: master
 * Release Asset: true
 *
 * @package Aucteeno_Nexus_Geo_Tagging
 * @since 0.1.0
 */

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AUCTEENO_NEXUS_GEO_TAGGING_VERSION', '0.1.0' );
define( 'AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_FILE', __FILE__ );
define( 'AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum PHP version check.
if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html( 'Aucteeno Nexus Geo-Tagging requires PHP 8.3 or higher. Please upgrade your PHP version.' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

// Minimum WordPress version check.
if ( version_compare( get_bloginfo( 'version' ), '6.9', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p><?php echo esc_html( 'Aucteeno Nexus Geo-Tagging requires WordPress 6.9 or higher. Please upgrade WordPress.' ); ?></p>
			</div>
			<?php
		}
	);
	return;
}

// Autoloader.
if ( file_exists( AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize plugin after Aucteeno and Aucteeno Nexus load.
add_action(
	'before_woocommerce_init',
	function () {
		$geo_tagging = new Geo_Tagging(
			new Cloudflare_Headers(),
			new Bot_Detector()
		);
		$geo_tagging->init();
	},
	30 // After aucteeno (default) and aucteeno-nexus (20).
);
```

- [ ] **Step 2: Verify PHPCS passes on the file**

```bash
./vendor/bin/phpcs aucteeno-nexus-geo-tagging.php
```

Expected: no errors (the three referenced classes don't exist yet, but PHPCS doesn't resolve class names — it only checks coding standards).

- [ ] **Step 3: Commit**

```bash
git add aucteeno-nexus-geo-tagging.php
git commit -m "feat: add plugin header and boot hook"
```

---

## Chunk 2: `Cloudflare_Headers` class (TDD)

**Repo:** `wp-content/plugins/aucteeno-nexus-geo-tagging/` (master)

### Task 7: Create test bootstrap

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/tests/bootstrap.php`

- [ ] **Step 1: Write the bootstrap**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test: add phpunit bootstrap"
```

### Task 8: Write failing tests for `Cloudflare_Headers`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/tests/Unit/Cloudflare_Headers_Test.php`

- [ ] **Step 1: Write the test file**

```php
<?php
/**
 * Tests for Cloudflare_Headers.
 *
 * @package The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Cloudflare_Headers;

final class Cloudflare_Headers_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// wp_unslash is a pass-through for tests.
		Functions\when( 'wp_unslash' )->returnArg();
		// Reset CF headers between tests.
		unset( $_SERVER['HTTP_CF_IPCOUNTRY'], $_SERVER['HTTP_CF_REGION_CODE'] );
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_CF_IPCOUNTRY'], $_SERVER['HTTP_CF_REGION_CODE'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_country_returns_empty_when_header_missing(): void {
		$cf = new Cloudflare_Headers();
		$this->assertSame( '', $cf->get_country() );
	}

	public function test_get_country_returns_valid_two_letter_code(): void {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
		$cf                           = new Cloudflare_Headers();
		$this->assertSame( 'US', $cf->get_country() );
	}

	public function test_get_country_normalizes_lowercase_to_uppercase(): void {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'ca';
		$cf                           = new Cloudflare_Headers();
		$this->assertSame( 'CA', $cf->get_country() );
	}

	public function test_get_country_rejects_unknown_sentinels(): void {
		$cf = new Cloudflare_Headers();

		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
		$this->assertSame( '', $cf->get_country(), 'XX should be rejected' );

		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'T1';
		$this->assertSame( '', $cf->get_country(), 'T1 should be rejected' );
	}

	public function test_get_country_rejects_malformed_values(): void {
		$cf = new Cloudflare_Headers();

		$malformed = [ 'USA', 'U', '', '12', "' OR 1=1", 'US;DROP' ];
		foreach ( $malformed as $value ) {
			$_SERVER['HTTP_CF_IPCOUNTRY'] = $value;
			$this->assertSame( '', $cf->get_country(), "Malformed value '$value' should be rejected" );
		}
	}

	public function test_get_subdivision_returns_empty_when_country_empty(): void {
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';
		$cf                             = new Cloudflare_Headers();
		$this->assertSame( '', $cf->get_subdivision( '' ) );
	}

	public function test_get_subdivision_returns_empty_when_region_header_missing(): void {
		$cf = new Cloudflare_Headers();
		$this->assertSame( '', $cf->get_subdivision( 'US' ) );
	}

	public function test_get_subdivision_returns_country_colon_region_format(): void {
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';
		$cf                             = new Cloudflare_Headers();
		$this->assertSame( 'US:KS', $cf->get_subdivision( 'US' ) );
	}

	public function test_get_subdivision_normalizes_lowercase_region(): void {
		$_SERVER['HTTP_CF_REGION_CODE'] = 'ks';
		$cf                             = new Cloudflare_Headers();
		$this->assertSame( 'US:KS', $cf->get_subdivision( 'US' ) );
	}

	public function test_get_subdivision_rejects_malformed_region(): void {
		$cf = new Cloudflare_Headers();

		$malformed = [ 'KANSAS', "KS'", 'KS;DROP', '!', '1234' ];
		foreach ( $malformed as $value ) {
			$_SERVER['HTTP_CF_REGION_CODE'] = $value;
			$this->assertSame( '', $cf->get_subdivision( 'US' ), "Malformed region '$value' should be rejected" );
		}
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Unit/Cloudflare_Headers_Test.php
```

Expected: FAIL with `Class "The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Cloudflare_Headers" not found`.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Cloudflare_Headers_Test.php
git commit -m "test: add Cloudflare_Headers unit tests (failing)"
```

### Task 9: Implement `Cloudflare_Headers`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/includes/class-cloudflare-headers.php`

- [ ] **Step 1: Write the class**

```php
<?php
/**
 * Cloudflare Headers Reader
 *
 * Reads and validates Cloudflare geo-location headers from $_SERVER.
 *
 * @package Aucteeno_Nexus_Geo_Tagging
 * @since 0.1.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging;

/**
 * Reads and validates Cloudflare geo headers.
 */
final class Cloudflare_Headers {

	/**
	 * Get the visitor's Cloudflare-detected country as an ISO 3166-1 alpha-2 code.
	 *
	 * @return string Two-letter uppercase country code, or empty string if unavailable/invalid.
	 */
	public function get_country(): string {
		$raw = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$value = strtoupper( trim( wp_unslash( $raw ) ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $value ) ) {
			return '';
		}
		// Reject Cloudflare's "unknown" sentinels.
		if ( in_array( $value, array( 'XX', 'T1' ), true ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Get the visitor's Cloudflare-detected subdivision as "COUNTRY:REGION".
	 *
	 * @param string $country Two-letter country code (must be non-empty).
	 * @return string "US:KS"-style subdivision, or empty string if unavailable/invalid.
	 */
	public function get_subdivision( string $country ): string {
		if ( '' === $country ) {
			return '';
		}
		$raw = $_SERVER['HTTP_CF_REGION_CODE'] ?? '';
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$value = strtoupper( trim( wp_unslash( $raw ) ) );
		if ( ! preg_match( '/^[A-Z0-9]{1,3}$/', $value ) ) {
			return '';
		}
		return $country . ':' . $value;
	}
}
```

- [ ] **Step 2: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/Cloudflare_Headers_Test.php
```

Expected: 10 tests, all passing.

- [ ] **Step 3: Run PHPCS on the new file**

```bash
./vendor/bin/phpcs includes/class-cloudflare-headers.php
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add includes/class-cloudflare-headers.php
git commit -m "feat: implement Cloudflare_Headers reader"
```

---

## Chunk 3: `Bot_Detector` class (TDD)

**Repo:** `wp-content/plugins/aucteeno-nexus-geo-tagging/` (master)

### Task 10: Write failing tests for `Bot_Detector`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/tests/Unit/Bot_Detector_Test.php`

- [ ] **Step 1: Write the test file**

```php
<?php
/**
 * Tests for Bot_Detector.
 *
 * @package The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Bot_Detector;

final class Bot_Detector_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_unslash' )->returnArg();
		unset( $_SERVER['HTTP_USER_AGENT'] );
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_empty_user_agent_parameter_is_bot(): void {
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( '' ) );
	}

	public function test_missing_user_agent_server_var_is_bot(): void {
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot() );
	}

	public function test_googlebot_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_bingbot_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_facebook_previewer_is_bot(): void {
		$ua       = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_generic_crawler_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; SomeNewCrawler/1.0)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_generic_spider_is_bot(): void {
		$ua       = 'Mozilla/5.0 (compatible; SomeSpider/1.0)';
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( $ua ) );
	}

	public function test_case_insensitive_matching(): void {
		$detector = new Bot_Detector();
		$this->assertTrue( $detector->is_bot( 'GOOGLEBOT/1.0' ) );
		$this->assertTrue( $detector->is_bot( 'GoogleBot/1.0' ) );
		$this->assertTrue( $detector->is_bot( 'googlebot/1.0' ) );
	}

	public function test_desktop_chrome_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_desktop_firefox_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:132.0) Gecko/20100101 Firefox/132.0';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_desktop_safari_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_mobile_safari_is_not_bot(): void {
		$ua       = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
		$detector = new Bot_Detector();
		$this->assertFalse( $detector->is_bot( $ua ) );
	}

	public function test_user_agent_parameter_takes_precedence_over_server_var(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'googlebot/1.0';
		$detector                   = new Bot_Detector();
		$this->assertFalse(
			$detector->is_bot( 'Mozilla/5.0 Chrome/130' ),
			'Explicit param should win over $_SERVER'
		);
	}

	public function test_falls_back_to_server_var_when_parameter_null(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
		$detector                   = new Bot_Detector();
		$this->assertTrue( $detector->is_bot() );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Unit/Bot_Detector_Test.php
```

Expected: FAIL with `Class "The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Bot_Detector" not found`.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Bot_Detector_Test.php
git commit -m "test: add Bot_Detector unit tests (failing)"
```

### Task 11: Implement `Bot_Detector`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/includes/class-bot-detector.php`

- [ ] **Step 1: Write the class**

```php
<?php
/**
 * Bot Detector
 *
 * User-Agent heuristic for detecting crawlers, previewers, and headless browsers.
 *
 * @package Aucteeno_Nexus_Geo_Tagging
 * @since 0.1.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging;

/**
 * Detects bot traffic based on a curated list of User-Agent substrings.
 */
final class Bot_Detector {

	/**
	 * Bot UA substrings. Case-insensitive match.
	 *
	 * Order matters only for micro-performance — most common patterns first.
	 *
	 * @var string[]
	 */
	private const BOT_PATTERNS = array(
		'bot',
		'crawler',
		'spider',
		'slurp',
		'googlebot',
		'bingbot',
		'yandexbot',
		'duckduckbot',
		'baiduspider',
		'facebookexternalhit',
		'twitterbot',
		'linkedinbot',
		'pinterestbot',
		'whatsapp',
		'telegrambot',
		'discordbot',
		'slackbot',
		'applebot',
		'petalbot',
		'semrushbot',
		'ahrefsbot',
		'headlesschrome',
		'phantomjs',
		'puppeteer',
		'playwright',
	);

	/**
	 * Determine whether the given User-Agent (or the one in $_SERVER) is a bot.
	 *
	 * @param string|null $user_agent Optional explicit UA. If null, falls back to $_SERVER['HTTP_USER_AGENT'].
	 *                                Missing/empty UA is treated as bot (safer default for curl, scripts).
	 * @return bool True if the UA matches a known bot pattern or is empty.
	 */
	public function is_bot( ?string $user_agent = null ): bool {
		if ( null === $user_agent ) {
			$raw        = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$user_agent = is_string( $raw ) ? wp_unslash( $raw ) : '';
		}
		if ( '' === $user_agent ) {
			return true; // No UA → treat as bot.
		}
		$ua_lower = strtolower( $user_agent );
		foreach ( self::BOT_PATTERNS as $pattern ) {
			if ( false !== strpos( $ua_lower, $pattern ) ) {
				return true;
			}
		}
		return false;
	}
}
```

- [ ] **Step 2: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/Bot_Detector_Test.php
```

Expected: 14 tests, all passing.

- [ ] **Step 3: Run PHPCS**

```bash
./vendor/bin/phpcs includes/class-bot-detector.php
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add includes/class-bot-detector.php
git commit -m "feat: implement Bot_Detector"
```

---

## Chunk 4: `Geo_Tagging` class — filter wiring (TDD)

**Repo:** `wp-content/plugins/aucteeno-nexus-geo-tagging/` (master)

### Task 12: Write failing tests for `Geo_Tagging::filter_location()`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/tests/Unit/Geo_Tagging_Test.php`

- [ ] **Step 1: Write the test file**

```php
<?php
/**
 * Tests for Geo_Tagging::filter_location().
 *
 * Uses real Cloudflare_Headers and Bot_Detector instances driven by $_SERVER,
 * since both classes are pure and trivially controllable via the superglobal.
 *
 * @package The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Bot_Detector;
use The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Cloudflare_Headers;
use The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Geo_Tagging;

final class Geo_Tagging_Test extends TestCase {

	private Geo_Tagging $subject;

	/**
	 * Minimal stub for WP_Block — the filter signature requires a WP_Block instance
	 * but filter_location() does not read from it.
	 */
	private object $block_stub;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_unslash' )->returnArg();

		unset(
			$_SERVER['HTTP_CF_IPCOUNTRY'],
			$_SERVER['HTTP_CF_REGION_CODE'],
			$_SERVER['HTTP_USER_AGENT']
		);

		$this->subject    = new Geo_Tagging( new Cloudflare_Headers(), new Bot_Detector() );
		$this->block_stub = new \stdClass();
	}

	protected function tearDown(): void {
		unset(
			$_SERVER['HTTP_CF_IPCOUNTRY'],
			$_SERVER['HTTP_CF_REGION_CODE'],
			$_SERVER['HTTP_USER_AGENT']
		);
		Monkey\tearDown();
		parent::tearDown();
	}

	private function human_ua(): void {
		$_SERVER['HTTP_USER_AGENT'] =
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
	}

	private function bot_ua(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
	}

	public function test_feature_disabled_returns_input_unchanged(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( '', '' ),
			array( 'geoTaggingEnabled' => false ),
			$this->block_stub
		);

		$this->assertSame( array( '', '' ), $result );
	}

	public function test_bot_without_opt_in_returns_input_unchanged(): void {
		$this->bot_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( 'CA', '' ),
			array(
				'geoTaggingEnabled'     => true,
				'geoTaggingAffectsBots' => false,
			),
			$this->block_stub
		);

		$this->assertSame( array( 'CA', '' ), $result );
	}

	public function test_bot_with_opt_in_applies_cf(): void {
		$this->bot_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY']   = 'US';
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';

		$result = $this->subject->filter_location(
			array( '', '' ),
			array(
				'geoTaggingEnabled'     => true,
				'geoTaggingAffectsBots' => true,
			),
			$this->block_stub
		);

		$this->assertSame( array( 'US', 'US:KS' ), $result );
	}

	public function test_human_enabled_cf_wins_over_input(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY']   = 'US';
		$_SERVER['HTTP_CF_REGION_CODE'] = 'KS';

		$result = $this->subject->filter_location(
			array( 'CA', 'CA:ON' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'US', 'US:KS' ), $result );
	}

	public function test_human_enabled_cf_country_empty_returns_input(): void {
		$this->human_ua();
		// No HTTP_CF_IPCOUNTRY set.

		$result = $this->subject->filter_location(
			array( 'CA', 'CA:ON' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_cf_country_present_subdivision_empty_returns_country_only(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
		// No region code header.

		$result = $this->subject->filter_location(
			array( '', '' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'US', '' ), $result );
	}

	public function test_cf_sentinel_country_returns_input(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';

		$result = $this->subject->filter_location(
			array( 'CA', 'CA:ON' ),
			array( 'geoTaggingEnabled' => true ),
			$this->block_stub
		);

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_affects_bots_missing_attribute_treats_as_false(): void {
		$this->bot_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( 'CA', '' ),
			array( 'geoTaggingEnabled' => true ), // no geoTaggingAffectsBots key.
			$this->block_stub
		);

		$this->assertSame( array( 'CA', '' ), $result );
	}

	public function test_enabled_attribute_missing_treats_as_false(): void {
		$this->human_ua();
		$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';

		$result = $this->subject->filter_location(
			array( 'CA', '' ),
			array(), // no geoTaggingEnabled key.
			$this->block_stub
		);

		$this->assertSame( array( 'CA', '' ), $result );
	}

	public function test_init_registers_filter_and_action(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'aucteeno_query_loop_location', array( $this->subject, 'filter_location' ), 10, 3 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'enqueue_block_editor_assets', array( $this->subject, 'enqueue_editor_assets' ) );

		$this->subject->init();
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Unit/Geo_Tagging_Test.php
```

Expected: FAIL with `Class "The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging\Geo_Tagging" not found`.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Geo_Tagging_Test.php
git commit -m "test: add Geo_Tagging unit tests (failing)"
```

### Task 13: Implement `Geo_Tagging` class

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/includes/class-geo-tagging.php`

- [ ] **Step 1: Write the class**

```php
<?php
/**
 * Geo-Tagging filter wiring.
 *
 * Composes Cloudflare_Headers and Bot_Detector to produce a filter callback
 * for aucteeno_query_loop_location. Owns the block-editor asset enqueue hook.
 *
 * @package Aucteeno_Nexus_Geo_Tagging
 * @since 0.1.0
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging;

/**
 * Wires the aucteeno_query_loop_location filter and the editor JS enqueue.
 */
final class Geo_Tagging {

	/**
	 * Cloudflare header reader.
	 *
	 * @var Cloudflare_Headers
	 */
	private Cloudflare_Headers $cf;

	/**
	 * Bot detector.
	 *
	 * @var Bot_Detector
	 */
	private Bot_Detector $bot_detector;

	/**
	 * Constructor.
	 *
	 * @param Cloudflare_Headers $cf           Cloudflare header reader.
	 * @param Bot_Detector       $bot_detector Bot detector.
	 */
	public function __construct( Cloudflare_Headers $cf, Bot_Detector $bot_detector ) {
		$this->cf           = $cf;
		$this->bot_detector = $bot_detector;
	}

	/**
	 * Register the filter callback and asset enqueue action.
	 */
	public function init(): void {
		add_filter(
			'aucteeno_query_loop_location',
			array( $this, 'filter_location' ),
			10,
			3
		);
		add_action(
			'enqueue_block_editor_assets',
			array( $this, 'enqueue_editor_assets' )
		);
	}

	/**
	 * Filter callback for aucteeno_query_loop_location.
	 *
	 * Note: $block is typed as mixed (not \WP_Block) deliberately. In production
	 * the base aucteeno plugin always passes a \WP_Block instance, but typing it
	 * here would force every unit test to construct a real WP_Block, which pulls
	 * in WordPress core. Since this method never reads from $block, mixed is
	 * strictly better — it lets tests pass \stdClass() as a stand-in.
	 *
	 * @param array $location   Two-element indexed array [ string $country, string $subdivision ].
	 * @param array $attributes Block attributes.
	 * @param mixed $block      The block instance (WP_Block in production). Unused.
	 * @return array Same shape as $location.
	 */
	public function filter_location( array $location, array $attributes, $block ): array {
		unset( $block ); // Signature only.

		// 1. Feature must be explicitly enabled on this block.
		if ( empty( $attributes['geoTaggingEnabled'] ) ) {
			return $location;
		}

		// 2. Bot gate: if visitor is a bot and bots are not opted in, skip.
		$affects_bots = ! empty( $attributes['geoTaggingAffectsBots'] );
		if ( ! $affects_bots && $this->bot_detector->is_bot() ) {
			return $location;
		}

		// 3. Read CF headers. Empty country → feature silently no-ops.
		$country = $this->cf->get_country();
		if ( '' === $country ) {
			return $location;
		}
		$subdivision = $this->cf->get_subdivision( $country );

		// 4. CF wins (§10, decision 1). Return the new values; base re-sanitizes.
		return array( $country, $subdivision );
	}

	/**
	 * Enqueue the block editor inspector script.
	 *
	 * Loads the built JS from dist/ with auto-resolved dependencies from the
	 * @wordpress/scripts build pipeline. Silently no-ops if the build artifact
	 * is missing (e.g., plugin installed without running `npm run build`).
	 */
	public function enqueue_editor_assets(): void {
		$asset_path = AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_DIR . 'dist/query-loop-inspector.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;
		wp_enqueue_script(
			'aucteeno-nexus-geo-tagging-inspector',
			AUCTEENO_NEXUS_GEO_TAGGING_PLUGIN_URL . 'dist/query-loop-inspector.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? AUCTEENO_NEXUS_GEO_TAGGING_VERSION,
			true
		);

		wp_set_script_translations(
			'aucteeno-nexus-geo-tagging-inspector',
			'aucteeno-nexus-geo-tagging'
		);
	}
}
```

- [ ] **Step 2: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/Geo_Tagging_Test.php
```

Expected: 10 tests, all passing.

- [ ] **Step 3: Run the full test suite**

```bash
./vendor/bin/phpunit
```

Expected: ~34 tests across all three files, all passing.

- [ ] **Step 4: Run PHPCS on includes/**

```bash
./vendor/bin/phpcs includes/
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add includes/class-geo-tagging.php
git commit -m "feat: implement Geo_Tagging filter and enqueue wiring"
```

---

## Chunk 5: Editor JS (block attributes + Inspector panel)

**Repo:** `wp-content/plugins/aucteeno-nexus-geo-tagging/` (master)

### Task 14: Create `package.json`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/package.json`

- [ ] **Step 1: Write the file**

```json
{
    "name": "aucteeno-nexus-geo-tagging",
    "version": "0.1.0",
    "description": "Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.",
    "private": true,
    "license": "GPL-2.0-or-later",
    "scripts": {
        "build": "wp-scripts build assets/js/query-loop-inspector.js --output-path=dist",
        "start": "wp-scripts start assets/js/query-loop-inspector.js --output-path=dist",
        "lint:js": "wp-scripts lint-js assets/js",
        "format": "wp-scripts format assets/js",
        "plugin-zip": "wp-scripts plugin-zip"
    },
    "devDependencies": {
        "@wordpress/scripts": "^30.0.0"
    },
    "files": [
        "aucteeno-nexus-geo-tagging.php",
        "includes/",
        "dist/",
        "readme.txt",
        "README.md"
    ]
}
```

- [ ] **Step 2: Install npm dependencies**

```bash
npm install
```

Expected: successful install, creates `node_modules/` and `package-lock.json`.

- [ ] **Step 3: Commit `package.json` and `package-lock.json`**

```bash
git add package.json package-lock.json
git commit -m "chore: add package.json with @wordpress/scripts"
```

### Task 15: Write the editor JS

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/assets/js/query-loop-inspector.js`

- [ ] **Step 1: Write the file**

```js
/**
 * Aucteeno Nexus Geo-Tagging — Query Loop Inspector.
 *
 * Adds two block attributes (geoTaggingEnabled, geoTaggingAffectsBots) to
 * the aucteeno/query-loop block at runtime via blocks.registerBlockType, and
 * injects a matching Inspector panel via editor.BlockEdit HOC filter.
 */

import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const BLOCK_NAME = 'aucteeno/query-loop';
const FILTER_NAMESPACE = 'aucteeno-nexus-geo-tagging';

/**
 * Register the two new block attributes on aucteeno/query-loop.
 */
function addGeoTaggingAttributes( settings, name ) {
	if ( name !== BLOCK_NAME ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			geoTaggingEnabled: {
				type: 'boolean',
				default: false,
			},
			geoTaggingAffectsBots: {
				type: 'boolean',
				default: false,
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	`${ FILTER_NAMESPACE }/add-attributes`,
	addGeoTaggingAttributes
);

/**
 * Inject the Inspector panel via BlockEdit HOC.
 */
const withGeoTaggingInspector = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== BLOCK_NAME ) {
			return createElement( BlockEdit, props );
		}

		const { attributes, setAttributes } = props;
		const { geoTaggingEnabled, geoTaggingAffectsBots } = attributes;

		return createElement(
			Fragment,
			null,
			createElement( BlockEdit, props ),
			createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{
						title: __( 'Geo-Tagging', 'aucteeno-nexus-geo-tagging' ),
						initialOpen: false,
					},
					createElement( ToggleControl, {
						label: __( 'Enable geo-tagging', 'aucteeno-nexus-geo-tagging' ),
						help: __(
							"When enabled, the visitor's Cloudflare-detected country and region override any manually set location filters. The editor preview reflects your own location.",
							'aucteeno-nexus-geo-tagging'
						),
						checked: !! geoTaggingEnabled,
						onChange: ( value ) =>
							setAttributes( { geoTaggingEnabled: value } ),
					} ),
					geoTaggingEnabled &&
						createElement( ToggleControl, {
							label: __(
								'Also apply to bots',
								'aucteeno-nexus-geo-tagging'
							),
							help: __(
								'By default, detected bots (search crawlers, social previewers) bypass geo-tagging so they see the default content. Enable this to apply geo-tagging to bots too.',
								'aucteeno-nexus-geo-tagging'
							),
							checked: !! geoTaggingAffectsBots,
							onChange: ( value ) =>
								setAttributes( { geoTaggingAffectsBots: value } ),
						} )
				)
			)
		);
	};
}, 'withGeoTaggingInspector' );

addFilter(
	'editor.BlockEdit',
	`${ FILTER_NAMESPACE }/with-inspector`,
	withGeoTaggingInspector
);
```

**Why `createElement` instead of JSX:** `@wordpress/scripts` supports JSX via Babel, but plain `createElement` avoids the JSX dependency and produces a smaller, more predictable build. This file has exactly one top-level export from React, and using `createElement` makes the dependency graph trivial.

- [ ] **Step 2: Build the script**

```bash
npm run build
```

Expected: `dist/query-loop-inspector.js` and `dist/query-loop-inspector.asset.php` are created.

- [ ] **Step 3: Verify the asset file looks correct**

```bash
cat dist/query-loop-inspector.asset.php
```

Expected: a file returning an array with `dependencies` listing WordPress packages (`wp-hooks`, `wp-block-editor`, `wp-components`, `wp-compose`, `wp-element`, `wp-i18n`) and a `version` hash.

- [ ] **Step 4: Run JS linter**

```bash
npm run lint:js
```

Expected: no errors.

- [ ] **Step 5: Commit the source (dist/ is gitignored)**

```bash
git add assets/js/query-loop-inspector.js
git commit -m "feat: add query-loop inspector editor script"
```

---

## Chunk 6: Dockerfile, Makefile, documentation

**Repo:** `wp-content/plugins/aucteeno-nexus-geo-tagging/` (master)

### Task 16: Create `Dockerfile`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/Dockerfile`

- [ ] **Step 1: Write the file**

```dockerfile
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install zip mbstring xml \
    && rm -rf /var/lib/apt/lists/*

# Install Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js 24.
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
```

- [ ] **Step 2: Commit**

```bash
git add Dockerfile
git commit -m "chore: add Dockerfile for reproducible builds"
```

### Task 17: Create `Makefile`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/Makefile`

- [ ] **Step 1: Write the file**

```makefile
IMAGE_NAME := aucteeno-nexus-geo-tagging-build
DOCKER_RUN := docker run --rm -v $(PWD):/app -w /app $(IMAGE_NAME)

.PHONY: docker-build install install-dev build lint format test all clean release

docker-build:
	docker build -t $(IMAGE_NAME) .

install: docker-build
	$(DOCKER_RUN) composer install --no-dev --prefer-dist --no-progress

install-dev: docker-build
	$(DOCKER_RUN) composer install --prefer-dist --no-progress
	$(DOCKER_RUN) npm ci

build: docker-build
	$(DOCKER_RUN) npm run build

lint: docker-build
	$(DOCKER_RUN) composer phpcs

format: docker-build
	$(DOCKER_RUN) composer phpcbf

test: docker-build
	$(DOCKER_RUN) composer test

all: install-dev build lint test

clean:
	rm -rf vendor node_modules dist build .phpunit.cache

release: install build
	mkdir -p build
	rm -rf build/aucteeno-nexus-geo-tagging
	mkdir -p build/aucteeno-nexus-geo-tagging
	cp -R aucteeno-nexus-geo-tagging.php includes dist README.md readme.txt \
		build/aucteeno-nexus-geo-tagging/
	cd build && zip -r aucteeno-nexus-geo-tagging.zip aucteeno-nexus-geo-tagging
```

- [ ] **Step 2: Commit**

```bash
git add Makefile
git commit -m "chore: add Makefile with docker-wrapped targets"
```

### Task 18: Create `README.md`, `readme.txt`, `CLAUDE.md`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/README.md`
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/readme.txt`
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/CLAUDE.md`

- [ ] **Step 1: Write `README.md`**

```markdown
# Aucteeno Nexus Geo-Tagging

Cloudflare geo-header based filtering for the Aucteeno Query Loop block.

When enabled per block, the plugin reads `CF-IPCountry` and `Cf-Region-Code` from
Cloudflare and overrides the block's location filter to show only auctions or
items matching the visitor's detected country and subdivision.

## Requirements

- WordPress 6.9+
- PHP 8.3+
- Aucteeno plugin (required, declared via `Requires Plugins` header)
- Site served through Cloudflare for the feature to have any effect
- Cloudflare managed transform "Add visitor location headers" enabled for
  subdivision filtering (country works on all paid plans by default)

## Development

All commands run inside Docker for reproducibility:

```bash
make install-dev   # install composer + npm deps
make build         # build JS assets
make test          # run PHPUnit
make lint          # run PHPCS
make format        # auto-fix PHPCS issues
make all           # install-dev + build + lint + test
make release       # produce build/aucteeno-nexus-geo-tagging.zip
```

## Architecture

See `docs/superpowers/specs/2026-04-11-aucteeno-nexus-geo-tagging-design.md` for
the full design rationale. In short:

- Three PHP classes (`Cloudflare_Headers`, `Bot_Detector`, `Geo_Tagging`), no DI
  container, autoloaded via Composer classmap.
- One filter hook on `aucteeno_query_loop_location` (added to the base `aucteeno`
  plugin on its own feature branch).
- Editor UI added at runtime via `blocks.registerBlockType` and
  `editor.BlockEdit` JS filters — no changes to the base block.json.
- Bot detection via curated User-Agent substring list.
- Page caching is explicitly out of scope. Site operators are responsible for
  cache configuration on pages containing geo-tagged Query Loops.
```

- [ ] **Step 2: Write `readme.txt`**

```
=== Aucteeno Nexus Geo-Tagging ===
Contributors: theanother
Tags: aucteeno, auction, cloudflare, geo, localization
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.

== Description ==

An opt-in extension for the Aucteeno plugin. When enabled per-block, the Query
Loop block filters auctions and items to match the visitor's Cloudflare-detected
country and subdivision. Bot traffic (search engines, social previewers) bypasses
the filter by default, with an optional toggle to include them.

Requires the site to be served through Cloudflare. See the plugin README for
full architecture notes and operator caveats, including the page-caching
limitation.

== Installation ==

1. Install and activate the Aucteeno plugin.
2. Install and activate Aucteeno Nexus Geo-Tagging.
3. Edit a post or page containing an Aucteeno Query Loop block.
4. Select the block and open the "Geo-Tagging" panel in the Inspector sidebar.
5. Toggle "Enable geo-tagging" on.
6. Optionally toggle "Also apply to bots" if you want crawlers to see filtered results.

== Changelog ==

= 0.1.0 =
* Initial release.
```

- [ ] **Step 3: Write `CLAUDE.md`**

```markdown
# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with this repository.

## Project Overview

`aucteeno-nexus-geo-tagging` is a lightweight WordPress extension plugin that adds
opt-in Cloudflare-header-based country/subdivision filtering to the Aucteeno Query
Loop block.

## Design & Plan

Before making changes, read:

- **Design spec:** `docs/superpowers/specs/2026-04-11-aucteeno-nexus-geo-tagging-design.md`
- **Implementation plan:** `docs/superpowers/plans/2026-04-11-aucteeno-nexus-geo-tagging-implementation.md`

These documents are the source of truth for the plugin's architecture.

## Development Commands

```bash
make install-dev   # install composer + npm deps
make build         # build JS assets
make test          # run PHPUnit
make lint          # run PHPCS
make format        # auto-fix PHPCS issues
make all           # install-dev + build + lint + test
```

## Architecture in one paragraph

Three PHP classes in `includes/` (`Cloudflare_Headers`, `Bot_Detector`, `Geo_Tagging`),
no DI container, autoloaded via Composer classmap. The plugin registers one filter
callback on `aucteeno_query_loop_location` (added by a companion feature-branch PR
in the base `aucteeno` plugin) and one action callback on `enqueue_block_editor_assets`.
The editor UI (two ToggleControls in a PanelBody) is injected into the existing
`aucteeno/query-loop` block at runtime via `blocks.registerBlockType` and
`editor.BlockEdit` JS filters — no changes to the base plugin's block.json.

## Key conventions

- PHP 8.3+, WordPress 6.9+.
- WordPress Coding Standards + VIPCS ruleset.
- `class-{kebab-name}.php` file naming.
- Namespace: `The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging`.
- PHPUnit 11 + Brain Monkey for tests, no WordPress loaded.

## Don't

- Don't add a DI container, Hook_Manager, or Mozart — this plugin is deliberately flat.
- Don't emit `Vary` headers or call `DONOTCACHEPAGE` — page caching is out of scope.
- Don't modify the base `aucteeno` plugin's block.json — the ownership boundary is enforced by injecting attributes at runtime.
```

- [ ] **Step 4: Commit**

```bash
git add README.md readme.txt CLAUDE.md
git commit -m "docs: add README, readme.txt, and CLAUDE.md"
```

---

## Chunk 7: CI workflows

**Repo:** `wp-content/plugins/aucteeno-nexus-geo-tagging/` (master)

### Task 19: Create `.github/workflows/ci.yml`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/.github/workflows/ci.yml`

- [ ] **Step 1: Write the file**

```yaml
name: CI

on:
  pull_request:
    branches: [master]
  workflow_dispatch:

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

permissions:
  contents: read

jobs:
  build:
    name: Build
    runs-on: depot-ubuntu-24.04

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, zip, curl, dom, tokenizer, intl, iconv
          tools: composer:v2

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress

      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: 24
          cache: npm

      - name: Install npm dependencies
        run: npm ci

      - name: Build frontend assets
        run: npm run build

      - name: Upload artifacts
        uses: actions/upload-artifact@v4
        with:
          name: artifacts
          path: |
            dist/
            vendor/
          retention-days: 1

  phpunit:
    name: PHPUnit Tests
    runs-on: depot-ubuntu-24.04
    needs: build

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, zip, curl, dom, tokenizer, intl, iconv

      - name: Download artifacts
        uses: actions/download-artifact@v4
        with:
          name: artifacts

      - name: Run PHPUnit
        run: php vendor/bin/phpunit

  phpcs:
    name: PHP Code Standards
    runs-on: depot-ubuntu-24.04
    needs: build

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, zip, curl, dom, tokenizer, intl, iconv

      - name: Download artifacts
        uses: actions/download-artifact@v4
        with:
          name: artifacts

      - name: Run PHPCS
        run: php vendor/bin/phpcs

  lint-js:
    name: JS Lint
    runs-on: depot-ubuntu-24.04
    needs: build

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: 24
          cache: npm

      - name: Install npm dependencies
        run: npm ci

      - name: Lint JavaScript
        run: npm run lint:js
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add CI workflow on depot runners"
```

### Task 20: Create `.github/workflows/package.yml`

**Files:**
- Create: `wp-content/plugins/aucteeno-nexus-geo-tagging/.github/workflows/package.yml`

- [ ] **Step 1: Write the file**

```yaml
name: Release Plugin

on:
  push:
    branches: [master]
  workflow_dispatch:

permissions:
  contents: write

jobs:
  test:
    name: Test Gate
    runs-on: depot-ubuntu-24.04

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, zip, curl, dom, tokenizer, intl, iconv
          tools: composer:v2

      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: 24
          cache: npm

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress

      - name: Install npm dependencies
        run: npm ci

      - name: Build frontend assets
        run: npm run build

      - name: Run PHPUnit
        run: php vendor/bin/phpunit

      - name: Run PHPCS
        run: php vendor/bin/phpcs

      - name: Lint JavaScript
        run: npm run lint:js

  release:
    name: Build & Release
    runs-on: depot-ubuntu-24.04
    needs: [test]

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Read version from package.json
        id: version
        run: |
          VERSION=$(node -p "require('./package.json').version")
          echo "version=$VERSION" >> $GITHUB_OUTPUT
          echo "tag=v$VERSION" >> $GITHUB_OUTPUT

      - name: Check if tag already exists
        id: tag_check
        run: |
          TAG="v${{ steps.version.outputs.version }}"
          if git ls-remote --tags origin "$TAG" | grep -q "$TAG"; then
            echo "exists=true" >> $GITHUB_OUTPUT
            echo "::warning::Tag $TAG already exists — skipping release."
          else
            echo "exists=false" >> $GITHUB_OUTPUT
          fi

      - name: Set up PHP
        if: steps.tag_check.outputs.exists == 'false'
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, zip, curl, dom, tokenizer, intl, iconv
          tools: composer:v2

      - name: Set up Node.js
        if: steps.tag_check.outputs.exists == 'false'
        uses: actions/setup-node@v4
        with:
          node-version: 24
          cache: npm

      - name: Install dependencies and build
        if: steps.tag_check.outputs.exists == 'false'
        run: |
          composer install --no-dev --prefer-dist --no-progress
          npm ci
          npm run build
          npm run plugin-zip

      - name: Create and push tag
        if: steps.tag_check.outputs.exists == 'false'
        run: |
          TAG="${{ steps.version.outputs.tag }}"
          git tag "$TAG"
          git push origin "$TAG"

      - name: Create GitHub Release
        if: steps.tag_check.outputs.exists == 'false'
        uses: softprops/action-gh-release@v2
        with:
          tag_name: ${{ steps.version.outputs.tag }}
          generate_release_notes: true
          files: aucteeno-nexus-geo-tagging.zip
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/package.yml
git commit -m "ci: add release workflow on depot runners"
```

### Task 21: Final validation for the extension plugin

**Files:** none new — validation only.

- [ ] **Step 1: Run the full test suite**

```bash
cd wp-content/plugins/aucteeno-nexus-geo-tagging
./vendor/bin/phpunit
```

Expected: ~34 tests, all passing.

- [ ] **Step 2: Run PHPCS on everything**

```bash
./vendor/bin/phpcs
```

Expected: no errors. If anything fails, fix it before proceeding.

- [ ] **Step 3: Rebuild JS and run the linter**

```bash
npm run build && npm run lint:js
```

Expected: clean build, no lint errors.

- [ ] **Step 4: Verify git state is clean**

```bash
git status
```

Expected: `nothing to commit, working tree clean`. The build artifacts in `dist/` should be git-ignored.

---

## Chunk 8: Base `aucteeno` plugin — feature branch

**Repo:** `wp-content/plugins/aucteeno/` (new branch `feat/query-loop-geo-tagging-hooks`)

### Task 22: Create the feature branch

**Files:** none — git only.

- [ ] **Step 1: Check current branch is clean**

```bash
cd wp-content/plugins/aucteeno
git status
```

Expected: working tree clean.

- [ ] **Step 2: Create and checkout the feature branch**

```bash
git checkout master
git pull --ff-only origin master
git checkout -b feat/query-loop-geo-tagging-hooks
```

Expected: switched to new branch `feat/query-loop-geo-tagging-hooks`.

### Task 23: Create the `Query_Loop_Location_Filter` helper class (TDD)

**Strategy rationale:** The base plugin's `render.php` is a procedural file, not a class — including it from a unit test requires ~25 Brain Monkey function stubs and still leaves the test fragile. Instead, extract the filter-call-and-sanitize logic into a small static helper class. The helper is trivially testable in isolation, and `render.php`'s only change becomes a single method call wrapped in the `! $has_product_ids` guard. This keeps the spec's architectural intent (filter hook inside `! $has_product_ids` path) and is cleaner than the full-file include approach. Spec §15 item 1 is satisfied — the filter hook still lives in the `aucteeno` plugin; the helper is just an implementation detail of how `render.php` calls `apply_filters()`.

**Files:**
- Create: `wp-content/plugins/aucteeno/includes/blocks/class-query-loop-location-filter.php`
- Create: `wp-content/plugins/aucteeno/tests/Query_Loop_Location_Filter_Test.php`

- [ ] **Step 1: Check existing tests/ directory conventions**

```bash
cd wp-content/plugins/aucteeno
ls tests/
head -40 tests/bootstrap.php
```

Expected: confirm the test suite uses Brain Monkey and PHPUnit 11, and observe the PSR-4 test namespace.

- [ ] **Step 2: Verify autoloader covers `includes/blocks/`**

```bash
grep -A2 '"classmap"' composer.json || grep -A2 '"psr-4"' composer.json
```

Expected: the autoload config includes either a `classmap` entry that covers `includes/` or a PSR-4 entry for the `The_Another\Plugin\Aucteeno\` namespace. If neither covers `includes/blocks/`, add the directory before creating the file.

- [ ] **Step 3: Write the failing test**

```php
<?php
/**
 * Tests for the Query_Loop_Location_Filter helper.
 *
 * @package The_Another\Plugin\Aucteeno\Tests
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use The_Another\Plugin\Aucteeno\Blocks\Query_Loop_Location_Filter;

final class Query_Loop_Location_Filter_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_returns_input_unchanged_when_no_filter_hooked(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => $value
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array( 'queryType' => 'auctions' ), new \stdClass() );

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_passes_country_subdivision_attributes_and_block_to_filter(): void {
		$captured = null;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value, ...$args ) use ( &$captured ) {
				if ( 'aucteeno_query_loop_location' === $tag ) {
					$captured = array( 'value' => $value, 'args' => $args );
				}
				return $value;
			}
		);

		$block = new \stdClass();
		Query_Loop_Location_Filter::apply( array( 'US', 'US:KS' ), array( 'queryType' => 'items' ), $block );

		$this->assertNotNull( $captured );
		$this->assertSame( array( 'US', 'US:KS' ), $captured['value'] );
		$this->assertSame( array( 'queryType' => 'items' ), $captured['args'][0] );
		$this->assertSame( $block, $captured['args'][1] );
	}

	public function test_accepts_valid_filter_return(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 'US', 'US:KS' )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'US', 'US:KS' ), $result );
	}

	public function test_ignores_non_array_filter_return(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => 'not an array'
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_ignores_wrong_length_filter_return(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 'US' )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'CA', 'CA:ON' ), $result );
	}

	public function test_accepts_partial_return_valid_country_null_subdivision(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 'US', null )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'US', 'CA:ON' ), $result, 'Valid country should apply, null subdivision should fall through' );
	}

	public function test_rejects_non_string_element_individually(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $tag, $value ) => array( 123, 'US:KS' )
		);

		$result = Query_Loop_Location_Filter::apply( array( 'CA', 'CA:ON' ), array(), new \stdClass() );

		$this->assertSame( array( 'CA', 'US:KS' ), $result, 'Non-string country rejected; valid subdivision applied' );
	}
}
```

- [ ] **Step 4: Run the test to verify it fails**

```bash
./vendor/bin/phpunit tests/Query_Loop_Location_Filter_Test.php
```

Expected: FAIL with `Class "The_Another\Plugin\Aucteeno\Blocks\Query_Loop_Location_Filter" not found`.

- [ ] **Step 5: Implement the helper class**

Create `wp-content/plugins/aucteeno/includes/blocks/class-query-loop-location-filter.php`:

```php
<?php
/**
 * Query Loop Location Filter helper.
 *
 * Wraps the apply_filters() call and return-value sanitization for the
 * aucteeno_query_loop_location filter. Extracted from render.php so the
 * logic can be unit-tested in isolation.
 *
 * @package Aucteeno
 * @since 1.2.3
 */

declare(strict_types=1);

namespace The_Another\Plugin\Aucteeno\Blocks;

/**
 * Static helper that emits the aucteeno_query_loop_location filter.
 */
final class Query_Loop_Location_Filter {

	/**
	 * Run the aucteeno_query_loop_location filter and return a sanitized pair.
	 *
	 * @param array $location   Two-element indexed array [ string $country, string $subdivision ].
	 *                          $country is a 2-letter ISO code or empty string.
	 *                          $subdivision is "COUNTRY:REGION" or empty string.
	 * @param array $attributes The block's resolved attributes array.
	 * @param mixed $block      The block instance (WP_Block in production).
	 * @return array Two-element indexed array in the same shape.
	 */
	public static function apply( array $location, array $attributes, $block ): array {
		/**
		 * Filters the resolved location for the Aucteeno Query Loop block before querying.
		 *
		 * Fires after the base precedence chain (attribute → context → taxonomy archive)
		 * resolves $location_country and $location_subdivision, and before they are
		 * written into $query_args. Does not fire when the block is in product-IDs mode —
		 * callers wrap this call in a ! $has_product_ids guard.
		 *
		 * @since 1.2.3
		 *
		 * @param array $location   Two-element indexed array [ string $country, string $subdivision ].
		 * @param array $attributes The block's resolved attributes array.
		 * @param mixed $block      The block instance.
		 */
		$filtered = apply_filters(
			'aucteeno_query_loop_location',
			$location,
			$attributes,
			$block
		);

		if ( ! is_array( $filtered ) || 2 !== count( $filtered ) ) {
			return $location;
		}

		$country     = $location[0];
		$subdivision = $location[1];

		if ( isset( $filtered[0] ) && is_string( $filtered[0] ) ) {
			$country = sanitize_text_field( $filtered[0] );
		}
		if ( isset( $filtered[1] ) && is_string( $filtered[1] ) ) {
			$subdivision = sanitize_text_field( $filtered[1] );
		}

		return array( $country, $subdivision );
	}
}
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
./vendor/bin/phpunit tests/Query_Loop_Location_Filter_Test.php
```

Expected: 7 tests, all passing.

- [ ] **Step 7: Run the full aucteeno test suite**

```bash
./vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 8: Run PHPCS on the new file**

```bash
./vendor/bin/phpcs includes/blocks/class-query-loop-location-filter.php
```

Expected: no errors.

- [ ] **Step 9: Commit**

```bash
git add includes/blocks/class-query-loop-location-filter.php tests/Query_Loop_Location_Filter_Test.php
git commit -m "feat(query-loop): add Query_Loop_Location_Filter helper with aucteeno_query_loop_location filter"
```

### Task 24: Wire the helper into `render.php`

**Files:**
- Modify: `wp-content/plugins/aucteeno/blocks/query-loop/render.php`

- [ ] **Step 1: Read the current state of the file around lines 190–215**

```bash
sed -n '190,220p' blocks/query-loop/render.php
```

Expected: shows the location-resolution block (lines 192–208) and the assignment block (lines 210–215). Confirm line numbers match before editing.

- [ ] **Step 2: Add the `use` statement near the top of the file**

Find the existing `use` block near line 19. Add:

```php
use The_Another\Plugin\Aucteeno\Blocks\Query_Loop_Location_Filter;
```

- [ ] **Step 3: Insert the helper call inside a `! $has_product_ids` guard**

Insert the following between line 208 (end of subdivision resolution) and line 210 (start of `$query_args` assignment):

```php

// Allow extensions to override the resolved location.
// Skipped when the block is in product-IDs mode — that branch (below) rebuilds
// $query_args from scratch and ignores location filters entirely.
if ( ! $has_product_ids ) {
	list( $location_country, $location_subdivision ) = Query_Loop_Location_Filter::apply(
		array( $location_country, $location_subdivision ),
		$attributes,
		$block
	);
}
```

- [ ] **Step 4: Run the full test suite to verify nothing regressed**

```bash
./vendor/bin/phpunit
```

Expected: all tests pass. If anything regressed, STOP and investigate.

- [ ] **Step 5: Run PHPCS on render.php**

```bash
./vendor/bin/phpcs blocks/query-loop/render.php
```

Expected: no new errors. Pre-existing errors (if any) are not in scope — leave them.

- [ ] **Step 6: Commit**

```bash
git add blocks/query-loop/render.php
git commit -m "feat(query-loop): wire aucteeno_query_loop_location filter in render.php

Call Query_Loop_Location_Filter::apply() from within the
! \$has_product_ids guard, after the existing precedence chain
resolves \$location_country and \$location_subdivision and before
they are written into \$query_args. Extension plugins can hook
the new aucteeno_query_loop_location filter to override both
values (e.g., aucteeno-nexus-geo-tagging, which applies
Cloudflare-derived country/subdivision)."
```

### Task 25: Bump version and update changelog

**Files:**
- Modify: `wp-content/plugins/aucteeno/aucteeno.php`
- Modify: `wp-content/plugins/aucteeno/CHANGELOG.md`
- Modify: `wp-content/plugins/aucteeno/package.json` (if version is also there)

- [ ] **Step 1: Read the actual current version dynamically**

```bash
CURRENT=$(awk '/^\s*\*\s*Version:/ {print $NF; exit}' aucteeno.php)
echo "Current version: $CURRENT"
```

Expected: prints a semver string, e.g., `1.2.2`. Compute the next patch version (e.g., `1.2.3`) and use it everywhere below. **Do not hard-code `1.2.3` if the base has drifted — compute it from `$CURRENT`.**

- [ ] **Step 2: Find every file that mentions the current version**

```bash
grep -rn "$CURRENT" aucteeno.php package.json composer.json 2>/dev/null
```

Expected: version appears in the plugin header `Version:` line, the `AUCTEENO_VERSION` constant, and likely in `package.json` and/or `composer.json`.

- [ ] **Step 3: Update `aucteeno.php` header and constant**

Change the plugin header `Version: <current>` to `Version: <next>` and `define( 'AUCTEENO_VERSION', '<current>' );` to `define( 'AUCTEENO_VERSION', '<next>' );`.

- [ ] **Step 4: Update `package.json` if it has a version field**

```bash
grep -n '"version"' package.json
```

If the current version appears, change it to the next patch.

- [ ] **Step 5: Check for `CHANGELOG.md`**

```bash
ls CHANGELOG.md 2>/dev/null
```

If it exists, read it to understand the format.

- [ ] **Step 6: Add changelog entry**

Prepend an entry for the new patch version. Substitute the next version you computed in Step 1:

```markdown
## <next>

### Added

- New `Query_Loop_Location_Filter` helper class exposing the `aucteeno_query_loop_location` filter. Extension plugins can hook the filter to override the resolved country and subdivision before they are used to query the HPS tables. The helper is called from `blocks/query-loop/render.php` inside a `! $has_product_ids` guard. Consumed by the `aucteeno-nexus-geo-tagging` extension.
```

If `CHANGELOG.md` does not exist, create it with the above entry plus a top-level `# Changelog` header.

- [ ] **Step 7: Run tests again to confirm nothing broke**

```bash
./vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
git add aucteeno.php package.json CHANGELOG.md
git commit -m "chore: bump version and add changelog entry for query_loop location filter"
```

### Task 26: Push the feature branch and open a PR

**Files:** none — git / gh only.

- [ ] **Step 1: Push the branch**

```bash
git push -u origin feat/query-loop-geo-tagging-hooks
```

Expected: branch pushed successfully.

- [ ] **Step 2: Open the PR**

```bash
gh pr create --title "feat(query-loop): add aucteeno_query_loop_location filter hook" --body "$(cat <<'EOF'
## Summary

- Adds a new filter hook `aucteeno_query_loop_location` in `blocks/query-loop/render.php` that lets extension plugins override the resolved country/subdivision pair before it is written into `$query_args`.
- Filter is wrapped in `! $has_product_ids` so it does not fire for blocks in product-IDs mode.
- Defensively sanitizes the filter return value — malformed returns fall through to pre-filter values.
- Version bumped patch-level, CHANGELOG updated.

## Why

This hook is the contract consumed by the new `aucteeno-nexus-geo-tagging` extension plugin, which implements opt-in Cloudflare-header-based country/subdivision filtering. Keeping the hook here and the implementation in the extension plugin enforces a clean ownership boundary — the base plugin carries the feature's contract (one filter) but none of its implementation.

## Test plan

- [x] `./vendor/bin/phpunit tests/Query_Loop_Location_Filter_Test.php` passes
- [x] `./vendor/bin/phpunit` (full suite) passes
- [x] `./vendor/bin/phpcs blocks/query-loop/render.php` passes
- [ ] Manual QA: install `aucteeno-nexus-geo-tagging`, verify Query Loop Inspector shows the new Geo-Tagging panel and toggling it visibly changes rendered results when the request has CF headers

## Related

Design spec: see `aucteeno-nexus-geo-tagging` repo at `docs/superpowers/specs/2026-04-11-aucteeno-nexus-geo-tagging-design.md`.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Expected: PR opened. Return the URL.

**STOP HERE** and ask the user whether to merge the PR themselves or wait for review.

---

## Chunk 9: End-to-end smoke test

**Repos:** both.

### Task 27: Push the extension plugin

**Files:** none — git only.

- [ ] **Step 1: Verify clean working tree**

```bash
cd wp-content/plugins/aucteeno-nexus-geo-tagging
git status
```

Expected: clean.

- [ ] **Step 2: Push to master**

```bash
git push origin master
```

Expected: push succeeds. The `package.yml` release workflow will trigger and create the first GitHub Release with `v0.1.0`. If the workflow fails, investigate before proceeding.

### Task 28: Manual smoke test in a browser

**Files:** none — browser-based validation.

- [ ] **Step 1: Ensure both plugins are activated in WordPress**

In a test WordPress install where `aucteeno` feature branch is installed and `aucteeno-nexus-geo-tagging` is activated:
- Visit the WordPress admin.
- Open a page/post containing an Aucteeno Query Loop block.
- Select the block; confirm the "Geo-Tagging" panel appears in the Inspector sidebar.
- Toggle "Enable geo-tagging" on. Confirm the "Also apply to bots" toggle appears.

- [ ] **Step 2: Save the post and visit the frontend**

- Visit the page on the frontend as a normal browser (non-bot UA).
- If the site is behind Cloudflare and has `CF-IPCountry` set (your real country), confirm the block shows items matching your country.
- If the site is NOT behind Cloudflare, confirm the block renders normally (no change from pre-feature behavior — graceful degradation).

- [ ] **Step 3: Smoke test bot behavior**

Use curl with a Googlebot UA to simulate a bot:

```bash
curl -A "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)" \
     -H "CF-IPCountry: US" \
     https://your-test-site.test/page-with-query-loop/
```

Expected: the rendered HTML shows unfiltered (or attribute-filtered) results — not US-filtered — because bots bypass geo-tagging by default.

- [ ] **Step 4: Smoke test bot opt-in**

Toggle "Also apply to bots" on in the block, save, and re-run the curl command. The rendered HTML should now show US-filtered results.

- [ ] **Step 5: Report back**

Document any issues or unexpected behaviors observed during the smoke test. If all checks pass, the feature is complete.

---

## After-plan checklist

Before marking this plan complete:

- [ ] `aucteeno-nexus-geo-tagging` has ~34 passing PHPUnit tests.
- [ ] `aucteeno-nexus-geo-tagging` has passing PHPCS, lint-js, and `npm run build`.
- [ ] `aucteeno-nexus-geo-tagging` has a green CI run on `master` and a created `v0.1.0` release.
- [ ] `aucteeno` feature branch has 3 new tests passing, full suite passing, PR opened.
- [ ] Manual smoke test passed for enabled/disabled, bot-allowed/bot-disallowed, and missing-CF-headers cases.
- [ ] Both plugins installed together in a test WordPress environment show the expected Inspector panel and visibly alter frontend results based on CF headers.

## Notes for the executing agent

- **Commits are frequent and small.** Every task ends in at least one `git commit`. Do not batch commits across tasks.
- **Cross-repo discipline.** Each task states the repo. Double-check `git status` and `pwd` before committing — easy to accidentally commit aucteeno-nexus-geo-tagging changes to the aucteeno repo or vice versa.
- **Stop and ask if a step fails unexpectedly.** Especially: if the aucteeno feature-branch test in Task 23 can't be written cleanly against `render.php` as-is, surface the problem before refactoring the base plugin's file structure.
- **The spec is the source of truth.** If you notice a contradiction between this plan and `docs/superpowers/specs/2026-04-11-aucteeno-nexus-geo-tagging-design.md`, stop and ask.
