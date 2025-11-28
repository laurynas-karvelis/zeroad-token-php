<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ZeroAd\Token\Site;

// -----------------------------------------------------------------------------
// Module initialization (once on startup)
// -----------------------------------------------------------------------------
Site::init([
    'value' => getenv('ZERO_AD_NETWORK_WELCOME_HEADER_VALUE') ?: ''
]);

$SERVER_HEADER_NAME  = Site::getServerHeaderName();
$SERVER_HEADER_VALUE = Site::getServerHeaderValue();
$CLIENT_HEADER_NAME  = Site::getClientHeaderName();

// -----------------------------------------------------------------------------
// Middleware simulation function
// -----------------------------------------------------------------------------
function tokenMiddleware(callable $handler)
{
    global $SERVER_HEADER_NAME, $SERVER_HEADER_VALUE, $CLIENT_HEADER_NAME;

    // Inject server header
    header("{$SERVER_HEADER_NAME}: {$SERVER_HEADER_VALUE}");

    // Read client token header
    $httpHeaderName = 'HTTP_' . strtoupper(str_replace('-', '_', $CLIENT_HEADER_NAME));
    $clientHeaderValue = $_SERVER[$httpHeaderName] ?? null;

    // Process token
    $tokenContext = Site::processRequest($clientHeaderValue);

    // Pass token context to handler
    $handler($tokenContext);
}

// -----------------------------------------------------------------------------
// Routing example (basic PHP routing)
// -----------------------------------------------------------------------------
$uri = $_SERVER['REQUEST_URI'];

if ($uri === '/') {
    tokenMiddleware(function ($tokenContext) {
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'OK',
            'tokenContext' => $tokenContext
        ]);
    });
} elseif ($uri === '/template') {
    tokenMiddleware(function ($tokenContext) use ($CLIENT_HEADER_NAME) {
        $shouldRemoveAds = $tokenContext['shouldRemoveAds'] ?? false;
        $template = '
        <html>
            <body>
                <h1>Hello</h1>
                <pre>tokenContext = ' . htmlspecialchars(json_encode($tokenContext, JSON_PRETTY_PRINT)) . '</pre>
                ' . ($shouldRemoveAds ? '<p>Will not show ads</p>' : '<p>Will show ads</p>') . '
            </body>
        </html>
        ';
        echo $template;
    });
} else {
    http_response_code(404);
    echo 'Not Found';
}
