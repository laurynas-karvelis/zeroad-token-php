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
    $tokenContext = $site->parseToken($_SERVER[$site->CLIENT_HEADER_NAME] ?? null);

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
