<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/render.php";

use ZeroAd\Token\Publisher;

// -----------------------------------------------------------------------------
// Publisher initialization (once at startup)
// -----------------------------------------------------------------------------

$publisher = Publisher::create([
    "publisherId" => $_ENV["ZERO_AD_PUBLISHER_ID"] ?? "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe",
    // The demo server runs on localhost; list every host you actually serve in production.
    "hostnames" => ["localhost", "127.0.0.1"],
]);

// -----------------------------------------------------------------------------
// Middleware: announce participation, then verify the visitor's token
// -----------------------------------------------------------------------------

function tokenMiddleware(callable $handler): void
{
    global $publisher;

    // Tell the extension this site participates (set on every response, token or not)
    header("{$publisher->headerName}: {$publisher->headerValue}");

    // Verify the token the extension attached. The request's host is passed since we serve several.
    $visitor = $publisher->verify(
        $_SERVER[$publisher->tokenHeaderServerKey] ?? null,
        $_SERVER["HTTP_HOST"] ?? null
    );

    $handler($visitor);
}

// -----------------------------------------------------------------------------
// Routing
// -----------------------------------------------------------------------------

$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if ($uri === "/") {
    tokenMiddleware(function ($visitor) {
        // A single subscriber flag drives the whole page: the Freedom plan means no ads, no trackers,
        // no cookie dialog, no paywall.
        echo render("homepage", ["isSubscriber" => $visitor->subscriber]);
    });
} elseif ($uri === "/token") {
    tokenMiddleware(function ($visitor) {
        header("Content-Type: application/json");
        echo json_encode(["message" => "OK", "visitor" => $visitor->toArray()]);
    });
} else {
    http_response_code(404);
    echo "Not Found";
}
