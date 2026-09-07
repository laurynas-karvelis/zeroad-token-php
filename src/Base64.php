<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * base64url decoding for the token wire format: unpadded, `-`/`_` alphabet.
 *
 * A malformed token is a result, never an exception, so decoding returns `null` on bad input rather
 * than throwing. The caller's exact-length checks are what ultimately reject junk.
 */
class Base64
{
    /**
     * Decodes an unpadded base64url string to raw bytes, or returns `null` when the input is not valid
     * base64url. `sodium_base642bin` is strict about the alphabet and rejects trailing garbage, so a
     * stray character fails cleanly here rather than decoding to silently wrong bytes.
     */
    public static function fromBase64Url(string $input): ?string
    {
        try {
            return sodium_base642bin($input, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException $error) {
            return null;
        }
    }

    /**
     * Decodes standard, padded base64 (the form the SPKI public key is written in). Strict: any
     * character outside the alphabet makes it return `null`.
     */
    public static function fromBase64(string $input): ?string
    {
        $decoded = base64_decode($input, true);
        return $decoded === false ? null : $decoded;
    }
}
