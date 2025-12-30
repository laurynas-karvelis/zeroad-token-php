# PHP Composer Example

This demo shows how to integrate the `zeroad.network/token` module with PHP using cryptographic token validation and conditional rendering.

## Features

- ✅ **Ed25519 signature verification** - Secure token validation using libsodium
- ✅ **APCu token caching** - Optional performance boost (10x faster validation)
- ✅ **Conditional rendering** - Ads, paywalls, and tracking based on subscription status
- ✅ **Middleware pattern** - Clean separation of token parsing and routing
- ✅ **Multiple routes** - Homepage, JSON API endpoint

## Quick Start

### 1. Install Dependencies

```shell
composer install
```

### 2. Start the Server

```shell
composer start
```

### 3. Open in Browser

- **Homepage**: [http://localhost:8080](http://localhost:8080)
- **Token API**: [http://localhost:8080/token](http://localhost:8080/token) (JSON output)

## What You'll See

**Without Zero Ad Network subscription:**

- Advertisement banners
- Cookie consent dialogs
- Marketing popups
- Analytics tracking enabled
- Paywalled content (preview only)
- Subscription overlays

**With Zero Ad Network subscription:**

- Clean, ad-free experience
- No cookie consent prompts
- No marketing interruptions
- Full access to paywalled content
- Privacy-protected browsing (no tracking)

## Testing with Demo Token

To test without purchasing a subscription:

1. **Get the Browser Extension**
   - Click "Get browser extension" in the navigation
   - Install for Chrome, Firefox, or Edge

2. **Get Demo Token**
   - Click "Get demo token" after installing
   - This opens the Zero Ad Network developer page
   - Demo token syncs automatically to your extension
   - Valid for 7 days (revisit to renew)

3. **Reload the Page**
   - The demo uses the **Freedom** plan (all features enabled)
   - You'll see the full ad-free, paywall-free experience

## How It Works

### Site Initialization

```php
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

$site = new Site([
  "clientId" => "DEMO-Z2CclA8oXIT1e0Qmq",
  "features" => [Constants::FEATURE["CLEAN_WEB"], Constants::FEATURE["ONE_PASS"]]
]);
```

### Middleware Pattern

```php
function tokenMiddleware(callable $handler): void
{
  global $site;

  // Set Welcome Header
  header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

  // Parse token (validates signature, checks expiration)
  $tokenContext = $site->parseClientToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

  // Pass context to handler
  $handler($tokenContext);
}
```

### Routing

```php
$uri = $_SERVER["REQUEST_URI"];

if ($uri === "/") {
  tokenMiddleware(function ($tokenContext) {
    echo render("homepage", ["tokenContext" => $tokenContext]);
  });
}
```

### Template Usage

```php
<?php if (!$tokenContext["HIDE_ADVERTISEMENTS"]): ?>
    <div class="ad-banner">Advertisement</div>
<?php endif; ?>

<?php if ($tokenContext["DISABLE_CONTENT_PAYWALL"]): ?>
    <article>Premium Content</article>
<?php else: ?>
    <div class="paywall">Subscribe to read</div>
<?php endif; ?>
```

## Token Context

The `tokenContext` array contains these boolean flags:

```php
[
  "HIDE_ADVERTISEMENTS" => bool, // Hide all ads
  "HIDE_COOKIE_CONSENT_SCREEN" => bool, // Hide cookie dialogs
  "HIDE_MARKETING_DIALOGS" => bool, // Hide popups/newsletters
  "DISABLE_NON_FUNCTIONAL_TRACKING" => bool, // Opt out of analytics
  "DISABLE_CONTENT_PAYWALL" => bool, // Remove article paywalls
  "ENABLE_SUBSCRIPTION_ACCESS" => bool // Grant premium features
];
```

All flags are `false` for:

- Users without subscriptions
- Expired tokens
- Invalid/forged tokens
- Client ID mismatch (developer tokens)

## Performance & Caching

### Without APCu

- **~2ms** per token validation (Ed25519 signature verification)
- Crypto operations happen on every request
- Suitable for low-traffic sites (<100 req/sec)

### With APCu (Recommended)

Enable APCu caching for 10x performance improvement:

```php
$site = new Site([
  "clientId" => "YOUR_CLIENT_ID",
  "features" => [Constants::FEATURE["CLEAN_WEB"], Constants::FEATURE["ONE_PASS"]],
  "cacheConfig" => [
    "ttl" => 5, // Cache for 5 seconds
    "prefix" => "zeroad:" // Cache key prefix
  ]
]);
```

**Performance with APCu:**

- **~0.2ms** per cached token (cache hit)
- **~2ms** for first validation (cache miss)
- Shared across PHP-FPM workers
- Respects token expiration
- Suitable for high-traffic sites (1000+ req/sec)

**Install APCu:**

```shell
# Ubuntu/Debian
sudo apt-get install php-apcu

# Via PECL
pecl install apcu
```

**Verify APCu is enabled:**

```shell
php -m | grep apcu
```

## Routes

- `GET /` - Homepage with conditional ads and features
- `GET /token` - JSON API endpoint showing parsed token context

## Learn More

- **Documentation**: [https://docs.zeroad.network](https://docs.zeroad.network)
- **Integration Guide**: [https://docs.zeroad.network/site-integration](https://docs.zeroad.network/site-integration)
- **Register Your Site**: [https://zeroad.network](https://zeroad.network)
- **Contact**: [hello@zeroad.network](mailto:hello@zeroad.network)
