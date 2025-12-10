<?php

declare(strict_types=1);

namespace ZeroAd\Token\Headers;

use ZeroAd\Token\Constants;
use ZeroAd\Token\Helpers;
use ZeroAd\Token\Logger;

class ServerHeader
{
  const SEPARATOR = "^";

  public static function encodeServerHeader(string $clientId, array $features): string
  {
    if (empty($clientId)) {
      throw new \Exception("The provided `clientId` value cannot be an empty string");
    }
    if (empty($features)) {
      throw new \Exception("At least one site feature must be provided");
    }

    $validValues = array_values(Constants::FEATURES);
    foreach ($features as $f) {
      if (!in_array($f, $validValues, true)) {
        $validKeys = implode(" | ", array_keys(Constants::FEATURES));
        throw new \Exception("Only valid site features are allowed: {$validKeys}");
      }
    }

    return implode(self::SEPARATOR, [$clientId, Constants::CURRENT_PROTOCOL_VERSION, Helpers::setFlags($features)]);
  }

  public static function decodeServerHeader(?string $headerValue): ?array
  {
    if (!$headerValue) {
      return null;
    }

    try {
      $parts = explode(self::SEPARATOR, $headerValue);
      Helpers::assert(count($parts) === 3, "Invalid header value format");

      [$clientId, $protocolVersion, $flags] = $parts;
      Helpers::assert(in_array((int) $protocolVersion, Constants::PROTOCOL_VERSION, true), "Invalid protocol version");
      Helpers::assert((string) (int) $flags === $flags, "Invalid flags number");

      $features = [];
      foreach (Constants::FEATURES as $feature => $bit) {
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
