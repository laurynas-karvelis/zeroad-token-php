<?php

declare(strict_types=1);

namespace ZeroAd\Token\Headers;

use ZeroAd\Token\Constants;
use ZeroAd\Token\Helpers;
use ZeroAd\Token\Logger;

class ServerHeader
{
  const SEPARATOR = "^";

  /**
   * Encode server welcome header
   *
   * @param string $clientId Your site's client ID from Zero Ad Network
   * @param array $features Array of feature flags (e.g., [Constants::FEATURE['CLEAN_WEB']])
   * @return string Encoded header value
   */
  public static function encodeServerHeader(string $clientId, array $features): string
  {
    if (empty($clientId)) {
      throw new \Exception("The provided `clientId` value cannot be an empty string");
    }
    if (empty($features)) {
      throw new \Exception("At least one site feature must be provided");
    }

    $validValues = array_values(Constants::FEATURE);
    foreach ($features as $f) {
      if (!in_array($f, $validValues, true)) {
        $validKeys = implode(" | ", array_keys(Constants::FEATURE));
        throw new \Exception("Only valid site features are allowed: {$validKeys}");
      }
    }

    return implode(self::SEPARATOR, [$clientId, Constants::CURRENT_PROTOCOL_VERSION, Helpers::setFlags($features)]);
  }

  /**
   * Decode server welcome header
   *
   * @param string|null $headerValue Header value to decode
   * @return array|null Decoded header data or null on failure
   */
  public static function decodeServerHeader($headerValue)
  {
    if (!$headerValue) {
      return null;
    }

    try {
      $parts = explode(self::SEPARATOR, $headerValue);
      Helpers::assert(count($parts) === 3, "Invalid header value format");

      $clientId = $parts[0];
      $protocolVersion = $parts[1];
      $flags = $parts[2];

      Helpers::assert(in_array((int) $protocolVersion, Constants::PROTOCOL_VERSION, true), "Invalid protocol version");
      Helpers::assert((string) (int) $flags === $flags, "Invalid flags number");

      $features = [];
      foreach (Constants::FEATURE as $feature => $bit) {
        if (Helpers::hasFlag((int) $flags, $bit)) {
          $features[] = $feature;
        }
      }

      return [
        "clientId" => $clientId,
        "version" => (int) $protocolVersion,
        "features" => $features
      ];
    } catch (\Exception $e) {
      Logger::log("warn", "Could not decode server header value", ["reason" => $e->getMessage()]);
      return null;
    }
  }
}
