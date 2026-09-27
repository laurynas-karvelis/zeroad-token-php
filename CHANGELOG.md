# Changelog

All notable changes to `zeroad.network/token` for PHP. Versions follow
[Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-09-27

### Changed

- README and `SKILLS.md` updates. The code is unchanged.
- Docs correction: under PHP-FPM, the default memory cache lasts only one
  request. Use `"cache" => ["store" => "auto"]` to share results across requests
  through APCu.

## [1.0.0] - 2026-09-26

First stable release, and a port of the TypeScript SDK 1.0.0. It replaces the
0.x API, headers and token format. 0.x releases can't verify current membership
tokens, so upgrade to use this version.

### Added

- `Publisher::create(["publisherId" => ..., "hostnames" => ...])` returns a
  publisher for your bootstrap.
- `$publisher->headerName` and `$publisher->headerValue` send
  `Better-Web-Publisher`, so the extension finds your website and credits the visit.
- `$publisher->verify($token, $hostname)` checks the `Better-Web-Token` header
  offline. It never throws on bad input. It returns a `VerificationResult` with
  `subscriber` and, for a rejection, a `reason` from `Rejection`: `MISSING`,
  `MALFORMED`, `UNSUPPORTED_VERSION`, `EXPIRED`, `UNKNOWN_HOSTNAME`,
  `WRONG_HOSTNAME` or `FORGED`.
- `$publisher->tokenHeaderServerKey` gives the `$_SERVER` key for the token header.
- Tokens are bound to a hostname with two Ed25519 signatures. An apex hostname
  and its `www` sibling share one allowlist entry, but each is signed separately.
  Tokens verify the same way as in the TypeScript SDK.
- Built-in verdict cache, with `cacheStats()` and `clearCache()`. The `store`
  option picks `"memory"`, `"apcu"` or `"auto"`, and `prefix` namespaces APCu keys.
- Runs on PHP 7.2+ and 8.x, with no dependencies beyond `ext-sodium`. `ext-apcu`
  is optional.
- `SKILLS.md`, a reference for coding agents, ships in the package.

### Removed

- The 0.x `Site` class, its `X-Better-Web-Welcome` and `X-Better-Web-Hello`
  headers, and the `Logger` class.

[1.0.1]: https://github.com/laurynas-karvelis/zeroad-token-php/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/laurynas-karvelis/zeroad-token-php/tree/1.0.0
