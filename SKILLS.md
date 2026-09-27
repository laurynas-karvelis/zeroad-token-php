# zeroad.network/token (PHP) - integration reference

Source of truth for writing or reviewing integration code against `zeroad.network/token` (PHP).
The package verifies Zero Ad Network subscriber tokens in a publisher backend. It cannot issue them.
It is the PHP port of `@zeroad.network/token`; same wire format, same behaviour.

---

## Decision tree

```
Adding Zero Ad Network to a site?
  -> Publisher::create() + one middleware. That is the whole API. See "The pattern".

Every visitor comes back subscriber === false?
  -> See "Troubleshooting", start with $visitor->reason.

Told to make verification faster?
  -> It is already cached. Under PHP-FPM, use "store" => "auto" or "apcu" to share results across
     requests. See "Caching". Do not hand-roll a second cache around it.

Looking for a way to sign or mint a token?
  -> Not here, by design. This package holds no private key.
```

---

## The pattern

There is one. Deviating from it is almost always a mistake.

```php
use ZeroAd\Token\Publisher;

// Once, in bootstrap - never inside a handler or template. Under PHP-FPM bootstrap runs once per
// request; long-running apps reuse the instance across requests.
$publisher = Publisher::create([
    "publisherId" => $_ENV["ZERO_AD_PUBLISHER_ID"],
    "hostnames"   => "example.com", // covers www.example.com too; pass an array for other hosts
    "cache"       => ["store" => "auto"], // APCu when available, so results survive the request
]);

// Per request, in global middleware:
//   1. announce participation on the response
//   2. verify the token on the request
header("{$publisher->headerName}: {$publisher->headerValue}");
$visitor = $publisher->verify($_SERVER[$publisher->tokenHeaderServerKey] ?? null, $_SERVER["HTTP_HOST"] ?? "");

if ($visitor->subscriber) {
    // suppress ads, trackers, consent dialogs, marketing modals; grant the publisher’s base subscription or custom included access level
}
```

`$publisher->tokenHeaderServerKey` is `"HTTP_BETTER_WEB_TOKEN"` - the key PHP puts the `Better-Web-Token`
request header under in `$_SERVER`. Use it; do not hand-build the `$_SERVER` key.

When you serve more than one hostname, pass the request's host as the second argument
(`$publisher->verify($token, $host)`) - a value outside the whitelist is rejected, never trusted.

---

## API surface

`Publisher::create(array $options): Publisher`

| Option                  | Required | Default      | Notes                                                              |
| :---------------------- | :------- | :----------- | :----------------------------------------------------------------- |
| `publisherId`           | yes      | -            | `zapub_` followed by exactly 24 alphanumerics. Throws otherwise.   |
| `hostnames`             | yes      | -            | `string\|array`. Whitelist; an apex covers its `www`. Scheme, port and path are stripped. |
| `publicKey`             | no       | platform key | Staging and tests only.                                            |
| `clockToleranceSeconds` | no       | `60`         |                                                                    |
| `cache`                 | no       | on           | `false`, or an array overriding `enabled` / `ttl` / `maxSize` / `store` / `prefix`. `store` is `"memory"` (default), `"apcu"` (shared across the FPM pool), or `"auto"`. |

`Publisher` (public properties and methods)

| Member                                              | Type                                                 |
| :-------------------------------------------------- | :--------------------------------------------------- |
| `->headerName`, `->headerValue`                     | `string`                                             |
| `->header`                                          | `[string, string]`                                   |
| `->tokenHeaderName`, `->tokenHeaderNameLowercase`   | `string`                                             |
| `->tokenHeaderServerKey`                            | `string` - `"HTTP_BETTER_WEB_TOKEN"`                 |
| `->verify($token, $hostname = null)`                | `VerificationResult`                                 |
| `->cacheStats()`                                    | `["size","maxSize","hits","misses","evictions"]`     |
| `->clearCache()`                                    | `void`                                               |
| `->publisherId`, `->hostnames`                      | echo of the resolved config                          |

`VerificationResult` - one object, `subscriber` says which branch:

```php
// subscriber:
$visitor->subscriber; // true
$visitor->plan;       // int, e.g. Constants::PLAN["FREEDOM"]
$visitor->planName;   // "Freedom"
$visitor->expiresAt;  // \DateTimeImmutable
$visitor->hostname;   // string
$visitor->cached;     // bool

// non-subscriber:
$visitor->subscriber; // false
$visitor->reason;     // Rejection::* string
$visitor->hostname;   // string
$visitor->cached;     // bool

$visitor->toArray();  // JSON-friendly copy, expiresAt as a unix timestamp
```

`Rejection::*`: `MISSING`, `MALFORMED`, `UNSUPPORTED_VERSION`, `EXPIRED`, `UNKNOWN_HOSTNAME`,
`WRONG_HOSTNAME`, `FORGED`.

`Constants::PLAN` is `["FREEDOM" => 1]`. One plan exists. The field is a byte, so more can be added
without a format change - treat an unrecognised plan as not entitled rather than as an error.

`VersionWarning::suppress(bool $suppressed = true)` silences the error-log line emitted when a token
arrives with a protocol version newer than this build understands (still rejected as
`UNSUPPORTED_VERSION`). Call it once at startup only to quiet a staged rollout or version-feeding tests -
never to paper over the real signal, which is that an upgrade is overdue.

---

## Rules

**Do**

- Create the publisher once, at bootstrap (a container singleton, a global, a static): once per request
  under PHP-FPM, once per process in a long-running app.
