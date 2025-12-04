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

This module helps developers to:

- Inject a valid site's HTTP Response Header (**Welcome Header**) to every endpoint. Example:

  ```http
  X-Better-Web-Welcome: "AZqnKU56eIC7vCD1PPlwHg^1^3"
  ```

- Detect Zero Ad Network user tokens sent via HTTP Request Header. Example:

  ```http
  X-Better-Web-Hello: "Aav2IXRoh0oKBw==.2yZfC2/pM9DWfgX+von4IgWLmN9t67HJHLiee/gx4+pFIHHurwkC3PCHT1Kaz0yUhx3crUaxST+XLlRtJYacAQ=="
  ```

- Parse and verify token integrity locally.
- Optionally generate a valid "Welcome Header" when `siteId` and `features` are provided.

## Implementation Details

- Uses `ext-sodium` to verify token signatures with Zero Ad Network's public ED25519 key.
- Decodes token payload to extract protocol version, expiration timestamp, and site features.
- Generates a feature map; expired tokens produce all flags as `false`.

Parsed token example:

```php
{
  ADS_OFF: boolean,
  COOKIE_CONSENT_OFF: boolean,
  MARKETING_DIALOG_OFF: boolean,
  CONTENT_PAYWALL_OFF: boolean,
  SUBSCRIPTION_ACCESS_ON: boolean,
};
```

- Verification is performed locally; no data leaves your server.
- Parsing and verification add roughly 0.06ms–0.6ms to endpoint execution time (tested on M1 MacBook Pro). Performance may vary.
- Redis caching tests showed local verification is faster than retrieving cached results.

## Benefits of Joining

Partnering with Zero Ad Network allows your site to:

- Generate a new revenue stream
- Provide a clean, unobstructed user experience
- Contribute to a more joyful, user-friendly internet

## Onboarding Your Site

1. [Sign up](https://zeroad.network/login) with Zero Ad Network.
2. [Register your site](https://zeroad.network/publisher/sites/add) and receive your unique `X-Better-Web-Welcome` header.

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

// -----------------------------------------------------------------------------
// Module initialization (once at startup)
// -----------------------------------------------------------------------------
// Initialize the Zero Ad Network Site module.
// Option 1: Use pre-generated server "Welcome Header" value:
//   $site = new Site(getenv('ZERO_AD_NETWORK_WELCOME_HEADER_VALUE'));
// Option 2: Dynamically construct "Welcome Header" using siteId and features:
$site = new ZeroAd\Token\Site([
  "siteId" => "073C3D79-B960-4335-B948-416AC1E3DBD4",
  "features" => [ZeroAd\Token\Constants::FEATURES["ADS_OFF"]],
]);

// -----------------------------------------------------------------------------
// Middleware simulation function
// -----------------------------------------------------------------------------
function tokenMiddleware(callable $handler)
{
  global $site;

  // Inject the server "X-Better-Web-Welcome" header
  header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

  // Read and parse the client's token header if present
  $tokenContext = $site->parseToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

  // Pass the parsed token context to the handler
  $handler($tokenContext);
}

// -----------------------------------------------------------------------------
// Routing example (basic PHP routing)
// -----------------------------------------------------------------------------
$uri = $_SERVER["REQUEST_URI"];

if ($uri === "/") {
  tokenMiddleware(function ($tokenContext) {
    // Render HTML page with token context for demonstration
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
  // Return JSON response with token context
  tokenMiddleware(function ($tokenContext) {
    header("Content-Type: application/json");
    echo json_encode([
      "message" => "OK",
      "tokenContext" => $tokenContext,
    ]);
  });
} else {
  // Handle 404 Not Found
  http_response_code(404);
  echo "Not Found";
}
```
