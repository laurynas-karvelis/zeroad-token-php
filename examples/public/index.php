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
