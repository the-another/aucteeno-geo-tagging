# Aucteeno Nexus Geo-Tagging — Design Spec

**Date:** 2026-04-11
**Status:** Approved — ready for implementation plan
**Scope:** New extension plugin `aucteeno-nexus-geo-tagging` + one filter hook in base `aucteeno` plugin

---

## 1. Summary

Add an opt-in feature to the Aucteeno Query Loop block that filters listings by the visitor's Cloudflare-detected country and subdivision. The feature is delivered as a new, separately-distributed extension plugin (`aucteeno-nexus-geo-tagging`) that plugs into a single new filter hook added to the base `aucteeno` plugin. Bot traffic bypasses the filter by default; site operators can opt bots in via a per-block checkbox.

## 2. Goals & non-goals

### Goals

- Visitors browsing a geo-tagged Query Loop see auctions/items matching their Cloudflare-detected country and subdivision.
- The feature is explicitly opt-in per block. Disabled blocks behave exactly as before.
- Bot traffic (search crawlers, social previewers) bypasses the filter by default so crawlers see the block's default content.
- Clean ownership boundary: the base `aucteeno` plugin carries the feature's contract (one filter hook) but none of its implementation; the extension plugin carries all of the logic, UI, and settings.
- Graceful degradation: the feature silently no-ops on non-Cloudflare sites, on empty/malformed CF headers, and when the extension plugin is deactivated.

### Non-goals

- **Page caching:** out of scope. Site operators are responsible for configuring Varnish/Cloudflare/WP cache plugins to vary on `CF-IPCountry` or to bypass cache on pages with geo-tagged Query Loops. The plugin does not emit `Vary` headers or call `DONOTCACHEPAGE`.
- **Non-Cloudflare geo detection:** the plugin only reads Cloudflare headers. MaxMind, IP2Location, and other providers are out of scope.
- **Cloudflare Bot Management integration:** bot detection uses a User-Agent heuristic in PHP. Enterprise bot-management signals are explicitly not used.
- **Cache-safe AJAX hydration:** the feature operates entirely server-side at initial render. No REST-based hydration pattern is introduced.
- **Editor preview simulation:** Gutenberg's block preview reflects the admin's own Cloudflare location. A "simulate country X" UI is not provided.

## 3. Requirements

- WordPress 6.9+
- PHP 8.3+
- Base `aucteeno` plugin (declared via `Requires Plugins: aucteeno`)
- Site must be served through Cloudflare for the feature to do anything; the managed transform "Add visitor location headers" must be enabled for subdivision filtering to work.

## 4. Architecture

### 4.1 Two repositories, one contract

The feature splits across two repos with a minimal contract between them.

**`aucteeno` (base plugin)** — one new filter hook in `blocks/query-loop/render.php`. Nothing else. Delivered on a new feature branch `feat/query-loop-geo-tagging-hooks`.

**`aucteeno-nexus-geo-tagging` (extension plugin)** — new plugin carrying all geo-tagging logic. Delivered on `master` since it is not yet released.

### 4.2 Plugin shape — lightweight

No dependency injection container, no Hook_Manager, no Mozart build step, no external runtime dependencies. The plugin is three PHP classes autoloaded via Composer PSR-4. Rationale: the feature is ~200 lines of code — a DI container for that is overkill. The plugin still mirrors `aucteeno-nexus` in every other way (phpcs config, PHPUnit + Brain Monkey, CI workflows, Docker-wrapped Makefile, packaging).

### 4.3 Repository layout

```
aucteeno-nexus-geo-tagging/
├── aucteeno-nexus-geo-tagging.php        # plugin header, boot, version check
├── composer.json                          # PSR-4, phpcs, phpunit (no runtime deps)
├── package.json                           # @wordpress/scripts only
├── phpunit.xml.dist
├── .phpcs.xml.dist                        # copied from aucteeno-nexus
├── Makefile                               # copied and simplified (no Mozart step)
├── Dockerfile                             # copied from aucteeno-nexus
├── .dockerignore
├── .gitignore
├── .github/workflows/
│   ├── ci.yml                             # adapted from aucteeno-nexus, no mozart
│   └── package.yml                        # adapted from aucteeno-nexus
├── includes/
│   ├── class-geo-tagging.php              # plugin entry, registers hooks
│   ├── class-cloudflare-headers.php       # reads CF-IPCountry / CF-Region-Code
│   └── class-bot-detector.php             # UA regex list
├── assets/
│   └── js/
│       └── query-loop-inspector.js        # block editor filter + Inspector panel
├── dist/                                  # built JS (git-ignored, built in CI)
├── tests/
│   ├── bootstrap.php
│   └── Unit/
│       ├── Cloudflare_Headers_Test.php
│       ├── Bot_Detector_Test.php
│       └── Geo_Tagging_Test.php
├── README.md
├── readme.txt
└── CLAUDE.md
```

