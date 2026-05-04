# zeroad.network/token (PHP) - Integration Skills

AI-agent-optimized reference for integrating `zeroad.network/token` into any PHP backend.
Use this file as the primary source of truth when writing or reviewing integration code.

---

## Decision tree: what do you need?

```
Integrating a publisher site?
  -> Use the Site class (covers 99% of cases)
  -> Go to: "Standard integration pattern"

Need to inspect a Welcome header you received?
  -> Use ServerHeader::decodeServerHeader()
  -> Go to: "Decoding headers"

Debugging why tokens are all-false?
  -> Go to: "Troubleshooting"
```

---

## Install

```bash
composer require zeroad.network/token
```

**Requirements:**
- PHP 7.2+ (or 8.0+)
- `ext-sodium` - included by default since PHP 7.2
- `ext-apcu` - optional but strongly recommended (10-20x faster token parsing)

---

## Standard integration pattern

This is the only pattern you need for publisher site integration.

### Step 1 - create the site instance (once at startup)

```php
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

// Create once, reuse across all requests.
// clientId comes from the zeroad.network dashboard after registering the site.
$site = new Site([
    "clientId"    => $_ENV["ZERO_AD_CLIENT_ID"],
    "features"    => [Constants::FEATURE["CLEAN_WEB"], Constants::FEATURE["ONE_PASS"]],
    "cacheConfig" => [
        "ttl"    => 10,              // seconds - how long to cache a verified token result
        "prefix" => "myapp:zeroad:"  // APCu key prefix - namespace it per app
    ]
]);
```

`$site` exposes three public properties set at construction time:

| Property | Type | Value |
|---|---|---|
| `$site->SERVER_HEADER_NAME` | `string` | `"X-Better-Web-Welcome"` |
| `$site->SERVER_HEADER_VALUE` | `string` | encoded welcome string, e.g. `"abc123^1^3"` |
| `$site->CLIENT_HEADER_NAME` | `string` | `"HTTP_X_BETTER_WEB_HELLO"` - the `$_SERVER` key |

`$site->parseClientToken(?string $value): array` - verifies and maps a token to boolean flags.

### Step 2 - middleware (all frameworks follow same logic)

Two things must happen on every request:
1. Set `X-Better-Web-Welcome` on the **response** (tells the extension this site participates).
2. Parse `X-Better-Web-Hello` from `$_SERVER` and pass the resulting `$tokenContext` to handlers.

#### Plain PHP

```php
function tokenMiddleware(callable $handler): void
{
    global $site;
    header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");
    $tokenContext = $site->parseClientToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);
    $handler($tokenContext);
}

// Usage:
tokenMiddleware(function ($tokenContext) {
    // render page using $tokenContext
});
```

#### Laravel - middleware class

```php
namespace App\Http\Middleware;

use Closure;
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

class ZeroAdNetwork
{
    private Site $site;

    public function __construct()
    {
        $this->site = new Site([
            "clientId" => config("zeroad.client_id"),
            "features" => [Constants::FEATURE["CLEAN_WEB"], Constants::FEATURE["ONE_PASS"]],
            "cacheConfig" => ["ttl" => 10]
        ]);
    }

    public function handle($request, Closure $next)
    {
        header("{$this->site->SERVER_HEADER_NAME}: {$this->site->SERVER_HEADER_VALUE}");

        $tokenContext = $this->site->parseClientToken(
            $_SERVER[$this->site->CLIENT_HEADER_NAME] ?? null
        );

        $request->attributes->set("tokenContext", $tokenContext);

        return $next($request);
    }
}

// In a controller:
public function index(Request $request)
{
    $tokenContext = $request->attributes->get("tokenContext");
    return view("index", ["tokenContext" => $tokenContext]);
}
```

#### Symfony - event listener

