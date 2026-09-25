# PHP Composer Example

This demo shows how to integrate the `zeroad.network/token` module with PHP: verifying a signed,
hostname-bound subscriber token and rendering the page accordingly.

## Features

- ✅ **Two Ed25519 signatures** - a batch credential from the platform, bound to this site's hostname
- ✅ **Instance result cache** - repeated checks on the same instance can reuse a verdict; cross-request PHP-FPM caching requires APCu
- ✅ **Conditional rendering** - ads, paywalls, cookie dialogs and marketing modals, all gated on one flag
- ✅ **Middleware pattern** - clean separation of header/verification and routing
- ✅ **Multiple routes** - homepage, JSON API endpoint

## Quick Start

### 1. Install Dependencies

The example requires the `Publisher` API. Its Composer manifest currently requests `^1.0`; confirm a
compatible release is available or configure a local Composer path repository for this SDK checkout.
The older `Site` API cannot run this example.

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

**Without a Zero Ad Network subscription** (no extension, or not a subscriber):

- Advertisement banners
- Cookie consent dialog
- Marketing popup
- Analytics tracking simulated
- Paywalled content (preview only)
- Subscription overlays

**With a subscription** (the extension attaches a valid token bound to this host):

- Clean, ad-free experience
- No cookie consent prompt
- No marketing interruptions
- Full access to paywalled content
- No simulated third-party tracking scripts

## Testing access

The public demo token is restricted to the official demo hostname and does not work on localhost.
For your own integration, use your account’s Publisher ID, register and verify the hostname in your
dashboard, then choose **Test in your browser**. Reload after test access reaches the extension.
Publisher testing needs no paid subscription and earns nothing.

The example allows `localhost` and `127.0.0.1`. Production extensions inject tokens only over HTTPS;
HTTP localhost testing requires the development extension and matching local platform configuration.
For a hosted test, update the example’s allowlist to your HTTPS hostname. Tokens are bound to the exact
hostname, so `localhost` and `127.0.0.1` are not interchangeable.

## How It Works

### Publisher initialization

```php
use ZeroAd\Token\Publisher;

$publisher = Publisher::create([
    "publisherId" => $_ENV["ZERO_AD_PUBLISHER_ID"],
    "hostnames"   => ["localhost", "127.0.0.1"],
]);
```

### Middleware pattern

```php
function tokenMiddleware(callable $handler): void
{
    global $publisher;

    // Announce participation on every response
    header("{$publisher->headerName}: {$publisher->headerValue}");

    // Verify the token (validates two signatures, checks expiry and the hostname binding)
    $visitor = $publisher->verify(
        $_SERVER[$publisher->tokenHeaderServerKey] ?? null,
        $_SERVER["HTTP_HOST"] ?? null
    );

    $handler($visitor);
}
```

### Template usage

This demo includes all its sample paid content with Freedom, so a single `subscriber` flag drives the page. A real site must also check whether the requested content belongs to its base subscription or custom included access level:

```php
<?php if (!$isSubscriber): ?>
    <div class="ad-banner">Advertisement</div>
<?php endif; ?>

<?php if ($isSubscriber): ?>
    <article>Premium content</article>
<?php else: ?>
    <div class="paywall">Subscribe to read</div>
<?php endif; ?>
```

### The verification result

`$publisher->verify()` returns a `VerificationResult` for missing or invalid tokens. With multiple
configured hostnames, omitting the hostname throws a configuration error:

```php
$visitor->subscriber; // bool - the one flag the page branches on
$visitor->plan;       // int|null  - Constants::PLAN["FREEDOM"] for a subscriber
$visitor->planName;   // string|null - "Freedom"
$visitor->expiresAt;  // \DateTimeImmutable|null
$visitor->reason;     // string|null - a Rejection::* constant when not a subscriber
$visitor->hostname;   // string - what it was verified against
$visitor->cached;     // bool - whether cryptography was skipped

$visitor->toArray();  // JSON-friendly copy (see the /token route)
```

`subscriber` is `false` for visitors without the extension, expired tokens, forged tokens, and tokens
bound to a different site.

## Routes

- `GET /` - Homepage with conditional ads and features
- `GET /token` - JSON endpoint showing the parsed verification result

## Learn More

- **Documentation**: [https://zeroad.network/docs](https://zeroad.network/docs)
- **Get your Publisher ID**: [https://zeroad.network](https://zeroad.network)
- **Contact**: [hello@zeroad.network](mailto:hello@zeroad.network)
