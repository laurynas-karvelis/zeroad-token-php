# PHP Composer Example

This demo shows how to integrate the `zeroad.network/token` module with PHP: verifying a signed,
origin-bound subscriber token and rendering the page accordingly.

## Features

- ✅ **Two Ed25519 signatures** - a batch credential from the platform, bound to this site's hostname
- ✅ **Per-process result cache** - a returning visitor's token is a map lookup, not fresh cryptography
- ✅ **Conditional rendering** - ads, paywalls, cookie dialogs and marketing modals, all gated on one flag
- ✅ **Middleware pattern** - clean separation of header/verification and routing
- ✅ **Multiple routes** - homepage, JSON API endpoint

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
- No tracking

## Testing with the Demo Token

To test without purchasing a subscription:

1. **Get the browser extension** - Chrome, Firefox, or Edge.
2. **Get a demo token** from the Zero Ad Network developer page - it syncs to your extension
   automatically and is valid for the **Freedom** plan.
3. **Reload the page** - you'll see the full clean experience.

> The demo lists `localhost` and `127.0.0.1` as its hostnames, so a demo token bound to `localhost`
> verifies here. In production, list the exact hosts you serve.

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

The Freedom plan grants everything, so a single `subscriber` flag drives the whole page:

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

`$publisher->verify()` always returns a `VerificationResult`, never throws on bad input:

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

- **Documentation**: [https://docs.zeroad.network](https://docs.zeroad.network)
- **Register your site**: [https://zeroad.network](https://zeroad.network)
- **Contact**: [hello@zeroad.network](mailto:hello@zeroad.network)
