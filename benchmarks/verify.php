<?php

declare(strict_types=1);

/**
 * Reproducible microbenchmark for `Publisher::verify()`, mirroring the TypeScript SDK's numbers:
 * cold verification (two Ed25519 checks), a cached verdict (a map lookup), and a malformed token
 * (rejected on length before any decoding).
 *
 * Run: `php benchmarks/verify.php`
 */

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../tests/Fixtures/Authority.php";

use ZeroAd\Token\Publisher;
use ZeroAd\Token\Token;
use ZeroAd\Token\Tests\Fixtures\Authority;

$HOSTNAME = "example.com";
$PUBLISHER_ID = "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe";

$authority = Authority::create();
$validToken = $authority->mintToken($HOSTNAME);
$malformedToken = str_repeat("!", Token::TOKEN_CHARACTERS);

/**
 * @param callable $work A single unit of work to time.
 * @return array{perCallUs: float, opsPerSec: float}
 */
function measure(callable $work, int $iterations, int $warmup = 2000): array
{
    for ($i = 0; $i < $warmup; $i++) {
        $work();
    }

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $work();
    }
    $elapsedNs = hrtime(true) - $start;

    $perCallUs = $elapsedNs / $iterations / 1000;
    return ["perCallUs" => $perCallUs, "opsPerSec" => 1_000_000 / $perCallUs];
}

function report(string $label, array $result): void
{
    printf(
        "%-32s %8.2f us   %14s ops/s\n",
        $label,
        $result["perCallUs"],
        number_format($result["opsPerSec"])
    );
}

$iterations = 200_000;

// Cold: cache off, so every call runs the full two-signature verification.
$cold = Publisher::create([
    "publisherId" => $PUBLISHER_ID,
    "hostnames" => $HOSTNAME,
    "publicKey" => $authority->publicKey,
    "cache" => false,
]);

// Cached: default memory cache, warmed by the measure() warmup so every timed call is a hit.
$cached = Publisher::create([
    "publisherId" => $PUBLISHER_ID,
    "hostnames" => $HOSTNAME,
    "publicKey" => $authority->publicKey,
]);

echo "PHP " . PHP_VERSION . " on " . php_uname("m") . ", sodium " . (defined("SODIUM_LIBRARY_VERSION") ? SODIUM_LIBRARY_VERSION : "?") . "\n";
echo str_repeat("-", 68) . "\n";

report("Cold verification, end to end", measure(function () use ($cold, $validToken) {
    $cold->verify($validToken);
}, $iterations));

report("Cached verdict", measure(function () use ($cached, $validToken) {
    $cached->verify($validToken);
}, $iterations));

report("Malformed token", measure(function () use ($cold, $malformedToken) {
    $cold->verify($malformedToken);
}, $iterations));
