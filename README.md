# zeroad.network/token (PHP)

The official PHP module for integrating websites with the [Zero Ad Network](https://zeroad.network) platform.

## What is Zero Ad Network?

Zero Ad Network is a browser-based platform that creates a better web experience for both users and content creators:

**For Users:**

- Browse without ads, trackers, cookie consent dialogs, and marketing pop-ups
- Access paywalled content across multiple sites with a single subscription
- Support content creators directly through fair revenue distribution

**For Publishers:**

- Generate revenue from users who would otherwise use ad blockers
- Provide a cleaner user experience while maintaining income
- Get paid based on actual user engagement with your content

**How It Works:**

1. Users subscribe and install the Zero Ad Network browser extension
2. The extension sends cryptographically signed tokens to partner sites
3. Partner sites verify tokens and enable premium features (ad-free, paywall-free)
4. Monthly revenue is distributed to publishers based on user engagement time

## Features

This module provides:

- ✅ **Zero dependencies** - Lightweight and secure (only requires ext-sodium)
- ✅ **Cryptographic verification** - ED25519 signature validation using ext-sodium
- ✅ **Performance optimized** - Built-in APCu caching for 10-20x performance boost
- ✅ **PHP 7.2+ compatible** - Works with legacy and modern PHP versions
- ✅ **Easy integration** - Simple API with minimal configuration

## Runtime Compatibility

| Runtime | Version | Ready |
| :------ | :------ | :---: |
| PHP 7   | 7.2+    |  ✅   |
| PHP 8   | 8.0+    |  ✅   |

**Required:**

- `ext-sodium` (included by default in PHP 7.2+)

**Recommended:**

- `ext-apcu` (for caching - provides 10-20x performance improvement)

## Installation

```bash
composer require zeroad.network/token
```

### Optional: Install APCu for Caching

**Highly recommended** for production environments:

```bash
# Debian/Ubuntu
sudo apt-get install php-apcu

# CentOS/RHEL
sudo yum install php-pecl-apcu

# Enable in php.ini
extension=apcu.so
apc.enabled=1
apc.shm_size=32M
```

## Quick Start

### 1. Register Your Site

Before implementing, you need to:

1. [Sign up](https://zeroad.network/login) for a Zero Ad Network account
2. [Register your site](https://zeroad.network/publisher/sites/add) to receive your unique `Client ID`

### 2. Choose Your Features

Decide which features your site will support:

- **`Constants::FEATURE['CLEAN_WEB']`**: Remove ads, cookie consent screens, trackers, and marketing dialogs
- **`Constants::FEATURE['ONE_PASS']`**: Provide free access to paywalled content and base subscription plans

### 3. Basic Implementation

```php
<?php

require_once __DIR__ . "/vendor/autoload.php";

use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

// Initialize once at startup
$site = new Site([
  "clientId" => "YOUR_CLIENT_ID_HERE",
  "features" => [Constants::FEATURE["CLEAN_WEB"], Constants::FEATURE["ONE_PASS"]]
]);

// In your middleware/controller
header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

// Parse the user's subscription token
$tokenContext = $site->parseClientToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

// Use token context in templates
render("index", ["tokenContext" => $tokenContext]);
```

### 4. In Your Templates

```php
<!-- index.php -->
<!DOCTYPE html>
<html>
<head>
  <title>My Site</title>
</head>
<body>
  <h1>Welcome to My Site</h1>
  
  <!-- Only show ads to non-subscribers -->
  <?php if (!$tokenContext["HIDE_ADVERTISEMENTS"]): ?>
    <div class="advertisement">
      <!-- Ad code here -->
    </div>
  <?php endif; ?>
  
  <!-- Only show cookie consent to non-subscribers -->
  <?php if (!$tokenContext["HIDE_COOKIE_CONSENT_SCREEN"]): ?>
    <div class="cookie-consent">
      <!-- Cookie consent dialog -->
    </div>
  <?php endif; ?>
  
  <!-- Content -->
  <article>
    <h2>Article Title</h2>
    
    <!-- Show preview or full content based on subscription -->
    <?php if ($tokenContext["DISABLE_CONTENT_PAYWALL"]): ?>
      <p>Full article content for Zero Ad Network subscribers...</p>
    <?php else: ?>
      <p>Article preview... <a href="/subscribe">Subscribe to read more</a></p>
    <?php endif; ?>
  </article>
</body>
</html>
```

## Token Context

After parsing, the token context contains boolean flags for each feature:

```php
[
  // CLEAN_WEB features
  "HIDE_ADVERTISEMENTS" => bool,
  "HIDE_COOKIE_CONSENT_SCREEN" => bool,
  "HIDE_MARKETING_DIALOGS" => bool,
  "DISABLE_NON_FUNCTIONAL_TRACKING" => bool,

  // ONE_PASS features
  "DISABLE_CONTENT_PAYWALL" => bool,
  "ENABLE_SUBSCRIPTION_ACCESS" => bool
];
```

**Important:** All flags default to `false` for:

- Users without subscriptions
- Expired tokens
- Invalid/forged tokens
- Missing tokens

## Performance & Caching

### APCu Cache (Highly Recommended)

The module includes built-in APCu caching to dramatically improve performance by avoiding redundant cryptographic operations.

**Performance Impact:**

- **Without cache**: ~100-200μs per token verification
- **With APCu cache**: ~10-20μs per cached token (10-20x faster!)
- Cache is shared between all PHP-FPM workers
- Automatically respects token expiration

### Configuration

```php
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

$site = new Site([
  "clientId" => "YOUR_CLIENT_ID",
  "features" => [Constants::FEATURE["CLEAN_WEB"]],
  "cacheConfig" => [
    "ttl" => 10, // Cache for 10 seconds (default: 5)
    "prefix" => "myapp:zeroad:" // Custom cache key prefix
  ]
]);
```

**How Caching Works:**

1. Token header is hashed (xxHash) to create cache key
2. If cached and not expired, returns immediately (~15μs)
3. If cache miss, performs crypto verification (~150μs)
4. Result cached with TTL = min(config TTL, token expiry time)
5. Expired tokens automatically removed from cache

**Cache automatically respects token expiration** - even with long cache TTL, expired tokens are never served from cache.

### Without APCu

If APCu is not available, the module works normally but performs full crypto verification on every request. Consider:

- Enabling OPcache to cache compiled PHP code
- Using a reverse proxy cache (Varnish, nginx)
- Installing APCu for best performance

### Performance Benchmarks

Typical performance on modern hardware:

| Operation    | Without Cache  | With APCu Cache | Improvement      |
| ------------ | -------------- | --------------- | ---------------- |
| Parse token  | ~150μs         | ~15μs           | **10x faster**   |
| 1000 req/sec | 150ms blocking | 15ms blocking   | **90% less CPU** |

## Advanced Configuration

### Cache Configuration

```php
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

$site = new Site([
  "clientId" => $_ENV["ZERO_AD_CLIENT_ID"],
  "features" => [Constants::FEATURE["CLEAN_WEB"]],
  "cacheConfig" => [
    "ttl" => 10, // Cache for 10 seconds
    "prefix" => "myapp:zeroad:" // Namespace your cache keys
  ]
]);
```

**Recommended TTL Settings:**

| Scenario                          | TTL    | Reason                            |
| --------------------------------- | ------ | --------------------------------- |
| High traffic (1000+ req/sec)      | 10-30s | Maximize cache hits               |
| Normal traffic (100-1000 req/sec) | 5-10s  | Balance freshness and performance |
| Low traffic (<100 req/sec)        | 2-5s   | Keep data fresh                   |

### Logging

**Set Log Level:**

```php
use ZeroAd\Token\Logger;

// Development
Logger::setLogLevel("debug"); // error, warn, info, debug

// Production
Logger::setLogLevel("error");
```

**Custom Log Handler:**

```php
use ZeroAd\Token\Logger;

// Integrate with Monolog
$monolog = new Monolog\Logger("zeroad");
$monolog->pushHandler(new StreamHandler("/var/log/zeroad.log"));

Logger::setLogHandler(function ($level, $message) use ($monolog) {
  $monolog->log($level, $message);
});
```

```php
// Disable logging in production
Logger::setLogHandler(function ($level, $message) {
  // No-op: discard all logs
});
```

```php
// Send errors to monitoring service
Logger::setLogHandler(function ($level, $message) {
  if ($level === "error") {
    Sentry\captureMessage($message);
  }
});
```

## Security

### Token Verification

All tokens are cryptographically signed using ED25519 by Zero Ad Network:

- **Signature verification** happens locally on your server using Zero Ad Network's official public key
- **Trusted authority** - Only tokens signed by Zero Ad Network are valid
- **No external API calls** - verification is instant and offline
- **Tamper-proof** - modified tokens fail verification automatically (constant-time comparison)
- **Time-limited** - expired tokens are automatically rejected

### Token Structure

Each token contains:

1. **Protocol version** - Currently v1
2. **Nonce** - Random 4-byte value
3. **Expiration timestamp** - Unix timestamp
4. **Feature flags** - Bitmask of enabled features
5. **Client ID** (optional) - For developer tokens
6. **Cryptographic signature** - ED25519 signature

Example token:

```
X-Better-Web-Hello: Aav2IXRoh0oKBw==.2yZfC2/pM9DWfgX+von4IgWLmN9t67HJHLiee/gx4+pFIHHurwkC3PCHT1Kaz0yUhx3crUaxST+XLlRtJYacAQ==
```

### Privacy

Tokens contain **no personally identifiable information**:

- ❌ No user IDs
- ❌ No email addresses
- ❌ No tracking data
- ✅ Only: expiration date and feature flags

## Framework Examples

### Laravel

```php
// app/Http/Middleware/ZeroAdNetwork.php
<?php

namespace App\Http\Middleware;

use Closure;
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

class ZeroAdNetwork
{
    private $site;

    public function __construct()
    {
        $this->site = new Site([
            'clientId' => config('zeroad.client_id'),
            'features' => [Constants::FEATURE['CLEAN_WEB'], Constants::FEATURE['ONE_PASS']],
            'cacheConfig' => ['ttl' => 10]
        ]);
    }

    public function handle($request, Closure $next)
    {
        // Set Welcome Header
        header("{$this->site->SERVER_HEADER_NAME}: {$this->site->SERVER_HEADER_VALUE}");

        // Parse token
        $tokenContext = $this->site->parseClientToken(
            $_SERVER[$this->site->CLIENT_HEADER_NAME] ?? null
        );

        // Add to request
        $request->attributes->set('tokenContext', $tokenContext);

        return $next($request);
    }
}

// In your controller
public function index(Request $request)
{
    $tokenContext = $request->attributes->get('tokenContext');
    return view('index', ['tokenContext' => $tokenContext]);
}
```

### Symfony

```php
// src/EventListener/ZeroAdNetworkListener.php
<?php namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

class ZeroAdNetworkListener
{
  private $site;

  public function __construct(string $clientId)
  {
    $this->site = new Site([
      "clientId" => $clientId,
      "features" => [Constants::FEATURE["CLEAN_WEB"]],
      "cacheConfig" => ["ttl" => 10]
    ]);
  }

  public function onKernelRequest(RequestEvent $event)
  {
    $request = $event->getRequest();

    $tokenContext = $this->site->parseClientToken($_SERVER[$this->site->CLIENT_HEADER_NAME] ?? null);

    $request->attributes->set("tokenContext", $tokenContext);
  }

  public function onKernelResponse(ResponseEvent $event)
  {
    $response = $event->getResponse();
    $response->headers->set($this->site->SERVER_HEADER_NAME, $this->site->SERVER_HEADER_VALUE);
  }
}
```

### WordPress

```php
// wp-content/themes/your-theme/functions.php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

// Initialize once
$GLOBALS['zeroad_site'] = new Site([
    'clientId' => get_option('zeroad_client_id'),
    'features' => [Constants::FEATURE['CLEAN_WEB'], Constants::FEATURE['ONE_PASS']],
    'cacheConfig' => ['ttl' => 10]
]);

// Add Welcome Header
add_action('send_headers', function() {
    $site = $GLOBALS['zeroad_site'];
    header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");
});

// Parse token and make available globally
add_action('init', function() {
    $site = $GLOBALS['zeroad_site'];
    $GLOBALS['zeroad_context'] = $site->parseClientToken(
        $_SERVER[$site->CLIENT_HEADER_NAME] ?? null
    );
});

// Use in templates
function zeroad_context() {
    return $GLOBALS['zeroad_context'] ?? [];
}

// In your template files
<?php if (!zeroad_context()['HIDE_ADVERTISEMENTS']): ?>
    <!-- Show ads -->
<?php endif; ?>
```

## Complete Usage Example

```php
<?php

require_once __DIR__ . "/vendor/autoload.php";

use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Logger;

// Configure logging
Logger::setLogLevel("error");

// Initialize site instance
$site = new Site([
  "clientId" => $_ENV["ZERO_AD_CLIENT_ID"] ?? "DEMO-Z2CclA8oXIT1e0Qmq",
  "features" => [Constants::FEATURE["CLEAN_WEB"], Constants::FEATURE["ONE_PASS"]],
  "cacheConfig" => [
    "ttl" => 10,
    "prefix" => "myapp:zeroad:"
  ]
]);

// Middleware function
function tokenMiddleware($site, callable $handler)
{
  // Set Welcome Header
  header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

  // Parse token
  $tokenContext = $site->parseClientToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

  // Call handler with context
  $handler($tokenContext);
}

// Routes
$uri = $_SERVER["REQUEST_URI"];

if ($uri === "/") {
  tokenMiddleware($site, function ($tokenContext) {
    require __DIR__ . "/views/index.php";
  });
} elseif ($uri === "/article/1") {
  tokenMiddleware($site, function ($tokenContext) {
    $article = [
      "title" => "The Future of Web Publishing",
      "preview" => "In recent years, the landscape has changed...",
      "fullContent" => "Full article content here..."
    ];
    require __DIR__ . "/views/article.php";
  });
} elseif ($uri === "/api/premium") {
  tokenMiddleware($site, function ($tokenContext) {
    header("Content-Type: application/json");

    if (!$tokenContext["ENABLE_SUBSCRIPTION_ACCESS"]) {
      http_response_code(403);
      echo json_encode(["error" => "Premium subscription required"]);
      return;
    }

    echo json_encode(["data" => "Premium content"]);
  });
} else {
  http_response_code(404);
  echo "Not Found";
}
```

## Implementation Requirements

When implementing Zero Ad Network features, you **must** fulfill these requirements to remain in good standing:

### CLEAN_WEB Requirements

- ✅ Disable **all** advertisements on the page
- ✅ Disable **all** cookie consent screens (headers, footers, dialogs)
- ✅ Fully opt out users from **non-functional** trackers
- ✅ Disable **all** marketing dialogs or popups (newsletters, promotions)

### ONE_PASS Requirements

- ✅ Provide free access to content behind paywalls
- ✅ Provide free access to your base subscription plan (if applicable)

**⚠️ Failure to comply will result in removal from the Zero Ad Network platform.**

## Troubleshooting

### Tokens Not Working

```php
use ZeroAd\Token\Logger;

// Enable debug logging
Logger::setLogLevel("debug");

// Check if token is being received
$headerValue = $_SERVER[$site->CLIENT_HEADER_NAME] ?? null;
error_log("Header value: " . ($headerValue ?? "NULL"));

// Verify token context
$context = $site->parseClientToken($headerValue);
error_log("Token context: " . json_encode($context));
```

### Check APCu Status

```php
if (!extension_loaded("apcu") || !apcu_enabled()) {
  error_log("WARNING: APCu not available - caching disabled");
} else {
  error_log("APCu enabled - caching active");
}
```

### Common Issues

1. **All flags are false** - Token is expired, invalid, or missing
2. **Performance slow** - Install APCu for 10-20x speedup
3. **Token rejected** - Verify Client ID matches registered site
4. **Headers not sent** - Ensure header() is called before any output

## API Reference

### `Site`

Creates a site instance with helper methods.

```php
$site = new Site([
    'clientId' => 'YOUR_CLIENT_ID',          // Required: Your Zero Ad Network client ID
    'features' => [Constants::FEATURE[...]],  // Required: Array of feature flags
    'cacheConfig' => [                        // Optional: Cache configuration
        'ttl' => 5,                           // Cache TTL in seconds
        'prefix' => 'zeroad:token:'           // Cache key prefix
    ]
]);

// Properties
$site->CLIENT_HEADER_NAME;    // Request header name (e.g., "HTTP_X_BETTER_WEB_HELLO")
$site->SERVER_HEADER_NAME;    // Response header name ("X-Better-Web-Welcome")
$site->SERVER_HEADER_VALUE;   // Response header value (encoded)

// Methods
$context = $site->parseClientToken(?string $headerValue): array
```

### `Constants`

```php
use ZeroAd\Token\Constants;

// Features
Constants::FEATURE['CLEAN_WEB']  // = 1
Constants::FEATURE['ONE_PASS']   // = 2

// Headers
Constants::SERVER_HEADER['WELCOME']  // = "X-Better-Web-Welcome"
Constants::CLIENT_HEADER['HELLO']    // = "X-Better-Web-Hello"

// Protocol
Constants::CURRENT_PROTOCOL_VERSION  // = 1
```

### `Logger`

```php
use ZeroAd\Token\Logger;

Logger::setLogLevel(string $level): void
// Set minimum log level: 'error', 'warn', 'info', 'debug'

Logger::setLogHandler(?callable $handler): void
// Set custom log handler: function(string $level, string $message): void

Logger::log(string $level, ...$args): void
// Log a message
```

## Resources

- 📖 [Official Documentation](https://docs.zeroad.network)
- 🌐 [Zero Ad Network Platform](https://zeroad.network)
- 💻 [Example Implementations](https://github.com/laurynas-karvelis/zeroad-token-php/tree/main/examples/)
- 📝 [Blog](https://docs.zeroad.network/blog)

## License

Apache License 2.0 - see LICENSE file for details

## About Zero Ad Network

Zero Ad Network is building a fairer internet where:

- Users enjoy cleaner, faster browsing
- Publishers earn sustainable revenue
- Privacy is respected by default

Join thousands of publishers creating a better web experience.

[Get Started →](https://zeroad.network/login)
