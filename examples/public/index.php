<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

/**
 * Module initialization (once at startup)
 */

$site = new ZeroAd\Token\Site([
  "clientId" => "Z2CclA8oXIT1e0QmqTWF8w",
  "features" => [ZeroAd\Token\Constants::FEATURES["CLEAN_WEB"], ZeroAd\Token\Constants::FEATURES["ONE_PASS"]]
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
  // Return JSON response with `$tokenContext` for API usage
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
