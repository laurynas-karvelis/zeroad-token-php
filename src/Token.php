<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Token wire format, version 1 - 174 bytes, 232 base64url characters.
 *
 * ```
 *   offset  size  field
 *        0     1  version
 *        1     1  plan
 *        2     4  expiresAt, u32 unix seconds, little-endian
 *        6    32  ephemeralPublicKey
 *       38    64  authoritySignature
 *      102     8  nonce
 *      110    64  hostnameSignature
 * ```
 *
 * Two signatures, because the authority signs long before anyone knows which site the visitor will
 * open. The authority signs a batch credential over `version | plan | expiresAt | ephemeralPublicKey`;
 * the extension holds the matching ephemeral private key and, on first contact with a site, signs that
 * site's hostname with it. A publisher receiving the token holds only a public key and a signature over
 * its own hostname, so it cannot mint a binding for anybody else's site.
 *
 * The hostname is deliberately absent from the wire. The verifier already knows which host it serves and
 * reconstructs the signed message from that, so a token bound elsewhere simply fails the signature check.
 */
class Token
{
    public const VERSION_OFFSET = 0;
    public const PLAN_OFFSET = 1;
    public const EXPIRES_AT_OFFSET = 2;
    public const EPHEMERAL_PUBLIC_KEY_OFFSET = 6;
    public const AUTHORITY_SIGNATURE_OFFSET = 38; // EPHEMERAL_PUBLIC_KEY_OFFSET + RAW_PUBLIC_KEY_BYTES(32)
    public const NONCE_OFFSET = 102; // AUTHORITY_SIGNATURE_OFFSET + SIGNATURE_BYTES(64)
    public const NONCE_BYTES = 8;
    public const HOSTNAME_SIGNATURE_OFFSET = 110; // NONCE_OFFSET + NONCE_BYTES
    public const TOKEN_BYTES = 174; // HOSTNAME_SIGNATURE_OFFSET + SIGNATURE_BYTES(64)

    /** Exact base64url length of a `TOKEN_BYTES` payload, unpadded: ceil(174 * 4 / 3). */
    public const TOKEN_CHARACTERS = 232;

    /**
     * Domain separation tags. Without them a signature made for one purpose could be presented as if it
     * had been made for the other, and a future protocol could be tricked into accepting a v1 signature.
     */
    private const CREDENTIAL_DOMAIN = "better-web:credential:v1";
    private const HOSTNAME_DOMAIN = "better-web:hostname:v1";

    /** Layout-check outcomes, returned by `read()` in place of a parsed token. */
    public const MALFORMED = "malformed";
    public const UNSUPPORTED_VERSION = "unsupported_version";

    /**
     * Parses the fixed-width fields and rejects anything that cannot possibly verify, before spending any
     * elliptic-curve maths on it. Everything here is a length check or a byte comparison.
     *
     * @return array|string An array `["bytes" => string, "plan" => int, "expiresAt" => int]` on success,
     *                       or one of `self::MALFORMED` / `self::UNSUPPORTED_VERSION`.
     */
    public static function read(string $token)
    {
        // Cheapest possible filter, and the one that stops an attacker filling memory with junk: a token
        // is always exactly this long, so oversized input is discarded before it is decoded or cached
        if (strlen($token) !== self::TOKEN_CHARACTERS) {
            return self::MALFORMED;
        }

        $bytes = Base64::fromBase64Url($token);
        if ($bytes === null || strlen($bytes) !== self::TOKEN_BYTES) {
            return self::MALFORMED;
        }

        $version = ord($bytes[self::VERSION_OFFSET]);
        if ($version !== Constants::PROTOCOL_VERSION) {
            VersionWarning::warnIfAhead($version);
            return self::UNSUPPORTED_VERSION;
        }

        $plan = ord($bytes[self::PLAN_OFFSET]);
        if (!in_array($plan, array_values(Constants::PLAN), true)) {
            return self::MALFORMED;
        }

        // Little-endian unsigned 32-bit
        $expiresAt = unpack("V", substr($bytes, self::EXPIRES_AT_OFFSET, 4))[1];

        return ["bytes" => $bytes, "plan" => $plan, "expiresAt" => $expiresAt];
    }

    /** The message the authority signed at issuance: the domain tag plus every field up to its signature. */
    public static function credentialMessage(string $bytes): string
    {
        return self::CREDENTIAL_DOMAIN . substr($bytes, 0, self::AUTHORITY_SIGNATURE_OFFSET);
    }

    /**
     * The message the extension signed with its ephemeral key when it first met this hostname: the domain
     * tag, every field up to the hostname signature, and the hostname itself.
     */
    public static function hostnameMessage(string $bytes, string $hostname): string
    {
        return self::HOSTNAME_DOMAIN . substr($bytes, 0, self::HOSTNAME_SIGNATURE_OFFSET) . $hostname;
    }

    public static function ephemeralPublicKey(string $bytes): string
    {
        return substr($bytes, self::EPHEMERAL_PUBLIC_KEY_OFFSET, Ed25519::RAW_PUBLIC_KEY_BYTES);
    }

    public static function authoritySignature(string $bytes): string
    {
        return substr($bytes, self::AUTHORITY_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);
    }

    public static function hostnameSignature(string $bytes): string
    {
        return substr($bytes, self::HOSTNAME_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);
    }
}
