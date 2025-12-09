<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class Helpers
{
  public static function toBase64(string $data): string
  {
    return base64_encode($data);
  }

  public static function fromBase64(string $input): string
  {
    $decoded = base64_decode($input, true);
    if ($decoded === false) {
      throw new \Exception("Base64 decoding failed");
    }
    return $decoded;
  }

  public static function assert($value, string $message)
  {
    if (!$value) {
      throw new \Exception($message);
    }
  }

  public static function hasFlag(int $bit, int $flags): bool
  {
    return ($bit & $flags) !== 0;
  }

  public static function setFlags(array $features = []): int
  {
    $acc = 0;
    foreach ($features as $feature) {
      $acc |= $feature;
    }
    return $acc;
  }
}
