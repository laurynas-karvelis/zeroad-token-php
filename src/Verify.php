<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * The whole of offline verification: layout, expiry, authority signature, hostname binding. No network,
 * no shared state, no clock beyond the local one.
 *
 * Checks run cheapest-first, and the authority signature is verified before the hostname signature so
 * that the ephemeral key is known to be one the platform actually blessed before anything is verified
 * against it.
 */
class Verify
{
    /**
     * @return array A verdict: `["subscriber" => true, "plan" => int, "expiresAt" => int]` or
     *               `["subscriber" => false, "reason" => string]`.
     */
    public static function verifyToken(
        string $token,
        string $hostname,
        string $authorityPublicKey,
        int $nowSeconds,
        int $clockToleranceSeconds
    ): array {
        $parsed = Token::read($token);

        if ($parsed === Token::MALFORMED) {
            return ["subscriber" => false, "reason" => Rejection::MALFORMED];
        }
        if ($parsed === Token::UNSUPPORTED_VERSION) {
            return ["subscriber" => false, "reason" => Rejection::UNSUPPORTED_VERSION];
        }

        if ($parsed["expiresAt"] + $clockToleranceSeconds <= $nowSeconds) {
            return ["subscriber" => false, "reason" => Rejection::EXPIRED];
        }

        $bytes = $parsed["bytes"];

        $credentialValid = Ed25519::verify(
            Token::credentialMessage($bytes),
            Token::authoritySignature($bytes),
            $authorityPublicKey
        );

        if (!$credentialValid) {
            return ["subscriber" => false, "reason" => Rejection::FORGED];
        }

        $boundToThisHost = Ed25519::verify(
            Token::hostnameMessage($bytes, $hostname),
            Token::hostnameSignature($bytes),
            Token::ephemeralPublicKey($bytes)
        );

        if (!$boundToThisHost) {
            return ["subscriber" => false, "reason" => Rejection::WRONG_HOSTNAME];
        }

        return ["subscriber" => true, "plan" => $parsed["plan"], "expiresAt" => $parsed["expiresAt"]];
    }

    /** Expands a stored verdict into the result handed back to the caller. */
    public static function toResult(array $verdict, string $hostname, bool $cached): VerificationResult
    {
        if (!$verdict["subscriber"]) {
            return VerificationResult::rejected($verdict["reason"], $hostname, $cached);
        }

        $expiresAt = (new \DateTimeImmutable())->setTimestamp($verdict["expiresAt"]);

        return VerificationResult::subscriber(
            $verdict["plan"],
            Constants::PLAN_NAME[$verdict["plan"]],
            $expiresAt,
            $hostname,
            $cached
        );
    }

    /**
     * Verdicts worth remembering. Everything else is rejected by a length or byte comparison in about a
     * microsecond, so caching it would save nothing while handing anybody who can send a request an easy
     * way to fill the cache with distinct keys.
     */
    public static function isCacheable(array $verdict): bool
    {
        if ($verdict["subscriber"]) {
            return true;
        }

        return $verdict["reason"] === Rejection::FORGED || $verdict["reason"] === Rejection::WRONG_HOSTNAME;
    }
}
