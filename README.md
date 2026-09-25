# zeroad.network/token (PHP)

Verify [Zero Ad Network](https://zeroad.network) subscriber tokens in your PHP backend. Offline, with no
dependencies beyond `ext-sodium` and no calls back to us.

```bash
composer require zeroad.network/token
```

This is the PHP port of [`@zeroad.network/token`](https://www.npmjs.com/package/@zeroad.network/token).
It speaks the exact same wire format, so a token minted by the platform verifies identically on either.

---

## The thirty second version

Zero Ad Network subscribers pay a monthly fee and install a browser extension. When one of them visits
a recognized website, the extension attaches a cryptographically signed token to eligible requests.
You verify it locally, and if it checks out, serve a clean page without ads, non-essential third-party trackers, cookie consent screens,
or marketing popups, including newsletter signup prompts. If you sell access, grant your base subscription
or a custom level that unlocks paid content or functionality. Higher tiers may remain restricted.

Earnings are calculated monthly from funded subscriber attention and allocation preferences. Publishers share
70% of received revenue after processing fees and excluding tax; the platform retains 30%. Transfers to
Stripe Express require a $30 accumulated balance and completed, eligible payout setup. Smaller balances
carry forward. A Stripe transfer is separate from a bank withdrawal.
See [how earnings work](https://zeroad.network/docs/monetization).

The SDK verifies membership; your application decides which content belongs to that included access level.
A subscriber token does not grant site administration, prove a purchase, or replace private-content permissions.
An already clean, unrestricted site only needs to announce its Publisher ID.

Two headers, and this package handles both ends:

| Direction      | Header                 | Carries                                         |
| :------------- | :--------------------- | :---------------------------------------------- |
| You -> visitor | `Better-Web-Publisher` | your publisher ID, so the visit can be credited |
| Visitor -> you | `Better-Web-Token`     | their signed, hostname-bound membership token   |

---

## Requirements

| Runtime | Version | Ready |
| :------ | :------ | :---: |
| PHP 7   | 7.2+    |  ✅   |
| PHP 8   | 8.0+    |  ✅   |

`ext-sodium` (bundled with PHP since 7.2) is the only dependency.

---

## Integrate

### 1. Register

[Sign up](https://zeroad.network/login) and copy your account’s **Publisher ID** (`zapub_...`).

You do not need a paid subscription or separate registration for each site. Announce the same ID on
all your properties. Accepted subscriber activity creates an observed integration after upload and
processing. You can also add and verify a website from your dashboard, then use **Test in your browser**.
Test access earns nothing.

### 2. Create a publisher

```php
use ZeroAd\Token\Publisher;

// Bootstrap per PHP-FPM request; reuse the instance in long-running applications.
$publisher = Publisher::create([
    "publisherId" => $_ENV["ZERO_AD_PUBLISHER_ID"],
    "hostnames"   => "example.com", // covers www.example.com too; pass an array for other hosts
]);
```

`hostnames` is every host you serve. It is required, and it matters - see
[why hostnames are a whitelist](#why-hostnames-are-a-whitelist). Listing an apex covers its `www` (and
vice versa), so `"example.com"` already admits `www.example.com`.

### 3. Wire up one middleware

Two things happen on every request: announce participation on the response, and verify the token on the
request. PHP exposes the request header under a `$_SERVER` key, which `$publisher->tokenHeaderServerKey`
gives you.

```php
header("{$publisher->headerName}: {$publisher->headerValue}");

$visitor = $publisher->verify(
    $_SERVER[$publisher->tokenHeaderServerKey] ?? null,
    $_SERVER["HTTP_HOST"] ?? ""
);
```

### 4. Branch on it

```php
if ($visitor->subscriber) {
    // Remove ads and interruptions; grant your included content access.
}
```

Apply that decision in your rendering and access rules, and configure page caches as described below.
A working example lives in [`examples/`](./examples).

> Set `Better-Web-Publisher` even on pages where you never read a token. It is how the extension
> discovers that your site takes part at all, and how visits get attributed to you.

---

## API

### `Publisher::create(array $options)`

| Option                  | Type             | Default      |                                                             |
| :---------------------- | :--------------- | :----------- | :---------------------------------------------------------- |
| `publisherId`           | `string`         | -            | From your dashboard. `zapub_` followed by 24 alphanumerics. |
| `hostnames`             | `string\|array`  | -            | Every host you serve; an apex covers its `www`. Ports, schemes and paths are stripped. |
| `publicKey`             | `string`         | platform key | Override for staging and tests. Leave alone in production.  |
| `clockToleranceSeconds` | `int`            | `60`         | Slack on expiry, for servers whose clocks drift.            |
| `cache`                 | `bool\|array`    | on           | See [caching](#caching). `false` disables it.               |

Returns a `Publisher` instance. Reuse it within the request, or across requests in a long-running application:

|                                             |                                                       |
| :------------------------------------------ | :---------------------------------------------------- |
| `$publisher->headerName` / `->headerValue`  | `"Better-Web-Publisher"` and `"zapub_..."`            |
| `$publisher->header`                        | `["Better-Web-Publisher", "zapub_..."]`               |
| `$publisher->tokenHeaderName`               | `"Better-Web-Token"`                                  |
| `$publisher->tokenHeaderNameLowercase`      | `"better-web-token"`                                  |
| `$publisher->tokenHeaderServerKey`          | `"HTTP_BETTER_WEB_TOKEN"`, the `$_SERVER` key         |
| `$publisher->verify($token, $hostname?)`    | `VerificationResult`                                  |
| `$publisher->cacheStats()`                  | `["size", "maxSize", "hits", "misses", "evictions"]`  |
| `$publisher->clearCache()`                  | drops every cached verdict                            |

### `$publisher->verify($token, $hostname = null)`

Takes the raw header value - a `string`, an `array` (some stacks hand back an array for a repeated
header; the first wins), or `null`. Never throws on bad input; a junk token is a result, not an
exception.

Pass the actual public request hostname, including when serving both an apex and `www`. Omitting it
uses the single configured hostname. A hostname outside the allowlist is rejected.

Returns a `VerificationResult`. `subscriber` says which branch you are in:

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

`$visitor->toArray()` gives a JSON-friendly copy (with `expiresAt` as a unix timestamp).

The only case `verify()` throws is when several hostnames are configured and none is passed.

### `Rejection`

Rejection reasons describe the failed check, not proof of an attack.

| Reason (`Rejection::`) | Means                                                  | Ordinary?                        |
| :--------------------- | :----------------------------------------------------- | :------------------------------- |
| `MISSING`              | No token header. Most of your traffic.                 | yes                              |
| `MALFORMED`            | Not a well-formed token.                               | yes                              |
| `UNSUPPORTED_VERSION`  | Unsupported format; check for an SDK update.           | yes, but see below               |
| `EXPIRED`              | Expiry field is past the allowed time.                 | yes                              |
| `UNKNOWN_HOSTNAME`     | The host asked for is not in your whitelist.           | check your config                |
| `WRONG_HOSTNAME`       | Authority signature passed; hostname signature failed. | check hostname, proxy, or token  |
| `FORGED`               | Authority signature failed.                            | check authority key or token     |

When a token arrives whose version is newer than this package understands, it is rejected as
`UNSUPPORTED_VERSION` and a line is written to the PHP error log suggesting an SDK update.
The warning alone does not prove that the token is genuine or that a protocol upgrade has shipped. To
silence it - during a staged rollout, or in tests that feed such tokens on purpose - call
`ZeroAd\Token\VersionWarning::suppress()` once at startup.

This package **only verifies**. The platform’s private authority key signs credentials; the extension’s
private ephemeral keys bind those credentials to hostnames. Neither key is included in a visitor token.

---

## Discovery and page caching

The first request to an unfamiliar site may have no token: the extension discovers your ID from the
response or loaded page. Reload after recognition. Production injection covers HTTPS main-frame and
media requests for the exact recognized hostname, not arbitrary fetch/XHR or subdomains.

Forward `Better-Web-Token` and preserve the public request hostname through proxies. Configure every
CDN, proxy, and page cache to bypass both lookup and storage for token-bearing requests, and return
private, non-cacheable subscriber responses. Header presence alone must never grant access. Test the
same URL with and without a valid token while caches are warm. The SDK’s result cache below caches
verification decisions, not HTML.

## Caching

The extension reuses a token for its bound hostname while that credential remains valid, so a returning
visitor may send bytes you have already checked. Caching avoids repeating the signature checks. It is on by default and there is rarely a reason to touch it.

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
| `maxSize` | `1000`     | entries (memory store only; APCu manages its own memory)             |
| `store`   | `"memory"` | `"memory"`, `"apcu"`, or `"auto"` - where verdicts live (see below)   |
| `prefix`  | see below  | namespaces the APCu keys; ignored by the memory store                 |

Three things it does that are worth knowing about:

**Failures are cached too.** A forged token costs exactly as much to reject as a real one costs to
accept, and whoever sends it is likely to send it again. This is safe because, for a fixed public key, a
rejection can never later become an acceptance.

**A success never outlives the token.** The stored expiry is the earlier of your TTL and the token's own
`expiresAt`, so a generous TTL cannot extend anybody's subscription.

**Cheap rejections are not cached.** A malformed, missing or expired token is thrown out by a length or
byte check. Caching those would save nothing and would hand anyone who can send a request an easy way to
fill memory with distinct keys.

The default **memory** store belongs to the `Publisher` instance. Under ordinary PHP-FPM it ends
with the request, even when the worker process is reused. In a long-running application, reuse the
instance to keep cached results across requests. Entries are evicted least-used-first, oldest breaking ties.

### Sharing verdicts across requests with APCu

On a classic PHP-FPM stack, application memory starts afresh for each request, so a returning
visitor’s token is re-verified. Use APCu to share verdicts across requests using the same APCu segment:

```php
Publisher::create([
    "publisherId" => "zapub_...",
    "hostnames"   => "example.com",
    "cache"       => ["store" => "apcu", "prefix" => "zeroad:token:"],
]);
```

`"apcu"` requires the [APCu extension](https://www.php.net/manual/en/book.apcu.php); if it is not
available the publisher logs a line and falls back to the memory store, so a token is still verified,
just not shared. `"auto"` picks APCu when present and memory otherwise, silently - a good default for code
that ships to hosts you do not control. The `prefix` namespaces the keys so several sites sharing one
APCu segment do not collide, and `clearCache()` removes only keys under that prefix. With the APCu store,
`cacheStats()` reports `evictions` as `0` (APCu evicts under its own memory pressure) and `maxSize` is
advisory.

---

## How the token works

You do not need this to integrate. You may want it before you trust it.

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

**The platform signs batch credentials.** The extension checks its pool hourly and refreshes when
credentials run low or approach expiry. It generates disposable keypairs locally and sends only the
public halves to the platform. The platform checks account entitlement and signs the plan, expiry, and
each key. Standard credentials expire at midnight UTC two days after issuance, about 24–48 hours later.
Demo and publisher-test access use separately issued, hostname-restricted tokens.

**The extension binds one to your hostname.** Offline, the first time it meets `example.com` it takes an
unused keypair and signs your hostname with the private half, then reuses that bound token until it
expires.

**You verify both signatures.** The first proves the platform authorized the credential’s plan and expiry.
The second proves it was bound to _your_ hostname.

The hostname is deliberately absent from the wire. Your server already knows what it serves and rebuilds
the signed message from that, so a token bound elsewhere simply fails the signature.

Tokens contain no account ID, name, or email. Reuse permits same-host correlation and replay during
validity. Issuance is authenticated, so the platform sees the account requesting each public key;
this is not blind issuance or mathematical anonymity. The extension separately uploads account-linked
attention data, including creator page URLs. Offline verification cannot immediately revoke an issued
token after cancellation or account closure.

### Why hostnames are a whitelist

`hostnames` is required, and `verify()` will not fall back to whatever arrived in the `Host` header,
because tokens are bound to a hostname and `Host` is set by the client. Without the whitelist an attacker
could bind a token to a domain they control, send it with `Host: that-domain.example`, and be admitted.
Listing your hosts removes the possibility.

`www.example.com` and `example.com` are technically different hosts, but listing either admits both, so a
site that serves both needs only one in the list. The signature is still checked against the exact host
each request arrives on.

---

## Framework examples

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

```php
require_once __DIR__ . "/vendor/autoload.php";

use ZeroAd\Token\Publisher;

$GLOBALS["zeroad_publisher"] = Publisher::create([
    "publisherId" => get_option("zeroad_publisher_id"),
    "hostnames"   => parse_url(home_url(), PHP_URL_HOST),
]);

add_action("send_headers", function () {
    $publisher = $GLOBALS["zeroad_publisher"];
    header("{$publisher->headerName}: {$publisher->headerValue}");
});

add_action("init", function () {
    $publisher = $GLOBALS["zeroad_publisher"];
    $GLOBALS["zeroad_visitor"] = $publisher->verify(
        $_SERVER[$publisher->tokenHeaderServerKey] ?? null,
        $_SERVER["HTTP_HOST"] ?? ""
    );
});

// In a template:
if (($GLOBALS["zeroad_visitor"] ?? null) && $GLOBALS["zeroad_visitor"]->subscriber) {
    // clean page
}
```

---

## Performance

Measured on PHP 8.5, Apple Silicon, single core, libsodium 1.0.22, via `php benchmarks/verify.php`:

|                               |                                                     |
| :---------------------------- | :-------------------------------------------------- |
| Cold verification, end to end | 88us, about 11,300/s                                |
| Cached verdict                | 0.95us, about 1,050,000/s                           |
| Malformed token               | about 1.3us, rejected on length before it is decoded |

Verification is two Ed25519 checks through `ext-sodium` (`sodium_crypto_sign_verify_detached`), so the
cold cost tracks libsodium and is in the same ballpark as the TypeScript SDK. At ~0.09ms it is already a
rounding error next to a database query or a template render.

The one thing worth knowing is PHP's process model. The default memory cache lives only for the request
that filled it, so on a classic PHP-FPM stack the cached number above applies within a request, not
across them - a returning visitor's identical token is re-verified cold on the next page load. Point the
cache at APCu (`"cache" => ["store" => "apcu"]`, see [Caching](#caching)) to turn that repeat into a
shared-memory lookup across the whole worker pool.

---

## Troubleshooting

**Every visitor comes back `MISSING`.** Expected - only subscribers send a token. Confirm the pipe works
by checking `Better-Web-Publisher` appears on your responses (`curl -sI https://your-site`).

**`UNKNOWN_HOSTNAME`.** The host being verified is not in `hostnames` (the `www`/apex sibling of a listed
host counts as listed). Log `$visitor->hostname` to see what actually arrived; a reverse proxy may be
passing something you did not expect.

**`WRONG_HOSTNAME` from real visitors.** Apex and `www` share allowlist coverage, but their signatures
are distinct. Pass the actual public request hostname and check proxy rewrites. A modified or replayed
token can also fail the hostname signature.

**`FORGED` for everybody.** A `publicKey` override left over from staging.

**Slower than expected.** Check `$publisher->cacheStats()`. A high `evictions` count against `size` at
`maxSize` means the working set outgrew the cache - raise `maxSize`. Confirm you create the publisher
once per request in PHP-FPM, or reuse it across requests in a long-running application. Use APCu
for cross-request caching in PHP-FPM.

---

## License

Apache-2.0
