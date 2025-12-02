## Introduction

**zeroad.network/token** is a module meant to be used by partnering sites of [Zero Ad Network](https://zeroad.network) platform. It's a lightweight module that works on PHP 7.2 (with `ext-sodium` pre-installed) and later versions.

This node module allows a Zero Ad Network program partnering sites and Web APIs to verify determine if incoming web requests are coming from our browser extension users with active subscription.

Their browser extension will send the `X-Better-Web-Hello` Request Header which will let our module to verify it's our actively subscribed user and will allow your site to make a decision whether to disable ads, paywalls or enable access to otherwise paid content of yours.

ZeroAd user browser extension will measure how many times and how long they spent on each resource of your website that sends the `X-Better-Web-Welcome` token. This information will go back to us and at the end of each month based on how large the active user base is and how much competition you got, you'll get awarded from each user's monthly subscription paid amount based on their usage patterns interacting with your site.

## Setup

If you already have your site registered with us, you can skip the section below.

### Register your website or web API

Sign up with us by navigating in your browser to [sign up](https://zeroad.network/login), once you've logged in successfully, go to and [add a project](https://zeroad.network/publisher/sites/add) page and register your site.

In the second step of the Site registration process you'll be presented with your unique `X-Better-Web-Welcome` header value for that site. Your website must respond with this header in all publicly accessible endpoints that contain HTML or RESTful response types. This will let ZeroAd Network users know that you are participating in the program.

## Module Installation

Install it with `composer`:

```shell
composer require zeroad.network/token
```

# Examples

Take the example as a reference only. The most basic example could look like this:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// -----------------------------------------------------------------------------
// Module initialization (once on startup)
// -----------------------------------------------------------------------------
// You can pass in server "Welcome Header" value that was generated once site Registration was complete on our platform, like this:
//   $site = new Site(getenv('ZERO_AD_NETWORK_WELCOME_HEADER_VALUE'));
//
// Or pass in siteId and define site features you support at the time to construct "Welcome Header" dynamically, like this:
$site = new ZeroAd\Token\Site(['siteId' => "073C3D79-B960-4335-B948-416AC1E3DBD4", 'features' => [ZeroAd\Token\Constants::FEATURES['ADS_OFF']]]);

// -----------------------------------------------------------------------------
// Middleware simulation function
// -----------------------------------------------------------------------------
function tokenMiddleware(callable $handler)
{
    global $site;

    // Inject server header
    header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

    // Read client token header. Client Header Name is already prepared to used in $_SERVER lookup table
    // And Parse the token
    $tokenContext = $site->parseToken($$_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

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
                <pre>tokenContext = ' . htmlspecialchars(json_encode($tokenContext, JSON_PRETTY_PRINT)) . '</pre>
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

For more example implementations please go to [see more examples](https://github.com/laurynas-karvelis/zeroad-token-php/tree/main/examples/).

P.S.: Each web request coming from active subscriber using their Zero Ad Network browser extension will incur a tiny fraction of CPU computation cost to verify the token data matches its encrypted signature. On modern web infrastructure a request execution time will increase roughly by ~0.6ms to 0.2ms or so. Mileage might vary, but the impact is minimal.

# Final thoughts

If no user of ours interacts with your website or web app, you lose nothing. You can keep showing ads to normal users, keep your paywalls etc.

We hope the opposite will happen and you'll realize how many people value pure, clean content created that is meant for them, actual people, that brings tangible and meaningful value for everyone.

Each website that joins us, becomes a part of re-making the web as it originally was intended to be - a joyful and wonderful experience once again.

**Thank you!**

> The "Zero Ad Network" team.
