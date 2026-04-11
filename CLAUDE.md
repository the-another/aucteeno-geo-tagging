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
