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

  const FEATURES_TO_ACTIONS = [
    Constants::FEATURES["CLEAN_WEB"] => [
      "HIDE_ADVERTISEMENTS",
      "HIDE_COOKIE_CONSENT_SCREEN",
      "HIDE_MARKETING_DIALOGS",
      "DISABLE_NON_FUNCTIONAL_TRACKING"
    ],
    Constants::FEATURES["ONE_PASS"] => ["DISABLE_CONTENT_PAYWALL", "ENABLE_SUBSCRIPTION_ACCESS"]
  ];

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

  public static function parseClientToken(?string $headerValue, array $options): array
  {
    $data = self::decodeClientHeader($headerValue, $options["publicKey"] ?? Constants::ZEROAD_NETWORK_PUBLIC_KEY);
    $flags = 0;

    if ($data && $data["expiresAt"]->getTimestamp() >= time()) {
      $flags = $data["flags"];
    }
    if ($flags && isset($data["clientId"]) && $data["clientId"] !== $options["clientId"]) {
      $flags = 0;
    }

    $context = [];
    foreach (self::FEATURES_TO_ACTIONS as $feature => $actionNames) {
      $decision = in_array($feature, $options["features"] ?? [], true) && Helpers::hasFlag($feature, $flags);

      foreach ($actionNames as $actionName) {
        $context[$actionName] = $decision;
      }
    }

    return $context;
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
