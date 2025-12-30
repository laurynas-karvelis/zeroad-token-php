<?php

declare(strict_types=1);

namespace ZeroAd\Token;

use ZeroAd\Token\Helpers;

class Crypto
{
  // cspell:words secretkey
  private static $keyCache = [];
  private const MAX_CACHE_SIZE = 10;

  /**
   * Fast hash function compatible with PHP 7.2+
   * Uses xxh3 on PHP 8.1+, falls back to xxh64 or sha256
   *
   * @param string $data Data to hash
   * @return string Hash string
   */
  private static function fastHash(string $data): string
  {
    // PHP 8.1+ has xxh3 (fastest)
    if (PHP_VERSION_ID >= 80100 && in_array("xxh3", hash_algos(), true)) {
      return hash("xxh3", $data);
    }

    // PHP 8.1+ also has xxh128 as alternative
    if (PHP_VERSION_ID >= 80100 && in_array("xxh128", hash_algos(), true)) {
      return hash("xxh128", $data);
    }

    // Fallback to md5 for PHP 7.2-8.0 (fast and sufficient for cache keys)
    return md5($data);
  }

  /**
   * Generate new Ed25519 keypair in DER format
   *
   * @return array Array with 'privateKey' and 'publicKey' in base64
   */
  public static function generateKeys(): array
  {
    // Generate 32-byte seed
    $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);

    // Generate Sodium keypair
    $keypair = sodium_crypto_sign_seed_keypair($seed);
    $secret = sodium_crypto_sign_secretkey($keypair); // 64 bytes
    $public = sodium_crypto_sign_publickey($keypair); // 32 bytes

    // Build PKCS8 DER (private)
    $privateDer = hex2bin("302e020100300506032b657004220420") . $seed;

    // Build SPKI DER (public)
    $publicDer = hex2bin("302a300506032b6570032100") . $public;

    return [
      "privateKey" => Helpers::toBase64($privateDer),
      "publicKey" => Helpers::toBase64($publicDer)
    ];
  }

  /**
   * Sign binary data using Ed25519 private key (DER PKCS8)
   *
   * @param string $data Binary data to sign
   * @param string $privateKeyBase64 Base64-encoded private key
   * @return string Binary signature
   */
  public static function sign(string $data, string $privateKeyBase64): string
  {
    $pkey = self::importPrivateKey($privateKeyBase64);
    return sodium_crypto_sign_detached($data, $pkey);
  }

  /**
   * Verify signature using Ed25519 public key (DER SPKI)
   *
   * @param string $data Binary data that was signed
   * @param string $signature Binary signature
   * @param string $publicKeyBase64 Base64-encoded public key
   * @return bool True if signature is valid
   */
  public static function verify(string $data, string $signature, string $publicKeyBase64): bool
  {
    $pkey = self::importPublicKey($publicKeyBase64);
    return sodium_crypto_sign_verify_detached($signature, $data, $pkey);
  }

  /**
   * Generate cryptographically secure random bytes
   *
   * @param int $size Number of bytes to generate
   * @return string Random bytes
   */
  public static function nonce(int $size): string
  {
    return random_bytes($size);
  }

  /**
   * Import private key from DER PKCS8
   *
   * @param string $base64Der Base64-encoded DER key
   * @return string Sodium-compatible private key
   */
  private static function importPrivateKey(string $base64Der): string
  {
    // Use hash as cache key (shorter than full base64 string)
    $cacheKey = self::fastHash($base64Der);

    if (isset(self::$keyCache[$cacheKey])) {
      return self::$keyCache[$cacheKey];
    }

    $der = base64_decode($base64Der, true);
    if ($der === false || strlen($der) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
      throw new \Exception("Invalid DER private key");
    }

    // PKCS8 DER from TS: last 32 bytes = seed
    $seed = substr($der, -SODIUM_CRYPTO_SIGN_SEEDBYTES);
    $keypair = sodium_crypto_sign_seed_keypair($seed);
    $pkey = sodium_crypto_sign_secretkey($keypair);

    // Limit cache size (prevent unbounded growth)
    if (count(self::$keyCache) >= self::MAX_CACHE_SIZE) {
      array_shift(self::$keyCache);
    }

    self::$keyCache[$cacheKey] = $pkey;
    return $pkey;
  }

  /**
   * Import public key from DER SPKI
   *
   * @param string $base64Der Base64-encoded DER key
   * @return string Sodium-compatible public key
   */
  private static function importPublicKey(string $base64Der): string
  {
    // Use hash as cache key (shorter than full base64 string)
    $cacheKey = self::fastHash($base64Der);

    if (isset(self::$keyCache[$cacheKey])) {
      return self::$keyCache[$cacheKey];
    }

    $der = base64_decode($base64Der, true);
    if ($der === false || strlen($der) < SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
      throw new \Exception("Invalid DER public key");
    }

    // Last 32 bytes = raw public key
    $pkey = substr($der, -SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);

    // Limit cache size (prevent unbounded growth)
    if (count(self::$keyCache) >= self::MAX_CACHE_SIZE) {
      array_shift(self::$keyCache);
    }

    self::$keyCache[$cacheKey] = $pkey;
    return $pkey;
  }
}
