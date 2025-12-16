<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/render.php";

/**
 * Module initialization (once at startup)
 */

$site = new ZeroAd\Token\Site([
  "clientId" => "DEMO-Z2CclA8oXIT1e0Qmq",
  "features" => [ZeroAd\Token\Constants::FEATURE["CLEAN_WEB"], ZeroAd\Token\Constants::FEATURE["ONE_PASS"]]
]);

// -----------------------------------------------------------------------------
// Middleware simulation function
// -----------------------------------------------------------------------------
function tokenMiddleware(callable $handler)
{
  global $site;

  // Inject the "X-Better-Web-Welcome" server header into every response
  header("{$site->SERVER_HEADER_NAME}: {$site->SERVER_HEADER_VALUE}");

  // Parse the incoming user token from the client header

  $tokenContext = $site->parseClientToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

  // Attach parsed token data to request for downstream use
  $handler($tokenContext);
}

// -----------------------------------------------------------------------------
// Routing example (basic PHP routing)
// -----------------------------------------------------------------------------
$uri = $_SERVER["REQUEST_URI"];

if ($uri === "/") {
  tokenMiddleware(function ($tokenContext) {
    // Render HTML page with `$tokenContext` for demonstration
    echo render("homepage", ["tokenContext" => $tokenContext]);
  });
} elseif ($uri === "/token") {
  // Return JSON response with `$tokenContext` for API usage
  tokenMiddleware(function ($tokenContext) {
    header("Content-Type: application/json");
    echo json_encode([
      "message" => "OK",
      "tokenContext" => $tokenContext ?? []
    ]);
  });
} else {
  // Handle 404 Not Found
  http_response_code(404);
  echo "Not Found";
}
