=== Aucteeno Geo-Tagging ===
Contributors: theanother
Tags: aucteeno, auction, cloudflare, geo, localization
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.1.2
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
2. Install and activate Aucteeno Geo-Tagging.
3. Edit a post or page containing an Aucteeno Query Loop block.
4. Select the block and open the "Geo-Tagging" panel in the Inspector sidebar.
5. Toggle "Enable geo-tagging" on.
6. Optionally toggle "Also apply to bots" if you want crawlers to see filtered results.

== Changelog ==

= 0.1.2 - 2026-04-11 =
* Fixed: release zip was missing the Composer `vendor/` directory, causing the plugin to silently bail at the autoloader guard on fresh installs — the Geo-Tagging panel never appeared in the Aucteeno Query Loop block inspector. `vendor/` is now included in both the CI (`wp-scripts plugin-zip`) and dev-local (`make release`) packaging paths.

= 0.1.1 - 2026-04-11 =
* Docs: rewrote README.md as a user-facing plugin description with usage, FAQ, and an FSE-template troubleshooting note.
* Docs: moved contributor-facing content (dev setup, build commands, architecture pointer) to CONTRIBUTE.md.

= 0.1.0 =
* Initial release.
