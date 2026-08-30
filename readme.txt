=== Aucteeno Geo-Tagging ===
Contributors: theanother
Tags: aucteeno, auction, cloudflare, geo, localization
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.

== Description ==

An opt-in extension for the Aucteeno plugin. When enabled per-block, the Query
Loop block filters auctions and items to match the visitor's Cloudflare-detected
country. Visitors in the United States and Canada are narrowed further to their
state or province; other countries stay at country level. Bot traffic (search
engines, social previewers) bypasses the filter by default, with an optional
toggle to include them.

Requires the site to be served through Cloudflare. See the plugin README for
full architecture notes and operator caveats, including the page-caching
limitation.

== Installation ==

1. Install and activate the Aucteeno plugin.
2. Install and activate Aucteeno Geo-Tagging.
3. Edit a post or page containing an Aucteeno Query Loop block.
4. Select the block and open the "Geo-Tagging" panel in the Inspector sidebar.
5. Toggle "Enable geo-tagging" on.
6. Optionally toggle "Also apply to bots" if you want crawlers to see filtered results.

== Changelog ==


= 0.2.1 - 2026-08-30 =
* Fixed: state/province narrowing applied to every country whose request carried a `cf-region-code` header, not only the markets it was designed for. Once the upstream proxy began forwarding that header, visitors outside the US and Canada were filtered to a subdivision code the Aucteeno location taxonomy has no term for — `DE:BY`, for example — so the Query Loop block rendered empty instead of falling back to a country-level list. Narrowing is now limited to United States and Canada visitors; every other country is filtered at country level.
* Added: `aucteeno_geo_tagging_subdivision_countries` filter exposing the list of countries eligible for state/province narrowing, so a new market can be opted in without a plugin release. Codes returned by the filter are normalised to uppercase, and non-string values are discarded.
* Docs: README.md gains the US/Canada rule under "What the plugin does" plus an "Extending" section documenting the new filter; readme.txt's description updated to match.

= 0.2.0 - 2026-04-12 =
* Fixed: release zip still shipped without the Composer `vendor/` directory despite 0.1.2 adding it to the `files` allowlist — `npm-packlist` was falling back to `.gitignore`, which excludes `/vendor/`. A `.npmignore` file now sits alongside `.gitignore` so `package.json`'s `files` field becomes the sole allowlist and `vendor/autoload.php` actually lands in the zip. The Geo-Tagging panel now appears in the Aucteeno Query Loop block inspector on a fresh install of the release zip.
* Changed: the missing-autoloader guard in the main plugin file no longer silently `return`s. An `register_activation_hook` refuses activation via `wp_die()` when `vendor/autoload.php` is absent, and a runtime guard surfaces an `admin_notices` error plus self-deactivates if the autoloader disappears from an already-active install. A broken archive can no longer masquerade as a working install.

= 0.1.2 - 2026-04-11 =
* Fixed: release zip was missing the Composer `vendor/` directory, causing the plugin to silently bail at the autoloader guard on fresh installs — the Geo-Tagging panel never appeared in the Aucteeno Query Loop block inspector. `vendor/` is now included in both the CI (`wp-scripts plugin-zip`) and dev-local (`make release`) packaging paths.

= 0.1.1 - 2026-04-11 =
* Docs: rewrote README.md as a user-facing plugin description with usage, FAQ, and an FSE-template troubleshooting note.
* Docs: moved contributor-facing content (dev setup, build commands, architecture pointer) to CONTRIBUTE.md.

= 0.1.0 =
* Initial release.
