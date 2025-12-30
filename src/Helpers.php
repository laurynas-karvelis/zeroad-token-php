<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class Helpers
{
  /**
   * Encode binary data to base64 using sodium (faster than base64_encode)
   */
  public static function toBase64(string $data): string
  {
    return sodium_bin2base64($data, SODIUM_BASE64_VARIANT_ORIGINAL);
  }

  /**
   * Decode base64 string using sodium (faster and stricter than base64_decode)
   */
  public static function fromBase64(string $input): string
  {
    try {
      return sodium_base642bin($input, SODIUM_BASE64_VARIANT_ORIGINAL);
    } catch (\SodiumException $e) {
      throw new \Exception("Base64 decoding failed: " . $e->getMessage());
    }
  }

  /**
   * Assert a condition, throw exception if false
   */
  public static function assert($value, string $message)
  {
    if (!$value) {
      throw new \Exception($message);
    }
  }

  /**
   * Check if a bit flag is set
   */
  public static function hasFlag(int $bit, int $flags): bool
  {
    return ($bit & $flags) !== 0;
  }

  /**
   * Combine feature flags using bitwise OR
   */
  public static function setFlags(array $features = []): int
  {
    $acc = 0;
    foreach ($features as $feature) {
      $acc |= $feature;
    }
    return $acc;
  }
}