```php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

class ZeroAdNetworkListener
{
    private Site $site;

    public function __construct(string $clientId)
    {
        $this->site = new Site([
            "clientId" => $clientId,
            "features" => [Constants::FEATURE["CLEAN_WEB"]],
            "cacheConfig" => ["ttl" => 10]
        ]);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $tokenContext = $this->site->parseClientToken(
            $_SERVER[$this->site->CLIENT_HEADER_NAME] ?? null
        );
        $event->getRequest()->attributes->set("tokenContext", $tokenContext);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $event->getResponse()->headers->set(
            $this->site->SERVER_HEADER_NAME,
            $this->site->SERVER_HEADER_VALUE
        );
    }
}
```

#### WordPress

```php
require_once __DIR__ . "/vendor/autoload.php";

use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

$GLOBALS["zeroad_site"] = new Site([
    "clientId" => get_option("zeroad_client_id"),
    "features" => [Constants::FEATURE["CLEAN_WEB"], Constants::FEATURE["ONE_PASS"]],
    "cacheConfig" => ["ttl" => 10]
]);

add_action("send_headers", function () {
    $site = $GLOBALS["zeroad_site"];
    header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");
});

add_action("init", function () {
    $site = $GLOBALS["zeroad_site"];
    $GLOBALS["zeroad_context"] = $site->parseClientToken(
        $_SERVER[$site->CLIENT_HEADER_NAME] ?? null
    );
});
```

### Step 3 - use $tokenContext in templates and handlers

`parseClientToken()` always returns an array - never throws. All flags are `false` when the visitor is not a subscriber or has an invalid/expired token.

```php
// Shape of $tokenContext:
[
    // Enabled when subscriber has CLEAN_WEB AND site declared Constants::FEATURE["CLEAN_WEB"]
    "HIDE_ADVERTISEMENTS"           => bool,
    "HIDE_COOKIE_CONSENT_SCREEN"    => bool,
    "HIDE_MARKETING_DIALOGS"        => bool,
    "DISABLE_NON_FUNCTIONAL_TRACKING" => bool,

    // Enabled when subscriber has ONE_PASS AND site declared Constants::FEATURE["ONE_PASS"]
    "DISABLE_CONTENT_PAYWALL"       => bool,
    "ENABLE_SUBSCRIPTION_ACCESS"    => bool,
]
```

**Critical rule:** a flag is `true` only when BOTH conditions hold:
- the subscriber's signed token grants that feature
- the site instance was created with the matching `Constants::FEATURE` value

**Usage pattern - guard an API endpoint:**

```php
if (!$tokenContext["ENABLE_SUBSCRIPTION_ACCESS"]) {
    http_response_code(403);
    echo json_encode(["error" => "Subscription required"]);
    exit;
}
```

**Usage pattern - PHP template:**

```php
<?php if (!$tokenContext["HIDE_ADVERTISEMENTS"]): ?>
    <div class="ad-banner"><!-- ad code --></div>
<?php endif; ?>

<?php if (!$tokenContext["HIDE_COOKIE_CONSENT_SCREEN"]): ?>
    <div class="cookie-banner"><!-- cookie notice --></div>
<?php endif; ?>

<?php if (!$tokenContext["HIDE_MARKETING_DIALOGS"]): ?>
    <div class="newsletter-popup"><!-- newsletter --></div>
<?php endif; ?>

<?php if (!$tokenContext["DISABLE_NON_FUNCTIONAL_TRACKING"]): ?>
    <script>/* analytics code */</script>
<?php endif; ?>

<?php if ($tokenContext["DISABLE_CONTENT_PAYWALL"]): ?>
    <div><?= $article["fullContent"] ?></div>
<?php else: ?>
    <div><?= $article["preview"] ?></div>
    <a href="/subscribe">Subscribe to read more</a>
<?php endif; ?>
```

---

## Choosing features

| Feature key | Constant | What it means for your site |
|---|---|---|
| `"CLEAN_WEB"` | `Constants::FEATURE["CLEAN_WEB"]` (= 1) | You will hide ads, cookie consent, marketing dialogs, and disable non-functional tracking |
| `"ONE_PASS"` | `Constants::FEATURE["ONE_PASS"]` (= 2) | You will lift paywalls and grant base subscription access |

