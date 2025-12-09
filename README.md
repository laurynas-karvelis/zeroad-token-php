# Introduction

The PHP Composer module is designed for sites running PHP that participate in the [Zero Ad Network](https://zeroad.network) program.

The `zeroad.network/token` module is a lightweight, open-source, fully tested HTTP-header-based "access/entitlement token" library with no external production dependencies.

For detailed guides and implementation instructions, see the [official Zero Ad Network documentation](https://docs.zeroad.network).

## Runtime Compatibility

| Runtime | Version | Ready |
| :------ | :------ | :---: |
| PHP 7   | 7.2+    |  ✅   |
| PHP 8   | 8.0+    |  ✅   |

**Note:** `ext-sodium` must be installed on your PHP runtime.

## Purpose

The module helps developers to:

- Generate a valid "Welcome Header" when `clientId` and `features` are provided.
- Inject a valid site's HTTP Response Header (**Welcome Header**) into every endpoint. Example:

  ```http
  X-Better-Web-Welcome: "Z2CclA8oXIT1e0QmqTWF8w^1^3"
  ```

- Detect and parse Zero Ad Network user tokens sent via HTTP Request Header. Example:

  ```http
  X-Better-Web-Hello: "Aav2IXRoh0oKBw==.2yZfC2/pM9DWfgX+von4IgWLmN9t67HJHLiee/gx4+pFIHHurwkC3PCHT1Kaz0yUhx3crUaxST+XLlRtJYacAQ=="
  ```

- Verify client token integrity locally.

## Implementation Details

- Uses `ext-sodium` to verify token signatures with Zero Ad Network's public ED25519 key.
- Decodes token payload to extract protocol version, expiration timestamp, and site features.
- Generates a feature map; expired tokens produce all flags as `false`.

Parsed token example:

```php
[
  "HIDE_ADVERTISEMENTS" => boolean,
  "HIDE_COOKIE_CONSENT_SCREEN" => boolean,
  "HIDE_MARKETING_DIALOGS" => boolean,
  "DISABLE_NON_FUNCTIONAL_TRACKING" => boolean,
  "DISABLE_CONTENT_PAYWALL" => boolean,
  "ENABLE_SUBSCRIPTION_ACCESS" => boolean
];
```

- Verification occurs locally; no data leaves your server.
- Parsing and verification adds roughly 0.06ms–0.6ms to endpoint execution time (tested on M1 MacBook Pro). Performance may vary.
- Redis caching tests show local verification is faster than retrieving cached results.

## Benefits of Joining

Partnering with Zero Ad Network allows your site to:

- Generate a new revenue stream by:
  - Providing a clean, unobstructed user experience
  - Removing paywalls and enabling free access to your base subscription plan
  - Or both combined
- Contribute to a truly joyful, user-friendly internet experience

## Onboarding Your Site

1. [Sign up](https://zeroad.network/login) with Zero Ad Network.
2. [Register your site](https://zeroad.network/publisher/sites/add) to receive your unique `X-Better-Web-Welcome` header.

Your site must include this header on all publicly accessible HTML or RESTful endpoints so that Zero Ad Network users’ browser extensions can recognize participation.

## Module Installation

Install via PHP Composer:

```shell
composer require zeroad.network/token
```

## Examples

The following PHP example demonstrates how to:

- Inject the "Welcome Header" into responses
- Parse the user's token from the request header
- Use the `$tokenContext` in controllers and templates

The most basic example looks like this:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

// Initialize the Zero Ad Network module at app startup.
// Your site's `clientId` value is obtained during site registration on the Zero Ad Network platform (https://zeroad.network).
$ZERO_AD_NETWORK_CLIENT_ID = "Z2CclA8oXIT1e0QmqTWF8w";
$site = new ZeroAd\Token\Site([
  "clientId" => $ZERO_AD_NETWORK_CLIENT_ID,
  "features" => [ZeroAd\Token\Constants::FEATURES["CLEAN_WEB"], ZeroAd\Token\Constants::FEATURES["ONE_PASS"]]
]);

// -----------------------------------------------------------------------------
// Middleware simulation function
// -----------------------------------------------------------------------------
function tokenMiddleware(callable $handler)
{
  global $site;

  // Inject the "X-Better-Web-Welcome" header into the response
  header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

  // Parse the incoming user token from the request header
  $tokenContext = $site->parseToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

  // Attach the parsed token data to the request object for downstream use
  $handler($tokenContext);
}

// -----------------------------------------------------------------------------
// Routing example (basic PHP routing)
// -----------------------------------------------------------------------------
$uri = $_SERVER["REQUEST_URI"];

if ($uri === "/") {
  tokenMiddleware(function ($tokenContext) {
    // Render HTML page with `$tokenContext` for demonstration
    $template =
      '
        <html>
            <body>
                <h1>Hello</h1>
                <pre>tokenContext = ' .
      htmlspecialchars(json_encode($tokenContext, JSON_PRETTY_PRINT)) .
      '</pre>
            </body>
        </html>
        ';
    echo $template;
  });
} elseif ($uri === "/json") {
  // Return JSON response with `$tokenContext`
  tokenMiddleware(function ($tokenContext) {
    header("Content-Type: application/json");
    echo json_encode([
      "message" => "OK",
      "tokenContext" => $tokenContext
    ]);
  });
} else {
  // Handle 404 Not Found
  http_response_code(404);
  echo "Not Found";
}
```
