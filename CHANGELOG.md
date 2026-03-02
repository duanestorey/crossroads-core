# Changelog

All notable changes to the Crossroads core engine will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