Pass one or both. Only declare features you actually implement - non-compliance results in platform ban.

### Compliance checklist - you MUST do ALL of these for each declared feature

**CLEAN_WEB - all four required:**
- [ ] Disable all advertisements (banners, interstitials, native ads, etc.)
- [ ] Disable all cookie consent screens (headers, footers, dialogs)
- [ ] Fully opt out users from non-functional trackers (analytics, ad pixels)
- [ ] Disable all marketing dialogs and popups (newsletters, promotions)

**ONE_PASS - both required:**
- [ ] Provide free access to all content behind a paywall
- [ ] Provide free access to the site's base subscription plan (if one exists)

---

## Cache configuration

Caching requires `ext-apcu`. Without it, every request runs a full ED25519 signature verification (~150 us). With it, cached requests take ~15 us (10-20x faster).

```php
// Configured via Site constructor cacheConfig key:
$site = new Site([
    "clientId"    => "...",
    "features"    => [...],
    "cacheConfig" => [
        "ttl"    => 10,              // seconds (default: 5)
        "prefix" => "myapp:zeroad:"  // APCu key prefix (default: "zeroad:token:")
    ]
]);
```

If APCu is not loaded or not enabled, a warning is logged and caching is silently disabled - the code still works, just slower.

Cache entries expire at `min(configTtl, tokenExpiresAt)` so they never outlive the token itself.

Recommended TTL by traffic:

| Traffic | TTL | Reason |
|---|---|---|
| > 1000 req/s | 10-30s | Maximize cache hits |
| 100-1000 req/s | 5-10s | Balance freshness and performance |
| < 100 req/s | 2-5s | Keep data fresh |

---

## Logging

```php
use ZeroAd\Token\Logger;

// Verbosity levels: "error" | "warn" | "info" | "debug"
Logger::setLogLevel("debug");  // enable during development

// Custom handler (route to your logger):
Logger::setLogHandler(function (string $level, string $message): void {
    error_log("[$level] $message");
});

// Silence all logs:
Logger::setLogHandler(null);
```

Warnings are emitted when a token fails verification - useful for spotting malformed headers or missing APCu.

---

## Decoding headers (inspection / testing)

These are lower-level static methods. Only use them when you need to inspect raw header values outside of the `Site` flow.

```php
use ZeroAd\Token\Headers\ServerHeader;
use ZeroAd\Token\Headers\ClientHeader;
use ZeroAd\Token\Constants;

// Decode a Welcome header (e.g. read from a third-party site):
$welcome = ServerHeader::decodeServerHeader("Z2CclA8oXIT1e0QmqTWF8w^1^3");
// [
//   "clientId" => "Z2CclA8oXIT1e0QmqTWF8w",
//   "version"  => 1,
//   "features" => ["CLEAN_WEB", "ONE_PASS"]
// ]
// Returns null for malformed values (logged as warning).

// Decode + verify a client token:
$decoded = ClientHeader::decodeClientHeader($rawValue, Constants::ZEROAD_NETWORK_PUBLIC_KEY);
// [
//   "version"   => int,
//   "expiresAt" => DateTime,
//   "flags"     => int,       // bitmask
//   "clientId"  => string|null  // only present in developer tokens
// ]
// Returns null when signature invalid or format wrong (logged as warning).
```

---

## Complete API reference

### Site class (`ZeroAd\Token\Site`)

```php
// Constructor - throws \InvalidArgumentException on invalid params
public function __construct(array $params)
// $params keys:
//   "clientId"    (string, required)
//   "features"    (array, required) - values from Constants::FEATURE
//   "cacheConfig" (array, optional) - keys: "ttl" (int seconds), "prefix" (string)

// Public properties (read-only after construction):
public string $SERVER_HEADER_NAME   // "X-Better-Web-Welcome"
public string $SERVER_HEADER_VALUE  // encoded welcome string
public string $CLIENT_HEADER_NAME   // "HTTP_X_BETTER_WEB_HELLO" ($_SERVER key)

// Parse and verify a token - always returns array, never throws
public function parseClientToken(?string $headerValue): array
```

