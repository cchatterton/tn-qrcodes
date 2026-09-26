# Changelog

All notable changes to TN QR Codes are recorded here.

## 1.5.2 - 2026-09-26

- Lower the PHP requirement to 7.4, matching WordPress 7.0.
- Allow the PHP 7.4-compatible TN Update Controller bootstrap.

## 1.5.1 - 2026-09-26

- Require WordPress 7.0+ and PHP 8.5+ for this release.

- Replace the independent GitHub updater with the version 1 TN Update Controller integration.
- Add local Install/Activate/Check controller actions and standardise Techn author/repository metadata.
- Preserve plugin identity, feature code, settings and activation scope; no feature-plugin release discovery runs during page rendering.

## 1.5 - 2026-06-14

- Added GitHub release update support for native WordPress plugin updates.
- Added compliant plugin metadata, version constant, and GitHub repository links.
- Moved QR logic into a focused function file and kept the main plugin file as a loader.
- Moved inline admin JavaScript and CSS into dedicated asset files.
- Improved nonce handling and QR download filename sanitisation.
- Added release ZIP build script and repository documentation.

## 1.4 - 2026-03-17

- Added support for `utm_source`, `utm_medium`, and `utm_campaign`.

## 1.0 - 2025-01-01

- Initial plugin release.