### 4.4 Namespace & constants

- Namespace: `The_Another\Plugin\Aucteeno_Nexus_Geo_Tagging`
- Constants: `AUCTEENO_NEXUS_GEO_TAGGING_VERSION`, `_PLUGIN_FILE`, `_PLUGIN_DIR`, `_PLUGIN_URL`, `_PLUGIN_BASENAME`
- Text domain: `aucteeno-nexus-geo-tagging`

### 4.5 Boot flow

Plugin file hooks `before_woocommerce_init` at priority 30 (after `aucteeno` default and `aucteeno-nexus` at 20), instantiates `Geo_Tagging`, calls `->init()`, which:

1. Registers the PHP filter on `aucteeno_query_loop_location`.
2. Enqueues the editor-side JS on `enqueue_block_editor_assets`.

No REST routes, no settings page, no admin menu. The entire admin UX is an Inspector panel on the Query Loop block.

## 5. The contract: `aucteeno_query_loop_location` filter

This is the only change to the base `aucteeno` plugin.

### 5.1 Signature

```php
/**
 * Filters the resolved location for the Aucteeno Query Loop block before querying.
 *
 * Fires after the base precedence chain (attribute → context → taxonomy archive)
 * has resolved $location_country and $location_subdivision, and before those values
 * are written into $query_args.
 *
 * @param array    $location   Two-element indexed array: [ string $country, string $subdivision ].
 *                             $country is a 2-letter ISO code ("US") or empty string.
 *                             $subdivision is "COUNTRY:REGION" ("US:KS") or empty string.
 * @param array    $attributes The block's resolved attributes array.
 * @param WP_Block $block      The block instance (includes context).
 * @return array Two-element indexed array in the same shape.
 */
$location = apply_filters(
    'aucteeno_query_loop_location',
    [ $location_country, $location_subdivision ],
    $attributes,
    $block
);
```

### 5.2 Placement

In `blocks/query-loop/render.php`:
- **Inside** the `! $has_product_ids` path (never fires when the block is in product-IDs mode, which resets `$query_args` anyway).
- **After** the existing fallback chain resolves `$location_country` and `$location_subdivision` (around current line 208).
- **Before** the `if ( ! empty( $location_country ) ) { $query_args['country'] = ...; }` assignments.

### 5.3 Return validation (defense in depth)

```php
if ( is_array( $location ) && count( $location ) === 2 ) {
    $location_country     = is_string( $location[0] ) ? sanitize_text_field( $location[0] ) : $location_country;
    $location_subdivision = is_string( $location[1] ) ? sanitize_text_field( $location[1] ) : $location_subdivision;
}
```

Malformed filter returns fall through silently — the pre-filter values are used.

### 5.4 Scope boundaries

- Filter does **not** receive `$query_args`. Scope is strictly the location pair.
- Filter does **not** fire when `$has_product_ids` is true (product-IDs branch short-circuits location filtering entirely).
- Filter does **not** interact with the REST pagination endpoint. Infinite scroll requests carry `country` / `subdivision` query params baked in at initial server render, so the first render's decision locks in for the session.

### 5.5 Filter naming rationale

