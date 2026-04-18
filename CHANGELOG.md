# Changelog

All notable changes to the Crossroads core engine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `Builder::_writeHeaders()` generates a Cloudflare Pages `_headers` file with RFC 8288 Link headers on `/*` advertising `sitemap`, `alternate` (RSS feed), and `describedby` (llms.txt) for AI agent discovery
- `Renderer::_buildListingMarkdown()` emits `.md` companions for home, paginated index, content-index, and taxonomy pages — previously only single-content pages had markdown twins, leaving list pages with no response when agents request `text/markdown`
- `Builder::mdPath()` helper for deriving the markdown companion path from an HTML URL
- `Content-Signal` directive in `robots.txt` (`_writeRobots()`), configurable via `site.content_signals` with default `ai-train=no, search=yes, ai-input=yes`

### Changed
- Markdown companion filenames switched from `foo.html.md` to `foo.md` — cleaner URLs, matches the Jekyll/Hugo convention, simplifies Cloudflare Transform Rule expressions for content negotiation

## [1.6.0] - 2026-03-04

### Fixed
- Exception shadowing in `Engine` and `DevServer`: `catch (Exception)` in namespace `CR` only caught `CR\Exception`, missing `RuntimeException`, `TypeError`, etc. — now catches `\Throwable` as fallback
- `Menu::loadMenus()` assigned `false` to typed `array` property when `YAML::parse_file()` failed, causing `TypeError` crash on malformed `menus.yaml`
- Content hash overwritten in `Entries::loadAllDb()` — DB hash was immediately replaced with a different algorithm, causing mismatches between file-based and DB-based load paths
- `Theme::getAssetHash()` used file size concatenation instead of content hashing — CSS edits that didn't change file size failed to bust cache
- Foreign key constraints in `taxonomy.sql` and `images.sql` lacked `ON DELETE CASCADE`, leaving orphaned rows on content deletion

### Added
- `Builder::sanitizeDescription()` public static method extracted from `_writeLlmsTxt()` for testability

## [1.5.0] - 2026-03-02

### Fixed
- `DB::getAllTerms()` queried `content` table instead of `taxonomy` table
- `Theme::processAssets()` used wrong directory (`coreThemeDir` instead of `primaryThemeDir`) when copying theme images
- `Config::get()` called `debug_backtrace()` twice on every cache miss
- `YAML::parse_file()` and `YAML::parse()` silently swallowed parse errors (now logged via `LOG()`)
- `TemplateEngine::render()` returned `null` when Latte engine uninitialized (now returns empty string with error log)
- `Engine::_import()` hardcoded WordPress importer class instead of using dynamic class name from CLI argument
- `Menu::loadMenus()` replaced base menu data with local overrides instead of merging them
- `WordPressPlugin::contentFilter()` discarded first `str_replace` result in caption replacement chain
- `LogListenerFile` leaked file handles (added `__destruct()` to close handle)
- `en.yaml` typos: "conetnt" → "content", "understoof" → "understood"
- `es.yaml` `exec_command` string had 1 format placeholder instead of 3 (matching `en.yaml`)
- `Builder::_writeLlmsTxt()` multi-line descriptions broke llms.txt list item format (now collapsed to single line, truncated at 200 chars)

### Added
- Default `contentFilter()` passthrough on base `Plugin` class (prevents fatal error when `PluginManager::contentFilter()` is called on plugins without override)

### Removed
- Dead `WebServer` class (`src/WebServer.php`) — superseded by `DevServer`
- Dead `Builder::_write404Page()` method — called non-existent `Renderer::render404Page()`

## [1.4.0] - 2026-03-02

### Added
- `og:site_name`, `og:locale`, `twitter:site`, `<meta name="author">` tags on single pages
- `og:title` and `og:url` tags on index/taxonomy/home pages
- JSON-LD structured data (`BlogPosting` for posts, `WebSite` for home page)
- `<meta name="robots" content="noindex, follow">` on paginated pages (page 2+)
- Optional `noai, noimageai` robots directive via `options.noai` config

### Changed
- Centralize all SEO meta tag generation in SeoPlugin (removed from theme headers)
- `twitter:card` uses `summary` when no featured image, `summary_large_image` when present
- Generator meta tag now reads `Crossroads SSG` consistently across all themes

### Fixed
- `og:description` used `name=` instead of `property=` attribute in lumen and simple themes
- `twitter:creator` was hardcoded instead of reading from `site.social` config
- `og:type` was always `article` even on home and taxonomy pages
- `og:locale` now uses proper `language_TERRITORY` format (e.g. `en_US`)
- `templateParamFilter` was not called for index pages
- `templateParamFilter` ran before `isSingle` and `page->title` were set in single page rendering

## [1.3.0] - 2026-03-02

### Added
- Generate `.md` companion files alongside HTML for all content pages (llms.txt spec)
- Generate `/llms.txt` index file with all pages and recent 50 posts
- Reference `llms.txt` in `robots.txt`
- Configurable sidebar bio via `site.bio` config
- Configurable social links via `site.social` config

### Changed
- Show build timestamp on home page only (reduces noisy diffs)

## [1.2.0] - 2026-03-02

### Fixed
- Restore draft-skip in build mode, clean up stale draft HTML from output

### Changed
- Always build drafts with meta tag marker and visual banner, remove `--drafts` flag

## [1.1.0] - 2026-03-02

### Added
- Phosphor dark terminal theme
- Dev server with hot reload and draft post support
- Local config overlay and sample content scaffolding

## [1.0.0] - 2026-03-02

### Added
- Initial release of crossroads-core as a standalone Composer package
