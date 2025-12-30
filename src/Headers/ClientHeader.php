<?php

declare(strict_types=1);

namespace ZeroAd\Token\Headers;

use ZeroAd\Token\Constants;
use ZeroAd\Token\Helpers;
use ZeroAd\Token\Crypto;
use ZeroAd\Token\Logger;

class ClientHeader
{
  const VERSION_BYTES = 1;
  const NONCE_BYTES = 4;
  const SEPARATOR = ".";

  /**
   * Empty context constant (all features disabled)
   */
  const EMPTY_CONTEXT = [
    "HIDE_ADVERTISEMENTS" => false,
    "HIDE_COOKIE_CONSENT_SCREEN" => false,
    "HIDE_MARKETING_DIALOGS" => false,
    "DISABLE_NON_FUNCTIONAL_TRACKING" => false,
    "DISABLE_CONTENT_PAYWALL" => false,
    "ENABLE_SUBSCRIPTION_ACCESS" => false
  ];

  const FEATURE_TO_ACTIONS = [
    Constants::FEATURE["CLEAN_WEB"] => [
      "HIDE_ADVERTISEMENTS",
      "HIDE_COOKIE_CONSENT_SCREEN",
      "HIDE_MARKETING_DIALOGS",
      "DISABLE_NON_FUNCTIONAL_TRACKING"
    ],
    Constants::FEATURE["ONE_PASS"] => ["DISABLE_CONTENT_PAYWALL", "ENABLE_SUBSCRIPTION_ACCESS"]
  ];

  // Cache configuration
  private static $cacheEnabled = false;
  private static $cacheTtl = 5;
  private static $cachePrefix = "zeroad:token:";

  /**
   * Configure caching behavior
   *
   * @param array $config Configuration array with keys: ttl, prefix
   */
  public static function configureCaching(array $config)
  {
    // Check if APCu is available
    if (!extension_loaded("apcu") || !apcu_enabled()) {
      Logger::log("warn", "APCu not available, caching disabled");
      self::$cacheEnabled = false;
      return;
    }

    self::$cacheEnabled = true;
    self::$cacheTtl = $config["ttl"] ?? 5;
    self::$cachePrefix = $config["prefix"] ?? "zeroad:token:";
  }

  /**
   * Return empty context (all features disabled)
   *
   * @return array Empty token context
   */
  public static function emptyContext(): array
  {
    return self::EMPTY_CONTEXT;
  }

  /**
   * Encode client header for testing purposes
   *
   * @param array $data Token data (version, expiresAt, features, clientId?)
   * @param string $privateKey Base64-encoded private key
   * @return string Base64-encoded token
   */
  public static function encodeClientHeader(array $data, string $privateKey): string
  {
    $payload = chr($data["version"]);
    $payload .= random_bytes(self::NONCE_BYTES);
    $payload .= pack("V", (int) floor($data["expiresAt"]->getTimestamp()));
    $payload .= pack("V", Helpers::setFlags($data["features"] ?? []));

    if (isset($data["clientId"])) {
      $payload .= $data["clientId"];
    }

    $signature = Crypto::sign($payload, $privateKey);
    return Helpers::toBase64($payload) . self::SEPARATOR . Helpers::toBase64($signature);
  }

