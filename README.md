# Introduction

This PHP Composer module is meant to be used by sites participating in [Zero Ad Network](https://zeroad.network) program, that are running in PHP runtime.

The `zeroad.network/token` module acts as an HTTP‑header‑based "access / entitlement token" library. It is a lightweight, fully open source, well tested and has no external production dependencies.

Up-to-date and in depth guides, how-to's and platform implementation details can be found at [the official Zero Ad Network documentation portal](https://docs.zeroad.network).

## Runtime compatibility

| Runtime | Compatibility | Ready |
| :------ | :------------ | :---: |
| PHP 7   | 7.2+          |  ✅   |
| PHP 8   | 8.0+          |  ✅   |

NOTE: Your PHP runtime must come with `ext-sodium` pre-installed.

## Purpose

It helps partnered site developer to:

- Inject a valid site's HTTP Response Header known as "Welcome Header" to every endpoint. An example:
  ```http
  X-Better-Web-Welcome: "AZqnKU56eIC7vCD1PPlwHg^1^3"
  ```
- Check for Zero Ad Network user's token presence that gets injected as a HTTP Request Header by their browser extension. An example of such Request Header:
  ```http
  X-Better-Web-Hello: "Aav2IXRoh0oKBw==.2yZfC2/pM9DWfgX+von4IgWLmN9t67HJHLiee/gx4+pFIHHurwkC3PCHT1Kaz0yUhx3crUaxST+XLlRtJYacAQ=="
  ```
- If found, parse the token from the HTTP Request Header value and verify its integrity.
- (Optionally) Generate a valid "Welcome Header" value when `siteId` UUID and site `features` array are provided.

## Implementation details

The module uses the `ext-sodium` runtime module to ensure the user's Request Header payload is valid by verifying its signature for the payload using Zero Ad Network's public ED25519 cryptographic key which is supplied within the module. Then:

- User's token payload is decoded and token's protocol version, expiration timestamp and site's feature list are extracted.
- A map of the site's features and their toggle states is generated.
- An expired token will produce a feature list with all flags being set to `false`.

Parsed token result example:

```php
{
  ADS_OFF: boolean,
  COOKIE_CONSENT_OFF: boolean,
  MARKETING_DIALOG_OFF: boolean,
  CONTENT_PAYWALL_OFF: boolean,
  SUBSCRIPTION_ACCESS_ON: boolean,
};
```

User's token payload verification is done locally within your app and no data leaves your server.

When a token is present, parsing and token integrity verification will roughly add between `0.06ms` and `0.6ms` to the total endpoint execution time (as per testing done on a M1 MacBook Pro). Your mileage will vary depending on your hardware, but the impact should stay minimal.

As per our exploratory test results in attempts to cache the token and its parsed results in Redis - it takes longer to retrieve the cached result than to verify token payload integrity.

## Why to join

By partnering with Zero Ad Network your site establishes a new stream of revenue enabling you to provide a tangible and meaningful value while simultaneously providing a pure, clean and unobstructed site UI that everyone loves.

With every new site joining us, it becomes easier to reshape the internet closer to its original intention - a joyful and wonderful experience for everyone.

## Onboard your site

To register your site, [sign up](https://zeroad.network/login) with Zero Ad Network and [register your site](https://zeroad.network/publisher/sites/add). On the second step of the Site registration process you'll be provided with your unique `X-Better-Web-Welcome` header value.

If you decide for your site to participate in the Zero Ad Network program, then it must respond with this header at all times on every publicly accessible endpoint containing HTML or RESTful response. When Zero Ad Network users visit your site, this allows their browser extension to know your site is participating in the program.

## Module installation

To install the module use PHP Composer:

```shell
composer require zeroad.network/token
```

# Examples

Take this PHP example as a quick reference. The example will show how to:

- Inject the "Welcome Header" into each response;
- Parse user's token from their request header;
- Use the `$tokenContext` value later in your controllers and templates.

The most basic example looks like this:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// -----------------------------------------------------------------------------
// Module initialization (once on startup)
// -----------------------------------------------------------------------------
// You can pass in server "Welcome Header" value that was generated once Site
// Registration was complete on our platform, like this:
//   $site = new Site(getenv('ZERO_AD_NETWORK_WELCOME_HEADER_VALUE'));
//
// Or pass in siteId and define site features you support at the time to
// construct "Welcome Header" dynamically, like this:
$site = new ZeroAd\Token\Site([
    'siteId' => "073C3D79-B960-4335-B948-416AC1E3DBD4",
    'features' => [
        ZeroAd\Token\Constants::FEATURES['ADS_OFF']
    ]
]);

// -----------------------------------------------------------------------------
// Middleware simulation function
// -----------------------------------------------------------------------------
function tokenMiddleware(callable $handler)
{
    global $site;

    // Inject server header
    header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

    // Read client token header. Client Header Name is already prepared
    // to used in $_SERVER lookup table and parse the token
    $tokenContext = $site->parseToken(
        $$_SERVER[$site->CLIENT_HEADER_NAME] ?? null
    );

    // Pass token context to handler
    $handler($tokenContext);
}

// -----------------------------------------------------------------------------
// Routing example (basic PHP routing)
// -----------------------------------------------------------------------------
$uri = $_SERVER['REQUEST_URI'];

if ($uri === '/') {
    tokenMiddleware(function ($tokenContext) {
        $template = '
        <html>
            <body>
                <h1>Hello</h1>
                <pre>tokenContext = ' . htmlspecialchars(
                    json_encode($tokenContext, JSON_PRETTY_PRINT)
                ) . '</pre>
            </body>
        </html>
        ';
        echo $template;
    });
} elseif ($uri === '/json') {
    tokenMiddleware(function ($tokenContext) {
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'OK',
            'tokenContext' => $tokenContext
        ]);
    });
} else {
    http_response_code(404);
    echo 'Not Found';
}
```