### Constants class (`ZeroAd\Token\Constants`)

```php
Constants::FEATURE["CLEAN_WEB"]  // int 1
Constants::FEATURE["ONE_PASS"]   // int 2
Constants::SERVER_HEADER["WELCOME"]  // "X-Better-Web-Welcome"
Constants::CLIENT_HEADER["HELLO"]    // "X-Better-Web-Hello"
Constants::ZEROAD_NETWORK_PUBLIC_KEY // base64 Ed25519 public key string
```

### Logger class (`ZeroAd\Token\Logger`)

```php
Logger::setLogLevel(string $level): void   // "error"|"warn"|"info"|"debug"
Logger::setLogHandler(?callable $handler): void  // fn(string $level, string $message): void
```

### ServerHeader class (`ZeroAd\Token\Headers\ServerHeader`)

```php
ServerHeader::decodeServerHeader(?string $headerValue): ?array
// Returns: ["clientId" => string, "version" => int, "features" => string[]] or null
```

### ClientHeader class (`ZeroAd\Token\Headers\ClientHeader`)

```php
ClientHeader::parseClientToken(?string $headerValue, array $options): array
// $options: ["clientId" => string, "features" => int[], "publicKey" => string (optional)]

ClientHeader::decodeClientHeader(?string $headerValue, string $publicKey): ?array
// Returns: ["version" => int, "expiresAt" => DateTime, "flags" => int, "clientId" => ?string] or null

ClientHeader::configureCaching(array $config): void
// $config: ["ttl" => int, "prefix" => string]

ClientHeader::emptyContext(): array
// Returns the all-false context array
```

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| All `$tokenContext` flags are `false` | Token missing, expired, or invalid | Enable `Logger::setLogLevel("debug")` and check for warnings |
| All flags `false` for a known subscriber | Site `features` array doesn't include the subscriber's plan feature | Add the relevant `Constants::FEATURE` value to the `features` array in the constructor |
| `parseClientToken` slow | APCu not installed or disabled | Install `ext-apcu` and enable it in `php.ini`; pass `cacheConfig` |
| APCu warning in logs | APCu not loaded when `cacheConfig` is passed | Install `ext-apcu` or remove `cacheConfig` (cache silently disabled) |
| Welcome header not reaching extension | Header sent after output started | Call `header()` before any output; check for BOM or whitespace before `<?php` |
| Token silently rejected | Header value exceeds 500 bytes | Tokens from the extension are always within limits; check for proxy/WAF truncation |

**Enable debug logging to trace a single request:**

```php
use ZeroAd\Token\Logger;

Logger::setLogLevel("debug");
Logger::setLogHandler(function (string $level, string $message): void {
    error_log("ZeroAd [$level]: $message");
});

$raw = $_SERVER[$site->CLIENT_HEADER_NAME] ?? null;
error_log("raw token header: " . ($raw ?? "(missing)"));

$ctx = $site->parseClientToken($raw);
error_log("token context: " . json_encode($ctx));
```

---

## What to avoid

- Do NOT call `new Site(...)` on every request - create once at startup (e.g. top of a bootstrap file or a service container singleton).
- Do NOT access `$_SERVER["X-Better-Web-Hello"]` directly - PHP normalizes headers to `HTTP_X_BETTER_WEB_HELLO`; use `$site->CLIENT_HEADER_NAME` to get the correct key.
- Do NOT ignore the compliance checklist - partial feature implementation causes platform ban.
- Do NOT call `ClientHeader::decodeClientHeader()` directly when `$site->parseClientToken()` covers the use case - it skips caching and context building.
- Do NOT skip setting the `X-Better-Web-Welcome` response header - without it the extension never sends tokens.
- Do NOT pass `cacheConfig` without `ext-apcu` installed - caching will be silently disabled and a warning logged, but make sure APCu is actually available in production.
