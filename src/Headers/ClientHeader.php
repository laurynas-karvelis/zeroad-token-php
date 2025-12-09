<?php

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

  private static $siteFeaturesNative;

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

  public static function decodeClientHeader(?string $headerValue, string $publicKey): ?array
  {
    if (!$headerValue) {
      return null;
    }

    try {
      $parts = explode(self::SEPARATOR, $headerValue);
      [$dataB64, $sigB64] = $parts;
      $dataBytes = Helpers::fromBase64($dataB64);
      $sigBytes = Helpers::fromBase64($sigB64);

      if (!Crypto::verify($dataBytes, $sigBytes, $publicKey)) {
        throw new \Exception("Forged header value is provided");
      }

      $version = ord($dataBytes[0]);
      $expiresAt = unpack("V", substr($dataBytes, self::VERSION_BYTES + self::NONCE_BYTES, 4))[1];
      $flags = unpack("V", substr($dataBytes, self::VERSION_BYTES + self::NONCE_BYTES + 4, 4))[1];

      $clientId = null;
      $expectedLength = self::VERSION_BYTES + self::NONCE_BYTES + 8;
      if (strlen($dataBytes) > $expectedLength) {
        $clientId = substr($dataBytes, $expectedLength);
      }

      return [
        "version" => $version,
        "expiresAt" => new \DateTime()->setTimestamp($expiresAt),
        "flags" => $flags,
        "clientId" => $clientId ?? null,
      ];
    } catch (\Exception $e) {
      Logger::log("warn", "Could not decode client header value", ["reason" => $e->getMessage()]);
      return null;
    }
  }

  public static function parseClientToken(?string $headerValue, string $clientId, string $publicKey): array
  {
    $data = self::decodeClientHeader($headerValue, $publicKey);
    $flags = 0;

    if ($data && $data["expiresAt"]->getTimestamp() >= time()) {
      $flags = $data["flags"];
    }
    if ($flags && isset($data["clientId"]) && $data["clientId"] !== $clientId) {
      $flags = 0;
    }

    $features = [];
    foreach (Constants::FEATURES as $feature => $bit) {
      if (Helpers::hasFlag($flags, $bit)) {
        $features[] = $feature;
      }
    }

    $hasCleanWeb = in_array("CLEAN_WEB", $features, true);
    $hasOnePass = in_array("ONE_PASS", $features, true);

    return [
      "HIDE_ADVERTISEMENTS" => $hasCleanWeb,
      "HIDE_COOKIE_CONSENT_SCREEN" => $hasCleanWeb,
      "HIDE_MARKETING_DIALOGS" => $hasCleanWeb,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => $hasCleanWeb,
      "DISABLE_CONTENT_PAYWALL" => $hasOnePass,
      "ENABLE_SUBSCRIPTION_ACCESS" => $hasOnePass,
    ];
  }
}
