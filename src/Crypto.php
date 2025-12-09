<?php

declare(strict_types=1);

namespace ZeroAd\Token;

use ZeroAd\Token\Helpers;

class Crypto
{
  // cspell:words secretkey
  private static $keyCache = [];

  /**
   * Generate new Ed25519 keypair in DER format
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
   */
  public static function sign(string $data, string $privateKeyBase64): string
  {
    $pkey = self::importPrivateKey($privateKeyBase64);
    return sodium_crypto_sign_detached($data, $pkey);
  }

  /**
   * Verify signature using Ed25519 public key (DER SPKI)
   */
  public static function verify(string $data, string $signature, string $publicKeyBase64): bool
  {
    $pkey = self::importPublicKey($publicKeyBase64);
    return sodium_crypto_sign_verify_detached($signature, $data, $pkey);
  }

  /**
   * Generate cryptographically secure random bytes
   */
  public static function nonce(int $size): string
  {
    return random_bytes($size);
  }

  /**
   * Import private key from DER PKCS8
   */
  private static function importPrivateKey(string $base64Der)
  {
    if (isset(self::$keyCache[$base64Der])) {
      return self::$keyCache[$base64Der];
    }

    $der = base64_decode($base64Der, true);
    if ($der === false || strlen($der) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
      throw new \Exception("Invalid DER private key");
    }

    // PKCS8 DER from TS: last 32 bytes = seed
    $seed = substr($der, -SODIUM_CRYPTO_SIGN_SEEDBYTES);
    $keypair = sodium_crypto_sign_seed_keypair($seed);
    $pkey = sodium_crypto_sign_secretkey($keypair);

    self::$keyCache[$base64Der] = $pkey;
    return $pkey;
  }

  /**
   * Import public key from DER SPKI
   */
  private static function importPublicKey(string $base64Der)
  {
    if (isset(self::$keyCache[$base64Der])) {
      return self::$keyCache[$base64Der];
    }

    $der = base64_decode($base64Der, true);
    if ($der === false || strlen($der) < SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
      throw new \Exception("Invalid DER public key");
    }

    // Last 32 bytes = raw public key
    $pkey = substr($der, -SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);

    self::$keyCache[$base64Der] = $pkey;
    return $pkey;
  }
}
