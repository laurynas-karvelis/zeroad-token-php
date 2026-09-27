# zeroad.network/token (PHP)

Recognise [Zero Ad Network](https://zeroad.network) subscribers in your PHP backend, and serve them a clean page.
Verification runs offline, with no dependencies beyond `ext-sodium` and no calls back to us.

```bash
composer require zeroad.network/token
```

**In short**

- Subscribers pay one monthly membership, called Freedom, and use our browser extension.
- On your website, the extension sends a signed `Better-Web-Token` header. This package checks it and answers yes or no.
- For a yes, serve the page without ads, cookie banners, non-essential trackers or marketing popups. If you sell access, unlock your included paid content.
- You earn from the time subscribers spend on your website, and keep 70% of your share. Transfers to Stripe
  start once your balance reaches $30 and payout setup is complete. [How earnings work →](https://zeroad.network/docs/monetization)

Step-by-step guide: [PHP guide](https://zeroad.network/docs/site-integration/remove-ads/php).
Using WordPress? Use the [WordPress plugin](https://zeroad.network/docs/site-integration/remove-ads/wordpress) instead.

This is the PHP port of [`@zeroad.network/token`](https://www.npmjs.com/package/@zeroad.network/token). It
speaks the same wire format, so a token verifies identically on either.

---

## Two headers

This package handles both ends:

| Direction      | Header                 | Carries                                                   |
| :------------- | :--------------------- | :-------------------------------------------------------- |
| You → visitor  | `Better-Web-Publisher` | Your Publisher ID, so the extension finds you and credits the visit |
| Visitor → you  | `Better-Web-Token`     | Their signed membership token, bound to your hostname     |

**Already clean?** If your website has no ads, trackers, cookie banners, popups or paywall, you only need
to send `Better-Web-Publisher`. You don't need to verify anything.
[Check whether your website is already clean →](https://zeroad.network/docs/site-integration#is-your-website-already-clean)

---

## Integrate

### 1. Copy your Publisher ID

[Sign in](https://zeroad.network/login), then copy your **Publisher ID** from
[Websites & creators](https://zeroad.network/sites#publisher-id). It starts with `zapub_`.

- You don't need a paid membership to publish.
- Use the same ID on every website you run. There is no separate sign-up per website.

### 2. Create a publisher

```php
use ZeroAd\Token\Publisher;

$publisher = Publisher::create([
    "publisherId" => $_ENV["ZERO_AD_PUBLISHER_ID"],
    "hostnames"   => "example.com", // also covers www.example.com; pass an array for other hosts
    "cache"       => ["store" => "auto"], // share results through APCu when available
]);
```

Create it in your bootstrap. With PHP-FPM, that runs once per request. In a long-running application,
reuse the instance across requests.

`hostnames` lists every host you serve. It's required, because a token only verifies on a listed host.
See [why hostnames are an allowlist](#why-hostnames-are-an-allowlist).

### 3. Check every request

```php
header("{$publisher->headerName}: {$publisher->headerValue}");

$visitor = $publisher->verify(
    $_SERVER[$publisher->tokenHeaderServerKey] ?? null,
    $_SERVER["HTTP_HOST"] ?? ""
);
```

This does two things on every request:

1. Sends `Better-Web-Publisher`, before any output, even when no token arrived. This is how the extension discovers your website.
2. Checks the visitor's token. PHP exposes it as `$_SERVER["HTTP_BETTER_WEB_TOKEN"]`, which
   `$publisher->tokenHeaderServerKey` gives you.

### 4. Serve subscribers the clean page

```php
if ($visitor->subscriber) {
    // Skip ads, cookie consent, non-essential trackers and marketing popups.
    // If you sell access, unlock your base subscription or included paid content.
}
```

Unlock paid content on the server. Hiding a paywall overlay doesn't help if the content was never sent.
Higher tiers can stay restricted.

### 5. Keep subscriber pages out of shared caches

If a CDN, reverse proxy or page cache sits in front of PHP, set it to bypass the cache for requests carrying `Better-Web-Token`.
Those requests must reach PHP. [Set up page caching and CDNs →](https://zeroad.network/docs/site-integration/remove-ads/caching)

### 6. Check it works

1. Confirm your responses include `Better-Web-Publisher`:
   `curl -s -D - -o /dev/null https://example.com/ | grep -i better-web-publisher`
2. In your dashboard, open your website's page and select **Test in your browser**. No paid membership needed.
3. Reload your website. You should see the clean page.
4. Open the same URL without the extension, with caches warm. You should see the normal page.

Laravel and Symfony middleware to copy are in [Framework examples](#framework-examples). The rest of the guide
is at [zeroad.network/docs](https://zeroad.network/docs/site-integration/remove-ads/php).

---

## Framework examples

In middleware, check the request, set the publisher header on the response, and pass `$visitor` to your templates.

### Laravel

```php
namespace App\Http\Middleware;

use Closure;
use ZeroAd\Token\Publisher;

class ZeroAdNetwork
{
    private $publisher;

    public function __construct()
    {
        $this->publisher = Publisher::create([
            "publisherId" => config("zeroad.publisher_id"),
            "hostnames"   => config("zeroad.hostnames"),
        ]);
    }

    public function handle($request, Closure $next)
    {
        $visitor = $this->publisher->verify(
            $request->header($this->publisher->tokenHeaderName),
            $request->getHost()
        );

        $request->attributes->set("visitor", $visitor);

        $response = $next($request);
        $response->headers->set($this->publisher->headerName, $this->publisher->headerValue);
        return $response;
    }
}
```

### Symfony

```php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use ZeroAd\Token\Publisher;

class ZeroAdNetworkListener
{
    private $publisher;

    public function __construct(string $publisherId, string $hostname)
    {
        $this->publisher = Publisher::create([
            "publisherId" => $publisherId,
            "hostnames"   => $hostname,
        ]);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $visitor = $this->publisher->verify(
            $request->headers->get($this->publisher->tokenHeaderName),
            $request->getHost()
        );
        $request->attributes->set("visitor", $visitor);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $event->getResponse()->headers->set($this->publisher->headerName, $this->publisher->headerValue);
    }
}
```

### WordPress

Use the [Zero Ad Network plugin](https://wordpress.org/plugins/zero-ad-network/). It bundles this SDK, and
also handles page caches and popular ad, consent, popup and membership plugins. See the
[WordPress guide](https://zeroad.network/docs/site-integration/remove-ads/wordpress).

---

## API

### `Publisher::create(array $options)`

| Option                  | Type             | Default      |                                                             |
| :---------------------- | :--------------- | :----------- | :---------------------------------------------------------- |
| `publisherId`           | `string`         | -            | From your dashboard. `zapub_` followed by 24 alphanumerics. |
| `hostnames`             | `string\|array`  | -            | Every host you serve. An apex covers its `www`. Ports, schemes and paths are stripped. |
| `publicKey`             | `string`         | platform key | Override for staging and tests. Leave alone in production.  |
| `clockToleranceSeconds` | `int`            | `60`         | Slack on expiry, for servers whose clocks drift.            |
| `cache`                 | `bool\|array`    | on           | See [caching](#caching). `false` turns it off.              |

It returns a `Publisher`. Reuse it within the request, or across requests in a long-running application:

|                                             |                                                       |
| :------------------------------------------ | :---------------------------------------------------- |
| `$publisher->header`                        | `["Better-Web-Publisher", "zapub_..."]`               |
| `$publisher->headerName` / `->headerValue`  | the same, separately                                  |
| `$publisher->tokenHeaderName`               | `"Better-Web-Token"`                                  |
| `$publisher->tokenHeaderNameLowercase`      | `"better-web-token"`                                  |
| `$publisher->tokenHeaderServerKey`          | `"HTTP_BETTER_WEB_TOKEN"`, the `$_SERVER` key         |
| `$publisher->verify($token, $hostname?)`    | `VerificationResult`                                  |
| `$publisher->cacheStats()`                  | `["size", "maxSize", "hits", "misses", "evictions"]`  |
| `$publisher->clearCache()`                  | drops every cached verdict                            |

### `$publisher->verify($token, $hostname = null)`

Pass the raw header value: a `string`, an `array` (some stacks return an array for a repeated header; the
first wins), or `null`. It never throws on bad input. A junk token is a result, not an exception.

- **Pass the actual public request hostname,** including when you serve both an apex and `www`.
- **If you omit it,** the single configured hostname is used. With several configured hostnames, omitting it throws.
- **A hostname outside the allowlist** is rejected.

`$visitor->subscriber` tells you which branch you're in:

```php
$visitor = $publisher->verify($token, $host);

if ($visitor->subscriber) {
    $visitor->plan;      // Constants::PLAN["FREEDOM"]
    $visitor->planName;  // "Freedom"
    $visitor->expiresAt; // \DateTimeImmutable
} else {
    $visitor->reason;    // one of the Rejection::* constants
}

$visitor->hostname; // what it was verified against
$visitor->cached;   // whether this skipped the cryptography
```

`$visitor->toArray()` gives a JSON-friendly copy, with `expiresAt` as a Unix timestamp.

### `Rejection`

A reason names the check that failed. It isn't proof of an attack.

| Reason (`Rejection::`) | Means                                                  | What to do                       |
| :--------------------- | :----------------------------------------------------- | :------------------------------- |
| `MISSING`              | No token header. Most of your traffic.                 | Nothing. This is normal.         |
| `MALFORMED`            | Not a well-formed token.                               | Nothing.                         |
| `UNSUPPORTED_VERSION`  | Unsupported token format.                              | Check for an SDK update.         |
| `EXPIRED`              | Expiry is past the allowed time.                       | Nothing, unless it comes in bursts. |
| `UNKNOWN_HOSTNAME`     | The host isn't in your allowlist.                      | Check your `hostnames` config.   |
| `WRONG_HOSTNAME`       | Authority signature passed; hostname signature failed. | Check the hostname and your proxy. |
| `FORGED`               | Authority signature failed.                            | Check for a `publicKey` override. |

When a token arrives with a version newer than this package understands, it's rejected as
`UNSUPPORTED_VERSION`, and a line in the PHP error log suggests an SDK update. The warning alone doesn't
prove the token is genuine, or that a protocol upgrade has shipped. To silence it during a staged rollout,
or in tests that send such tokens on purpose, call `ZeroAd\Token\VersionWarning::suppress()` once at startup.

### Also available

All in the `ZeroAd\Token` namespace: `Constants` (`PLAN`, `PLAN_NAME`, `PUBLISHER_HEADER`, `PUBLISHER_ID_SCHEME`,
`TOKEN_HEADER`, `TOKEN_HEADER_LOWERCASE`, `TOKEN_HEADER_SERVER_KEY`, `AUTHORITY_PUBLIC_KEY`, `PROTOCOL_VERSION`),
`Rejection`, `VerificationResult`, `Token::TOKEN_BYTES`, `Token::TOKEN_CHARACTERS`, `Hostname::canonical()`,
`PublisherHeader::encode()`, `PublisherHeader::parse()` and `VersionWarning::suppress()`.

This package **only verifies**. The platform's private authority key signs credentials, and the extension's
private ephemeral keys bind them to hostnames. Neither key is in a visitor token.

---

## Good to know

- **The first visit may have no token.** The extension discovers your ID from a page it has already loaded.
  The next page or a reload carries the token.
- **Tokens reach page and media requests only.** The extension adds them to HTTPS main-frame and media
  requests for the exact recognised hostname. Not to fetch/XHR calls, and not to subdomains.
- **Proxies must pass things through.** Forward `Better-Web-Token`, and keep the public `Host` header.
- **A header alone never grants access.** Only a successful `verify()` does.

---

## Caching

The extension reuses a token for its bound hostname while the credential is valid. So a returning visitor
may send bytes you've already checked. Caching skips repeating the signature checks. It's on by default.

This caches verification results, not HTML. For page caches, see
[Page caching and CDNs](https://zeroad.network/docs/site-integration/remove-ads/caching).

```php
Publisher::create([
    "publisherId" => "zapub_...",
    "hostnames"   => "example.com",
    "cache"       => ["ttl" => 600000, "maxSize" => 5000], // or "cache" => false
]);
```

| Option    | Default    |                                                                       |
| :-------- | :--------- | :-------------------------------------------------------------------- |
| `enabled` | `true`     |                                                                       |
| `ttl`     | `600000`   | milliseconds a verdict is trusted                                     |
| `maxSize` | `1000`     | entries (memory store only; APCu manages its own memory)              |
| `store`   | `"memory"` | `"memory"`, `"apcu"` or `"auto"`: where verdicts live (see below)     |
| `prefix`  | see below  | namespaces the APCu keys; the memory store ignores it                 |

Three things worth knowing:

**Failures are cached too.** A forged token costs as much to reject as a real one costs to accept, and
whoever sends it will likely send it again. This is safe: for a fixed public key, a rejection can never
later become an acceptance. The only direction a verdict moves is from valid to expired, and each entry's
own expiry handles that.

**A success never outlives the token.** The stored expiry is the earlier of your TTL and the token's own
`expiresAt`. A generous TTL can't extend anybody's membership.

**Cheap rejections are not cached.** A length or byte check throws out a malformed, missing or expired
token. Caching those would save nothing, and would let anyone fill memory with distinct keys.

### Where results live

| Store      | Lifetime under PHP-FPM | Use it when |
| :--------- | :--------------------- | :---------- |
| `"memory"` | Ends with each request, even when the worker is reused | Long-running apps that reuse the publisher |
| `"apcu"`   | Shared across requests in the same APCu segment | You know APCu is installed |
| `"auto"`   | APCu when present, otherwise memory | Code that ships to hosts you don't control |

With `"apcu"`, if the extension isn't available, the publisher logs a line and falls back to memory. The
token is still verified, just not shared. `"auto"` falls back silently.

```php
Publisher::create([
    "publisherId" => "zapub_...",
    "hostnames"   => "example.com",
    "cache"       => ["store" => "apcu", "prefix" => "zeroad:token:"],
]);
```

- **`prefix`** namespaces the keys, so several websites sharing one APCu segment don't collide. `clearCache()`
  removes only keys under that prefix.
- **With APCu,** `cacheStats()` reports `evictions` as `0`, because APCu evicts under its own memory
  pressure. `maxSize` is advisory.
- **With memory,** entries are evicted least-used-first, oldest breaking ties.

---

## How the token works

You don't need this to integrate. You may want it before you trust it.

A token is 174 bytes, 232 base64url characters, and carries **two** Ed25519 signatures.

```
  offset  size  field
       0     1  version
       1     1  plan
       2     4  expiresAt, u32 unix seconds, little-endian
       6    32  ephemeralPublicKey
      38    64  authoritySignature   over "better-web:credential:v1" || bytes[0..38)
     102     8  nonce
     110    64  hostnameSignature    over "better-web:hostname:v1"  || bytes[0..110) || hostname
```

**1. The platform signs batch credentials.** The extension generates disposable key pairs locally, and sends
only the public halves. The platform checks the account's membership, then signs the plan, expiry and each
key. Standard credentials expire at midnight UTC two days after issue, about 24–48 hours later. The
extension checks its pool hourly, and refreshes when credentials run low or near expiry. Demo and
publisher-test access use separately issued tokens, restricted to one hostname.

**2. The extension binds one to your hostname.** The first time it meets `example.com`, it takes an unused
key pair and signs your hostname with the private half. This happens offline. It reuses that bound token
for your exact hostname while it stays valid.

**3. You verify both signatures.** The first proves the platform authorised the plan and expiry. The second
proves the token was bound to _your_ hostname.

The hostname is deliberately absent from the wire. Your server already knows what it serves, and rebuilds
the signed message from that. A token bound elsewhere simply fails the signature.

### What this stops

The token you receive contains a **public** key, and a signature over **your own** hostname. The secret that
makes bindings never leaves the visitor's browser.

- You can't present a visitor's token at another website. That needs a signature over the other website's
  hostname, and you don't have the key.
- Nobody can edit the plan or extend the expiry. The platform signature covers both.
- Nobody can create a valid credential without the platform's private key.

### Privacy

- Tokens contain no account ID, name or email.
- Reusing a token allows correlation and replay on the same host while it's valid.
- Issuance is authenticated, so the platform sees which account requests each public key. This isn't blind
  issuance or mathematical anonymity.
- The extension separately uploads account-linked attention data, including creator page URLs.
- Offline verification can't revoke an issued token straight away after cancellation or account closure.
  It stops working when it expires.

### Why hostnames are an allowlist

`hostnames` is required, and `verify()` won't fall back to whatever arrived in the `Host` header. Tokens are
bound to a hostname, and the client sets `Host`. Without the allowlist, an attacker could bind a token to a
domain they control, send it with `Host: that-domain.example`, and be admitted as a subscriber. Listing your
hosts removes that possibility.

`www.example.com` and `example.com` are different hosts, but listing either admits both. They're the same
domain under one owner, so a website serving both needs only one in the list. The signature is still checked
against the exact host each request arrives on.

---

## Performance

Measured on PHP 8.5, Apple Silicon, single core, libsodium 1.0.22, via `php benchmarks/verify.php`:

|                               |                                                      |
| :---------------------------- | :--------------------------------------------------- |
| Cold verification, end to end | 88µs, about 11,300/s                                 |
| Cached verdict                | 0.95µs, about 1,050,000/s                            |
| Malformed token               | about 1.3µs, rejected on length before it's decoded  |

Verification is two Ed25519 checks through `ext-sodium` (`sodium_crypto_sign_verify_detached`). The cold
cost tracks libsodium, and is close to the TypeScript SDK. At about 0.09ms, it's a rounding error next to a
database query or a template render.

On classic PHP-FPM, the default memory cache lasts only for its request. So the cached figure applies within
a request, and a returning visitor's token is verified cold on the next page load. Use
`"cache" => ["store" => "apcu"]` or `"auto"` to share results across the whole worker pool.

---

## Requirements

- PHP 7.2 or newer, including PHP 8
- `ext-sodium`, bundled with PHP since 7.2
- Optional: [APCu](https://www.php.net/manual/en/book.apcu.php), to share verification results between requests

---

## Troubleshooting

**Every visitor comes back `MISSING`.** That's expected: only subscribers send a token. Check that
`Better-Web-Publisher` appears on your responses, then test with **Test in your browser**.

**A test subscriber comes back `MISSING`.** Reload after the first visit. Then check that your CDN or proxy
forwards `Better-Web-Token` to PHP, and doesn't serve a cached page.

**`UNKNOWN_HOSTNAME`.** The host isn't in `hostnames`. The `www`/apex sibling of a listed host counts as
listed. Log `$visitor->hostname` to see what arrived; a reverse proxy may pass something unexpected.

**`WRONG_HOSTNAME` from real visitors.** Apex and `www` share allowlist coverage, but their signatures are
different. A token for `example.com` can't verify against `www.example.com`. Pass the actual public hostname,
and check for proxy rewrites. A modified or replayed token can also cause this.

**`FORGED` for everybody.** A `publicKey` override left over from staging.

**It got slower under load.** Check `$publisher->cacheStats()`. A high `evictions` count, with `size` at
`maxSize`, means the working set outgrew the cache: raise `maxSize`. Create the publisher once per request
under PHP-FPM, or reuse it in a long-running app. Use APCu to cache across PHP-FPM requests.

---

## License

Apache-2.0