`aucteeno_query_loop_location` — prefixed by plugin, scoped to the block, verb-less (it's a filter). Matches existing aucteeno filter naming.

## 6. Cloudflare header reader

### 6.1 Class: `Cloudflare_Headers`

Pure, stateless, no WordPress dependencies. Reads `$_SERVER` directly and validates via regex.

```php
final class Cloudflare_Headers {

    public function get_country(): string {
        $value = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
        if ( ! is_string( $value ) ) {
            return '';
        }
        $value = strtoupper( trim( $value ) );
        if ( ! preg_match( '/^[A-Z]{2}$/', $value ) ) {
            return '';
        }
        // Reject Cloudflare's "unknown" sentinels.
        if ( in_array( $value, [ 'XX', 'T1' ], true ) ) {
            return '';
        }
        return $value;
    }

    public function get_subdivision( string $country ): string {
        if ( '' === $country ) {
            return '';
        }
        $value = $_SERVER['HTTP_CF_REGION_CODE'] ?? '';
        if ( ! is_string( $value ) ) {
            return '';
        }
        $value = strtoupper( trim( $value ) );
        if ( ! preg_match( '/^[A-Z0-9]{1,3}$/', $value ) ) {
            return '';
        }
        return $country . ':' . $value;
    }
}
```

### 6.2 Header sources

- `HTTP_CF_IPCOUNTRY` — PHP-superglobal form of Cloudflare's `CF-IPCountry` header. Available on all paid CF plans.
- `HTTP_CF_REGION_CODE` — PHP-superglobal form of `Cf-Region-Code`. Part of Cloudflare's "Add visitor location headers" managed transform, which must be enabled in the Cloudflare dashboard (Rules → Managed Transforms).

### 6.3 Output format

The subdivision format `"COUNTRY:REGION"` (e.g. `"US:KS"`) matches the existing `aucteeno-location` taxonomy term meta `code` format used by `render.php` for subdivision filtering.

### 6.4 Validation rejections

- Empty / missing headers return empty string.
- Cloudflare sentinels `XX` (unknown) and `T1` (Tor exit) are rejected.
- Country must be exactly two A–Z characters after uppercase normalization.
- Region code must be 1–3 alphanumeric characters after uppercase normalization.
- Injection attempts, punctuation, and too-long values are rejected by regex.

## 7. Bot detector

### 7.1 Class: `Bot_Detector`

Pure, stateless, no WordPress dependencies. Matches User-Agent substrings against a curated list.

```php
final class Bot_Detector {

    private const BOT_PATTERNS = [
        'bot', 'crawler', 'spider', 'slurp',
        'googlebot', 'bingbot', 'yandexbot', 'duckduckbot', 'baiduspider',
        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'pinterestbot',
        'whatsapp', 'telegrambot', 'discordbot', 'slackbot',
        'applebot', 'petalbot', 'semrushbot', 'ahrefsbot',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright',
    ];

    public function is_bot( ?string $user_agent = null ): bool {
        if ( null === $user_agent ) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        if ( ! is_string( $user_agent ) || '' === $user_agent ) {
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

### 7.2 Design decisions

- **Empty / missing UA is treated as bot.** Safer default for curl, scripts, and scrapers that omit the header.
- **Curated list, not a library.** Using `jaybizzle/crawler-detect` or similar would require Mozart prefixing (rejected in §4.2). The ~20-pattern list is maintainable and catches search engines, social previewers, and headless browsers.
- **Generic `bot` / `crawler` / `spider` fragments** catch the long tail (PetalBot, SogouBot, etc.) without naming each one.
- **UA is a parameter with `$_SERVER` fallback** — trivially testable without mutating globals.
- **Case-insensitive via pre-lowered haystack**, not regex `/i` flag — single `strtolower` + N `strpos` is faster than N regex passes.
- **Known minor false positive risk:** a browser UA containing `"Robot"` would match `bot` substring. Modern browsers don't contain this. Accepted — the failure mode is "bot-classified human sees default content," which is the safe direction.

## 8. Render-time behavior

### 8.1 `Geo_Tagging::filter_location()` — full logic

```php
public function filter_location( array $location, array $attributes, \WP_Block $block ): array {
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

    // 4. CF wins (decision §10.1). Return the new values; base re-sanitizes.
    return [ $country, $subdivision ];
}
```

No try/catch: the code is straight-line with no throwing branches, and a bare `\Throwable` catch would swallow legitimate bugs in testing.

### 8.2 End-to-end precedence

**Before this feature:**
```
attribute → block context → taxonomy archive → query_args
```

**With feature enabled, human visitor (or bot + affects-bots), CF country valid:**
```
attribute → block context → taxonomy archive → [discarded] → CF country/subdivision → query_args
```

**With feature disabled, or bot without affects-bots, or CF country empty/invalid:**
```
attribute → block context → taxonomy archive → query_args   // unchanged
```

Every failure mode is graceful degradation to the pre-filter behavior.

### 8.3 REST pagination behavior

- Initial server render runs the filter, and the resolved country/subdivision flows into `$interactivity_context['country']` / `['subdivision']` (already existing code in `render.php`, lines 341–342, which reads from `$query_args`).
- Pagination/infinite-scroll requests forward those values as query params to `/aucteeno/v1/auctions` or `/items`.
- The first render's decision locks in for the session — pagination stays consistent even if the visitor's Cloudflare location hypothetically changed mid-session.

### 8.4 Trust boundaries

- `$_SERVER['HTTP_CF_IPCOUNTRY']` is trusted only after regex validation. Injection attempts are rejected.
- On sites not behind Cloudflare, an attacker could forge the header directly — but the damage surface is "attacker sees a specific country's auctions," which is not a security issue (same result is achievable by setting `locationCountry` on the block via the existing REST pagination endpoint).
- Base-plugin return validation (§5.3) re-sanitizes the filter return value as defense in depth.

## 9. Editor UI

### 9.1 Injected block attributes

Two attributes added at runtime via `wp.hooks.addFilter( 'blocks.registerBlockType', ... )`, scoped to `name === 'aucteeno/query-loop'`. Not declared in the base `block.json`.

```js
{
    geoTaggingEnabled: { type: 'boolean', default: false },
    geoTaggingAffectsBots: { type: 'boolean', default: false },
}
```

Runtime-injected attributes persist in saved block markup normally. Deactivating the extension plugin leaves the saved attributes dormant in post content (harmless); reactivating restores full behavior without data migration.

### 9.2 Inspector panel

Injected via `editor.BlockEdit` higher-order-component filter. Renders a `PanelBody` titled "Geo-Tagging" after the existing Query Loop inspector panels, collapsed by default.

- **Enable geo-tagging** — `ToggleControl`, controls `geoTaggingEnabled`. Help text: "When enabled, the visitor's Cloudflare-detected country and region override any manually set location filters."
- **Also apply to bots** — `ToggleControl`, controls `geoTaggingAffectsBots`. **Conditionally rendered only when `geoTaggingEnabled` is true.** Help text: "By default, detected bots (search crawlers, social previewers) bypass geo-tagging so they see the block's default content. Enable this to apply geo-tagging to bots too."

### 9.3 Enqueueing

```php
add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
```

Script built with `@wordpress/scripts`. Entry `assets/js/query-loop-inspector.js` → `dist/query-loop-inspector.js`. The generated `dist/query-loop-inspector.asset.php` provides auto-resolved dependencies (`wp-hooks`, `wp-block-editor`, `wp-components`, `wp-element`, `wp-i18n`).

### 9.4 Why runtime attribute injection instead of declaring in `block.json`

Because ownership matters. The whole point of the "extension is fully additive" approach is that the extension adds its own attributes. Declaring them in base `block.json` would mean `aucteeno` owns attributes for a feature it doesn't implement. The slight awkwardness of runtime attributes (they're not in the block.json schema) is the cost of a clean ownership boundary, and it's worth paying.

## 10. Decisions log

These are the explicit decisions made during design review, preserved for implementation.

1. **Precedence when enabled: CF wins.** When `geoTaggingEnabled === true`, Cloudflare-derived location overrides any manually-set `locationCountry` / `locationSubdivision` on the block, including taxonomy archive context. The editor explicitly opted in; they own the consequence.
2. **Granularity: country + subdivision.** Both `CF-IPCountry` and `Cf-Region-Code` are used when available; subdivision alone is not used.
3. **Bot detection: UA heuristic.** No Cloudflare bot headers are read; the plugin uses its own User-Agent pattern list.
4. **Cache: operator's problem.** The plugin does not emit `Vary`, does not call `DONOTCACHEPAGE`, does not hydrate via AJAX. Documented as a known limitation.
5. **Extension is fully additive.** Base plugin change is one filter hook and defensive sanitization. All feature logic, UI, and attributes live in the extension.
6. **Plugin shape: lightweight.** No DI container, no Hook_Manager, no Mozart. Three PHP classes, PSR-4 autoload.
7. **Taxonomy archive override is accepted.** When geo-tagging is enabled on a block placed on `/aucteeno-location/canada/` and the visitor has `CF-IPCountry: US`, the visitor sees US auctions. Documented as expected behavior.
8. **Filter no-ops silently on missing/invalid CF headers.** No debug logging. Non-Cloudflare sites get no behavior change and no errors.
9. **No try/catch safety net** in the filter callback. The code is straight-line with no throwing branches.
10. **Filter placed inside the `! $has_product_ids` branch** — blocks in product-IDs mode are unaffected.
11. **Starting version: `0.1.0`.** First stable release will be `1.0.0` per `aucteeno-nexus` pattern.
12. **CI: depot runners, PHPUnit only, PHP 8.3 only.** No Playwright for Gutenberg panel, no matrix. Matches `aucteeno-nexus`.

## 11. Edge cases

| # | Case | Behavior | Test |
|---|------|----------|------|
| 1 | Non-Cloudflare site (no CF headers) | Filter returns pre-filter values unchanged. | `Cloudflare_Headers_Test::test_returns_empty_when_header_missing` |
| 2 | CF `XX` or `T1` sentinel | Rejected, returns empty. | `Cloudflare_Headers_Test::test_rejects_unknown_sentinels` |
| 3 | Country present, region header missing | Returns `[country, '']` — block filters to country only. | `Cloudflare_Headers_Test::test_subdivision_empty_when_region_header_missing` |
| 4 | Malformed region code (injection) | Regex rejects, returns empty subdivision. | `Cloudflare_Headers_Test::test_rejects_malformed_region` |
| 5 | Empty User-Agent | Treated as bot, feature skipped unless affects-bots. | `Bot_Detector_Test::test_empty_user_agent_is_bot` |
| 6 | False-positive UA containing "Robot" | Classified as bot. Accepted risk. | N/A |
| 7 | Geo-tagged block with `productIds` context | Filter does not fire (placed inside `! $has_product_ids` branch). | Verified at implementation time. |
| 8 | Taxonomy archive + geo-tagging enabled | CF overrides archive — accepted behavior. | `Geo_Tagging_Test` (stubbed). |
| 9 | Multiple Query Loops, only some geo-tagged | Filter respects each block's attributes independently. | `Geo_Tagging_Test::test_only_affects_enabled_blocks` |
| 10 | Editor preview (Gutenberg SSR) | Reflects admin's own Cloudflare location. Documented limitation. | N/A |
| 11 | Exception in filter callback | Not expected; straight-line code. No try/catch. | N/A |
| 12 | Valid CF, zero results | Block shows "No auctions found." empty state. Troubleshooting note in readme. | N/A |

## 12. Testing strategy

PHPUnit 11 + Brain Monkey, no WordPress loaded, no database.

**`Cloudflare_Headers_Test` (~8 tests)**
- Returns empty when `HTTP_CF_IPCOUNTRY` absent.
- Rejects `XX` and `T1` sentinels.
- Rejects malformed values (lowercase, too-long, punctuation, SQL fragments).
- Returns valid two-letter code when well-formed.
- Returns empty subdivision when country is empty.
- Returns empty subdivision when `HTTP_CF_REGION_CODE` absent.
- Returns empty subdivision when region code is malformed.
- Returns `"COUNTRY:REGION"` format when both valid.

**`Bot_Detector_Test` (~10 tests)**
- Empty UA is bot.
- Null UA falls back to `$_SERVER`.
- Googlebot, Bingbot, facebookexternalhit detected.
- Generic `*bot*`, `*crawler*`, `*spider*` substring fragments caught.
- Chrome, Firefox, Safari, Edge desktop UAs are NOT bots.
- Mobile Safari / Chrome Mobile are NOT bots.
- Case-insensitive matching (`GoogleBot`, `googlebot`, `GOOGLEBOT`).
- Accepts UA as parameter (testable without mutating `$_SERVER`).

**`Geo_Tagging_Test` (~10 tests)** — stubs `Cloudflare_Headers` and `Bot_Detector`.
- `geoTaggingEnabled=false` returns pre-filter values unchanged.
- Bot + `geoTaggingAffectsBots=false` returns pre-filter unchanged.
- Bot + `geoTaggingAffectsBots=true` applies CF.
- Human + enabled + CF country present → CF wins.
- Human + enabled + CF country empty → pre-filter unchanged.
- CF country present, subdivision empty → returns `[country, '']`.
- Manual `locationCountry="CA"` overridden by CF `US` when enabled.
- Multiple blocks on page, only enabled block affected.
- Malformed filter input (non-array `$location`) handled defensively.
- Meta-test on `init()` confirms filter registered with priority 10 and 3 args.

**Total: ~28 tests, runnable in under a second.**

**Not tested in the plugin:**
- JS block editor filter — manual QA in Gutenberg. Logic is trivial (one HOC, two ToggleControls).
- `aucteeno_query_loop_location` filter wiring in base `render.php` — tested on the `aucteeno` side as part of the feature-branch work.

## 13. Packaging

### 13.1 `composer.json`

```json
{
    "name": "the-another/aucteeno-nexus-geo-tagging",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=8.3"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "brain/monkey": "^2.6",
        "wp-coding-standards/wpcs": "^3.0",
        "automattic/vipwpcs": "^3.0",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "The_Another\\Plugin\\Aucteeno_Nexus_Geo_Tagging\\": "includes/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "The_Another\\Plugin\\Aucteeno_Nexus_Geo_Tagging\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "phpcs": "phpcs",
        "phpcbf": "phpcbf"
    }
}
```

No Mozart. No runtime dependencies.

### 13.2 Makefile

Copied from `aucteeno-nexus` with the `build` target simplified to `npm run build` only (no `mozart-build` step). All commands Docker-wrapped for environment consistency. Targets: `install`, `install-dev`, `build`, `lint`, `format`, `test`, `all`, `clean`, `release`.

### 13.3 Plugin header

```
Plugin Name: Aucteeno Nexus Geo-Tagging
Plugin URI: https://theanother.org/plugin/aucteeno-nexus-geo-tagging/
Description: Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.
Version: 0.1.0
Author: The Another
Author URI: https://theanother.org
Requires at least: 6.9
Requires PHP: 8.3
Requires Plugins: aucteeno
Text Domain: aucteeno-nexus-geo-tagging
License: GPL v2 or later
GitHub Plugin URI: https://github.com/the-another/aucteeno-nexus-geo-tagging
Primary Branch: master
Release Asset: true
```

## 14. CI workflows

Copied from `aucteeno-nexus/.github/workflows/` and adapted.

### 14.1 `ci.yml`

- Runners: **`depot-ubuntu-24.04`** (all jobs).
- Triggers: `push` and `pull_request` on all branches.
- Jobs:
  - `build` — `npm ci` + `npm run build`.
  - `phpunit` — depends on `build`; runs PHPUnit.
  - `phpcs` — depends on `build`; runs PHPCS.
  - `lint-js` — depends on `build`; runs `npm run lint:js`.
- PHP 8.3 only, no matrix.
- Concurrency group cancels in-progress runs on new pushes to the same branch.
- No Mozart step.

### 14.2 `package.yml`

- Runners: **`depot-ubuntu-24.04`**.
- Triggers: `push` to `master`.
- Builds production assets, zips the plugin, creates a GitHub Release with the zip as a release asset (matches `Release Asset: true` in the plugin header for the `GitHub Plugin URI` auto-updater).

## 15. `aucteeno` feature-branch scope

Single PR on branch `feat/query-loop-geo-tagging-hooks`:

1. Add the `apply_filters( 'aucteeno_query_loop_location', ... )` call inside the `! $has_product_ids` path in `blocks/query-loop/render.php`.
2. Add defense-in-depth sanitization of the filter return value (§5.3).
3. Add a PHPUnit test asserting the filter fires with the right arguments and that a non-array return is ignored.
4. Version bump `aucteeno.php` (patch bump: `1.2.2` → `1.2.3`).
5. Add a `CHANGELOG.md` entry.

No other files touched. No new dependencies. No other block changes.

## 16. Known limitations (for readme)

- **Not behind Cloudflare:** the feature silently does nothing. No warnings, no errors.
- **Cloudflare subdivision header disabled:** only country-level filtering applies. Enable "Add visitor location headers" in the Cloudflare dashboard under Rules → Managed Transforms to enable subdivision filtering.
- **Page caching:** a cached page served to a visitor from country X will continue to show X's auctions to visitors from country Y until cache expiry. Site operators must configure Varnish/Cloudflare/WP cache plugins appropriately or bypass cache on pages with geo-tagged Query Loops.
- **Editor preview:** reflects the admin's own Cloudflare location. There is no "simulate country X" UI.
- **Taxonomy archive pages:** when geo-tagging is enabled on a block placed on an `aucteeno-location` taxonomy archive, the Cloudflare location overrides the archive's country. This is intentional.
- **Zero results:** if the visitor's country has no matching auctions, the block shows its "No auctions found." empty state. This is correct behavior.
