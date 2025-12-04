<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class ServerHeader
{
  private const SEPARATOR = "^";
  public string $NAME;
  public string $VALUE;

  public function __construct($options)
  {
    $this->NAME = Constants::SERVER_HEADERS["WELCOME"];

    if (is_string($options)) {
      if (!$options) {
        throw new \InvalidArgumentException("ServerHeader string value must be non-empty");
      }
      $this->VALUE = $options;
    } elseif (is_array($options) && isset($options["siteId"], $options["features"])) {
      $this->VALUE = $this->encode($options["siteId"], $options["features"]);
    } else {
      throw new \InvalidArgumentException("ServerHeader must be string or array with siteId and features");
    }
  }

  public function encode(string $siteId, array $features): string
  {
    $flags = Helpers::setFeatures(0, $features);
    $encodedSiteId = Helpers::uuidToBase64($siteId);
    return implode(self::SEPARATOR, [$encodedSiteId, Constants::CURRENT_PROTOCOL_VERSION, $flags]);
  }

  public static function decode(?string $headerValue): ?array
  {
    if (!$headerValue) {
      return null;
    }
    try {
      $parts = explode(self::SEPARATOR, $headerValue);
      if (count($parts) !== 3) {
        return null;
      }

      [$encodedSiteId, $protocolVersion, $flags] = $parts;
      if (!in_array((int) $protocolVersion, Constants::PROTOCOL_VERSION)) {
        return null;
      }

      $siteId = Helpers::base64ToUuid($encodedSiteId);
      $flags = (int) $flags;

      $features = [];
      foreach (Constants::FEATURES as $key => $bit) {
        if (Helpers::hasFeature($flags, $bit)) {
          $features[] = $key;
        }
      }

      return [
        "version" => (int) $protocolVersion,
        "features" => $features,
        "siteId" => $siteId,
      ];
    } catch (\Throwable $e) {
      // Optionally log warning here
      return null;
    }
  }
}
