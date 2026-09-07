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
your site, the extension attaches a cryptographically signed token. You verify it locally, and if it
checks out you owe that visitor a clean page - no ads, no trackers, no cookie dialog, no paywall. Your
share of their subscription is paid out monthly based on the time they actually spent with you.

Two headers, and this package handles both ends:

| Direction      | Header                 | Carries                                         |
| :------------- | :--------------------- | :---------------------------------------------- |
| You -> visitor | `Better-Web-Publisher` | your publisher ID, so the visit can be credited |
| Visitor -> you | `Better-Web-Token`     | their signed, origin-bound subscription token   |

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

[Sign up](https://zeroad.network/login), add your site, and copy your **publisher ID** (`zapub_...`).

### 2. Create a publisher, once, at startup

```php
use ZeroAd\Token\Publisher;

// Create once, reuse across every request - it parses a key and owns the result cache.
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

$visitor = $publisher->verify($_SERVER[$publisher->tokenHeaderServerKey] ?? null);
```

### 4. Branch on it

```php
if ($visitor->subscriber) {
    // no ads, no trackers, no consent dialog, no paywall
}
```

That is the whole integration. A working example lives in [`examples/`](./examples).

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

Returns a `Publisher` you keep for the life of the process:

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

The hostname may be omitted when exactly one was configured. Pass the request's host when you serve
several - a host outside your whitelist is rejected, never trusted.

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

Worth logging. Most are ordinary; two are not.

| Reason (`Rejection::`) | Means                                            | Ordinary?                        |
| :--------------------- | :----------------------------------------------- | :------------------------------- |
| `MISSING`              | No token header. Most of your traffic.           | yes                              |
| `MALFORMED`            | Not a well-formed token.                          | yes                              |
| `UNSUPPORTED_VERSION`  | A newer token format. Upgrade this package.       | yes, but see below               |
| `EXPIRED`              | Genuine, but past its expiry.                     | yes                              |
| `UNKNOWN_HOSTNAME`     | The host asked for is not in your whitelist.      | check your config                |
| `WRONG_HOSTNAME`       | A genuine token minted for **a different site**.  | **somebody is replaying tokens** |
| `FORGED`               | Not signed by Zero Ad Network.                    | **somebody is minting tokens**   |

When a token arrives whose version is newer than this package understands, it is rejected as
`UNSUPPORTED_VERSION` and a line is written to the PHP error log telling you an upgrade is due. To
silence it - during a staged rollout, or in tests that feed such tokens on purpose - call
`ZeroAd\Token\VersionWarning::suppress()` once at startup.

This package **only verifies**. Nothing here can mint a token - that requires a private key that never
leaves the platform.

---

## Caching

A subscriber's token stays the same all day, so a returning visitor sends bytes you have already
checked. Verifying once and remembering the answer turns a pair of elliptic-curve operations into a map
lookup. It is on by default and there is rarely a reason to touch it.

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

The default **memory** store is per `Publisher` instance and lives for the life of the PHP process.
Under PHP-FPM that means per worker - it is not shared across workers, which is exactly why the
publisher is created once at startup and reused, never per request. Entries are evicted
least-used-first, oldest breaking ties.

### Sharing verdicts across requests with APCu

On a classic PHP stack a fresh process handles each request, so the memory store starts empty every
time and a returning visitor's identical token is re-verified from scratch. Point `store` at APCu and the
verdict computed by one request is there for the next, across the whole FPM pool:

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

**The platform signs a batch credential.** Once a day, the extension generates a batch of throwaway
keypairs locally and sends the public halves to us. We check the subscription is live, sign each one
together with the plan and an expiry truncated to midnight UTC, and send them back. We never see the
private halves, and the shared midnight expiry puts every subscriber in one anonymity set.

**The extension binds one to your hostname.** Offline, the first time it meets `example.com` it takes an
unused keypair and signs your hostname with the private half, then reuses that bound token until it
expires.

**You verify both signatures.** The first proves the platform issued the credential for a live
subscription. The second proves it was minted for _your_ host.

The hostname is deliberately absent from the wire. Your server already knows what it serves and rebuilds
the signed message from that, so a token bound elsewhere simply fails the signature.

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
    $GLOBALS["zeroad_visitor"] = $publisher->verify($_SERVER[$publisher->tokenHeaderServerKey] ?? null);
});

// In a template:
if (($GLOBALS["zeroad_visitor"] ?? null) && $GLOBALS["zeroad_visitor"]->subscriber) {
    // clean page
}
```

---

## Troubleshooting

**Every visitor comes back `MISSING`.** Expected - only subscribers send a token. Confirm the pipe works
by checking `Better-Web-Publisher` appears on your responses (`curl -sI https://your-site`).

**`UNKNOWN_HOSTNAME`.** The host being verified is not in `hostnames` (the `www`/apex sibling of a listed
host counts as listed). Log `$visitor->hostname` to see what actually arrived; a reverse proxy may be
passing something you did not expect.

**`WRONG_HOSTNAME` from real visitors.** Should be rare - `www` and apex are folded together. A steady
stream means tokens are being replayed from another site; a trickle is usually a proxy rewriting `Host`.

**`FORGED` for everybody.** A `publicKey` override left over from staging.

**Slower than expected.** Check `$publisher->cacheStats()`. A high `evictions` count against `size` at
`maxSize` means the working set outgrew the cache - raise `maxSize`. Confirm you create the publisher
once at startup, never per request.

---

## License

Apache-2.0
