# Aucteeno Geo-Tagging

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
make release       # produce build/aucteeno-geo-tagging.zip
```

## Architecture

See `docs/superpowers/specs/2026-04-11-aucteeno-geo-tagging-design.md` for
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
