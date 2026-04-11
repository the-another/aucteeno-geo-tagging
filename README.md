# Aucteeno Geo-Tagging

**Cloudflare geo-header based filtering for Aucteeno Query Loop blocks.**

- **Contributors:** The Another
- **Requires at least:** WordPress 6.9
- **Tested up to:** WordPress 6.9
- **Requires PHP:** 8.3
- **Requires Plugins:** [aucteeno](https://github.com/the-another/aucteeno)
- **Stable tag:** 0.1.0
- **License:** GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html

## Description

Aucteeno Geo-Tagging is an opt-in extension for the [Aucteeno](https://github.com/the-another/aucteeno)
plugin. When enabled per block, the Aucteeno Query Loop block automatically
filters auctions and items to match the visitor's Cloudflare-detected country
and subdivision — so a visitor from Manitoba sees Manitoba auctions, a visitor
from Kansas sees Kansas auctions, and so on.

The feature is strictly opt-in and scoped to individual blocks. Any Query Loop
block in your site without the toggle enabled is unaffected, so you can mix
globally-scoped and geo-scoped listings on the same page.

### What the plugin does

- Reads the `CF-IPCountry` and `CF-Region-Code` headers that Cloudflare sets on
  every request routed through its network.
- On any `aucteeno/query-loop` block where the **Geo-Tagging** toggle is
  enabled, overrides the block's `locationCountry` / `locationSubdivision`
  attributes with the visitor's detected values before the query runs.
- Skips bot traffic by default — search crawlers, social previewers, and
  headless browsers see the block's default (non-geo-filtered) content, which
  keeps SEO and link previews sane. An "Also apply to bots" toggle overrides
  this if you explicitly want bots to see geo-filtered results too.
- Silently no-ops when Cloudflare headers are absent or invalid (local dev,
  direct-to-origin requests, Cloudflare "unknown" sentinels like `XX` / `T1`).
  The block falls back to its normal behavior — nothing breaks.

### What the plugin deliberately does **not** do

- **No page cache integration.** The plugin does not emit `Vary` headers or
  call `DONOTCACHEPAGE`. If you run a page cache in front of WordPress, you are
  responsible for configuring it to vary on `CF-IPCountry` / `CF-Region-Code`
  (or to bypass caching on pages with geo-tagged blocks). Without this,
  cached pages will leak one visitor's region to another visitor.
- **No IP geolocation database.** The plugin is Cloudflare-only. If your site
  is not served through Cloudflare, the feature has no effect (it silently
  no-ops instead of erroring).
- **No client-side filtering.** Filtering happens server-side on the initial
  render and on REST API pagination calls. The visitor never sees a "flash of
  unfiltered content."

## Requirements

- WordPress 6.9 or higher
- PHP 8.3 or higher
- The [Aucteeno](https://github.com/the-another/aucteeno) plugin, installed
  and active (enforced via the `Requires Plugins` header)
- Your site served through Cloudflare
- The Cloudflare managed transform **"Add visitor location headers"** enabled
  in your Cloudflare dashboard (required for subdivision filtering; country
  detection works by default on all paid Cloudflare plans)

## Installation

1. Install and activate the **Aucteeno** plugin.
2. Install and activate **Aucteeno Geo-Tagging**.
3. Open a post, page, or FSE template that contains an **Aucteeno Query Loop**
   block.
4. Select the block and open the **Geo-Tagging** panel in the Inspector sidebar.
5. Toggle **Enable geo-tagging** on.
6. Optionally toggle **Also apply to bots** if you want crawlers and social
   previewers to see geo-filtered results too.
7. Save/Update the post or template.

For block-theme (FSE) sites, the Query Loop block often lives in a template
file or template part rather than a page's content. In that case, either edit
the template via Appearance → Editor, or add `"geoTaggingEnabled":true` to the
block delimiter in the theme's `.html` file directly.

## How it interacts with existing location attributes

The base Aucteeno Query Loop block already supports a manually-configured
location attribute (country + subdivision) that filters its results. When
geo-tagging is enabled on a block, the Cloudflare-derived values **replace**
whatever you've configured manually — Cloudflare wins. This is intentional:
geo-tagging is the "personalize to visitor" mode, and a hardcoded location
would defeat the purpose.

If you want different behavior on different blocks — e.g. a globally-pinned
"Featured from Texas" strip plus a geo-tagged main grid — simply leave
geo-tagging off on the Texas block and enable it on the main grid. The two
blocks coexist on the same page without interference.

## Frequently asked questions

### Does this work on local development?

Not by default — Cloudflare doesn't route local traffic, so the headers
aren't present and the feature no-ops. You can simulate it in local dev by
injecting the headers manually at your local reverse proxy or via a browser
extension like ModHeader:

```
CF-IPCountry: CA
CF-Region-Code: MB
```

### Why is my front-end still showing all auctions?

Most common cause: the Query Loop block being rendered is a different
instance than the one you toggled the setting on. In FSE block themes this
often means the block lives in `templates/front-page.html` or a template part
under `parts/`, not in a regular page's content. Check the rendered page's
block source and verify the specific block instance has
`"geoTaggingEnabled":true` in its delimiter.

Second most common cause: page caching. If a cached HTML response is served
before PHP runs, the plugin never gets a chance to filter.

### Does it work with bots and search engines?

By default, no — crawlers bypass the filter entirely and see the unfiltered
content. This is the right default for SEO: you don't want Googlebot indexing
different results per Google datacenter location. You can override this
per-block with the "Also apply to bots" toggle.

## Changelog

### 0.1.0

- Initial release.

## Contributing

Development setup, build commands, architecture notes, and design
documentation live in [`CONTRIBUTE.md`](CONTRIBUTE.md).