  /**
   * Parse client token and build context
   *
   * @param string|null $headerValue Token header value
   * @param array $options Configuration (clientId, features, publicKey?)
   * @return array Token context with feature flags
   */
  public static function parseClientToken($headerValue, array $options): array
  {
    // Early return for empty input
    if ($headerValue === null || $headerValue === "") {
      return self::emptyContext();
    }

    // Validate length (prevent DoS with huge headers)
    if (strlen($headerValue) > 500) {
      Logger::log("warn", "Token header too long", ["length" => strlen($headerValue)]);
      return self::emptyContext();
    }

    $now = time();

    // Try cache first (APCu shared between PHP-FPM workers)
    if (self::$cacheEnabled) {
      $cacheKey = self::$cachePrefix . hash("xxh3", $headerValue);
      $cached = apcu_fetch($cacheKey, $success);

      if ($success && is_array($cached)) {
        // Check if cached token hasn't expired
        if ($cached["expiresAt"] >= $now) {
          return self::buildContext($cached, $options, $now);
        }
        // Token expired, delete from cache
        apcu_delete($cacheKey);
      }
    }

    // Cache miss - decode and verify (expensive crypto operation!)
    $decoded = self::decodeClientHeader($headerValue, $options["publicKey"] ?? Constants::ZEROAD_NETWORK_PUBLIC_KEY);

    // Cache the result if decoding succeeded
    if (self::$cacheEnabled && $decoded) {
      $cacheKey = self::$cachePrefix . hash("xxh3", $headerValue);

      // Calculate TTL: min of cache TTL and token expiry (respects token expiration!)
      $tokenExpiry = $decoded["expiresAt"]->getTimestamp();
      $ttl = min(self::$cacheTtl, max(0, $tokenExpiry - $now));

      if ($ttl > 0) {
        $cacheData = [
          "expiresAt" => $tokenExpiry,
          "flags" => $decoded["flags"],
          "clientId" => $decoded["clientId"] ?? null,
          "version" => $decoded["version"]
        ];

        apcu_store($cacheKey, $cacheData, $ttl);
      }
    }

    // Build and return context
    return self::buildContext($decoded, $options, $now);
  }

  /**
   * Build token context from cached or decoded data
   */
  private static function buildContext($decoded, array $options, int $now): array
  {
    if (!$decoded) {
      return self::emptyContext();
    }

    // Get expiration timestamp (handle both DateTime objects and integers)
    $expiresAt = $decoded["expiresAt"];
    if ($expiresAt instanceof \DateTime) {
      $expiresAt = $expiresAt->getTimestamp();
    }

    // Token expired?
    if ($expiresAt < $now) {
      return self::emptyContext();
    }

    // Developer token validation (if `clientId` present, must match)
    if (isset($decoded["clientId"]) && $decoded["clientId"] !== $options["clientId"]) {
      return self::emptyContext();
    }

    $flags = $decoded["flags"];
    $context = [];

    foreach (self::FEATURE_TO_ACTIONS as $feature => $actionNames) {
      $decision = in_array($feature, $options["features"] ?? [], true) && Helpers::hasFlag($feature, $flags);

      foreach ($actionNames as $actionName) {
        $context[$actionName] = $decision;
      }
    }

    return $context;
  }

  /**
   * Decode and verify client token header
   *
   * @param string|null $headerValue Token header value
   * @param string $publicKey Base64-encoded public key
   * @return array|null Decoded token data or null on failure
   */
  public static function decodeClientHeader($headerValue, string $publicKey)
  {
    if (!$headerValue) {
      return null;
    }

    try {
      $separatorPos = strpos($headerValue, self::SEPARATOR);
      if ($separatorPos === false) {
        throw new \Exception("Invalid header format: missing separator");
      }

      $dataB64 = substr($headerValue, 0, $separatorPos);
      $sigB64 = substr($headerValue, $separatorPos + 1);

      $dataBytes = Helpers::fromBase64($dataB64);
      $sigBytes = Helpers::fromBase64($sigB64);

      // Verify signature (constant-time operation)
      if (!Crypto::verify($dataBytes, $sigBytes, $publicKey)) {
        Logger::log("warn", "Token signature verification failed");
        return null;
      }

      // Parse token data
      $version = ord($dataBytes[0]);

      $expectedMinLength = self::VERSION_BYTES + self::NONCE_BYTES + 8;
      if (strlen($dataBytes) < $expectedMinLength) {
        throw new \Exception("Invalid data length");
      }

      $expiresAt = unpack("V", substr($dataBytes, self::VERSION_BYTES + self::NONCE_BYTES, 4))[1];
      $flags = unpack("V", substr($dataBytes, self::VERSION_BYTES + self::NONCE_BYTES + 4, 4))[1];

      $clientId = null;
      if (strlen($dataBytes) > $expectedMinLength) {
        $clientId = substr($dataBytes, $expectedMinLength);
      }

      $expiresAtDt = new \DateTime();
      $expiresAtDt->setTimestamp($expiresAt);

      return [
        "version" => $version,
        "expiresAt" => $expiresAtDt,
        "flags" => $flags,
        "clientId" => $clientId ?? null
      ];
    } catch (\Exception $e) {
      Logger::log("warn", "Could not decode client header value", ["reason" => $e->getMessage()]);
      return null;
    }
  }
}
