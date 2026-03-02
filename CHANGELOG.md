# Changelog

All notable changes to the Crossroads core engine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
