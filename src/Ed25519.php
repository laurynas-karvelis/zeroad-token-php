<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Ed25519 verification via ext-sodium, plus the one bit of key wrangling the SDK needs: stripping the
 * SPKI DER wrapper off the platform's public key to get the raw 32 bytes libsodium wants.
 *
 * This SDK only ever verifies. Nothing here can sign or mint - that needs a private key the platform
 * never releases.
 */
class Ed25519
{
    /** Raw Ed25519 public key length. */
    public const RAW_PUBLIC_KEY_BYTES = 32;

    /** Ed25519 detached signature length. */
    public const SIGNATURE_BYTES = 64;

    /** SPKI DER prefix for an Ed25519 public key. Prepended to a raw 32-byte key to wrap it. */
    private const SPKI_PREFIX = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

    /**
     * Verifies a detached Ed25519 signature. Returns `false` on any failure, including a signature or
     * key of the wrong length - callers treat every negative the same way, and libsodium throwing over
     * a malformed input would only be noise.
     */
    public static function verify(string $message, string $signature, string $rawPublicKey): bool
    {
        if (strlen($signature) !== self::SIGNATURE_BYTES || strlen($rawPublicKey) !== self::RAW_PUBLIC_KEY_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, $rawPublicKey);
        } catch (\SodiumException $error) {
            return false;
        }
    }

    /** Strips the SPKI DER wrapper off a base64 public key, leaving the raw 32 bytes. Throws if it is not one. */
    public static function rawPublicKeyFromSpkiBase64(string $base64): string
    {
        $bytes = Base64::fromBase64($base64);
        $prefixLength = strlen(self::SPKI_PREFIX);

        if ($bytes === null
            || strlen($bytes) !== $prefixLength + self::RAW_PUBLIC_KEY_BYTES
            || substr($bytes, 0, $prefixLength) !== self::SPKI_PREFIX
        ) {
            throw new \InvalidArgumentException("Expected a base64-encoded SPKI DER Ed25519 public key");
        }

        return substr($bytes, $prefixLength);
    }
}
