<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class Helpers
{
  public static function uuidToBase64(string $uuid): string
  {
    $hex = str_replace("-", "", $uuid);
    if (strlen($hex) !== 32) {
      throw new \InvalidArgumentException("Invalid UUID format");
    }
    $bytes = hex2bin($hex);
    return rtrim(base64_encode($bytes), "=");
  }

  public static function base64ToUuid(string $input): string
  {
    $bytes = base64_decode($input . str_repeat("=", (4 - (strlen($input) % 4)) % 4), true);
    if (!$bytes || strlen($bytes) !== 16) {
      throw new \InvalidArgumentException("Invalid byte length for UUID");
    }
    $hex = bin2hex($bytes);
    return sprintf(
      "%s-%s-%s-%s-%s",
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12),
    );
  }

  public static function hasFeature(int $flags, int $feature): bool
  {
    return (bool) ($flags & $feature);
  }

  public static function setFeatures(int $flags = 0, array $features = []): int
  {
    foreach ($features as $feature) {
      $flags |= $feature;
    }
    return $flags;
  }
  /**
   * Minimal logger stub
   */
  public static function logger(string $level, string $message, array $context = [])
  {
    error_log(strtoupper($level) . ": " . $message . " " . json_encode($context));
  }
}