- Set `Better-Web-Publisher` on every response, including ones where no token arrived. It is how the
  extension discovers the site takes part.
- Pass the request's host to `verify()` when serving more than one hostname.
- Listing an apex admits its `www` sibling and vice versa, so a site serving both needs only one in the
  list. The signature is still checked against the exact host each request arrives on.
- Keep token-bearing requests out of shared page caches: bypass lookup and storage when
  `Better-Web-Token` is present, and send `Cache-Control: private, no-store` on those responses. See
  https://zeroad.network/docs/site-integration/remove-ads/caching.
- Log `WRONG_HOSTNAME` and `FORGED` counts. A spike can mean tampering or replay, but check proxy
  `Host` rewrites and `publicKey` overrides first. A reason names the failed check, not an attacker.

**Do not**

- Do not call `Publisher::create()` inside a request handler.
- Do not build your own cache around `verify()`. It caches successes and cryptographic failures already,
  keyed by hostname and token, bounded, with successes never outliving the token.
- Do not return `$visitor->reason` to the visitor in production. It tells an attacker which check failed.
- Do not set `publicKey` in production.
- Do not treat `verify()` as throwing. Junk input yields a non-subscriber result. It throws in exactly
  one case: several hostnames configured and none passed to the call.
- Do not gate anything on `expiresAt` yourself. It is already checked, with clock tolerance.
- Do not access `$_SERVER["Better-Web-Token"]` directly - PHP normalises it to `HTTP_BETTER_WEB_TOKEN`;
  use `$publisher->tokenHeaderServerKey`.
- Do not look for a signing, issuing or key-generation method. There is none in the shipped package.
- Do not grant access because a `Better-Web-Token` header is present, or because of a client-supplied
  cookie or header. Only `$visitor->subscriber === true` grants access.
- Do not hand-roll a WordPress integration. Use the `zero-ad-network` plugin, which bundles this SDK.

---

## Caching

On by default: `["enabled" => true, "maxSize" => 1000, "ttl" => 600000]` (ttl in milliseconds).

- Keyed by hostname **and** token, so one host cannot answer for another.
- Successes and cryptographic failures (`FORGED`, `WRONG_HOSTNAME`) are both cached - each costs the same
  to reach.
- Cheap rejections (`MISSING`, `MALFORMED`, `EXPIRED`) are **not** cached. Caching them would let anyone
  flood memory with distinct keys.
- A cached success is trusted for `min(ttl, token.expiresAt)`.
- Eviction is least-used-first, oldest breaking ties. Expired entries are swept every 128 writes.
- The default `"memory"` store belongs to the `Publisher` instance. Under PHP-FPM it ends with each
  request, even when the worker is reused. `"apcu"` shares results across requests in one APCu segment
  (logs and falls back to memory if APCu is missing); `"auto"` picks APCu when present, silently.
- `prefix` namespaces APCu keys; `clearCache()` removes only that prefix. With APCu, `evictions` is `0`
  and `maxSize` is advisory.

Turn it off with `"cache" => false`; tune with `"cache" => ["ttl" => ..., "maxSize" => ..., "store" => ...]`.

---

## Wire format

174 bytes, 232 base64url characters, two Ed25519 signatures.

```
  offset  size  field
       0     1  version (1)
       1     1  plan
       2     4  expiresAt, u32 unix seconds, little-endian
       6    32  ephemeralPublicKey
      38    64  authoritySignature   over "better-web:credential:v1" || bytes[0..38)
     102     8  nonce
     110    64  hostnameSignature    over "better-web:hostname:v1"  || bytes[0..110) || hostname
```

The authority signs the batch credential (bytes 0 to 37) at issuance, after checking the subscription is
live. The extension holds the matching ephemeral private key and signs the hostname locally, the first
time it meets a site. The hostname is not on the wire; the verifier rebuilds the signed message from the
host it serves, so a token bound elsewhere fails the signature rather than a string comparison.

`tests/Fixtures/Authority.php` is the reference implementation of both signing steps, kept out of the
shipped `src/`. It matches the TypeScript SDK's `authority.ts` byte for byte.

---

## Troubleshooting

| Symptom                          | Cause                                                                                  |
| :------------------------------- | :------------------------------------------------------------------------------------- |
| All `MISSING`                    | Normal - only subscribers send a token. Verify `Better-Web-Publisher` is on responses. |
| Test subscriber gets `MISSING`   | First visit has no token; reload. Else a CDN/proxy strips the header or serves cache.  |
| All `FORGED`                     | A `publicKey` override left over from staging.                                         |
| All `UNKNOWN_HOSTNAME`           | Host not in `hostnames`. Log `$visitor->hostname`; check the proxy.                    |
| `WRONG_HOSTNAME` from real users | Wrong exact host passed (apex vs `www`), a proxy rewriting `Host`, or token replay.    |
| `UNSUPPORTED_VERSION`            | Newer token format. Upgrade the package.                                               |
| `EXPIRED` in bursts              | Server clock drift. Raise `clockToleranceSeconds`, then fix NTP.                       |
| Throws "several hostnames"       | Multiple hosts configured, none passed to `verify()`.                                  |
| Slower under load                | PHP-FPM without APCu re-verifies each request: use `"store" => "auto"`. Or `maxSize` too small. |

---

## Requirements

PHP 7.2+ (or 8.0+), `ext-sodium` (bundled with PHP since 7.2). No other dependencies.
