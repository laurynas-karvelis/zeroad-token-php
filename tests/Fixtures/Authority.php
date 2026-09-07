<?php

declare(strict_types=1);

namespace ZeroAd\Token\Tests\Fixtures;

/**
 * Reference implementation of the two parties this SDK does not contain: the authority that issues batch
 * credentials, and the extension that binds one to a hostname. It exists to drive the tests, and mirrors
 * `src/__tests__/__fixtures__/authority.ts` from the TypeScript SDK step for step, so a token minted here
 * verifies there and vice versa.
 *
 * The signing lives in the test fixture, never in the shipped `src/` - the published package only verifies.
 */
class Authority
{
    private const CREDENTIAL_DOMAIN = "better-web:credential:v1";
    private const HOSTNAME_DOMAIN = "better-web:hostname:v1";

    private const SPKI_PREFIX = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

    private const AUTHORITY_SIGNATURE_OFFSET = 38;
    private const NONCE_OFFSET = 102;
    private const HOSTNAME_SIGNATURE_OFFSET = 110;
    private const TOKEN_BYTES = 174;

    /** @var string base64 SPKI DER, the form `Publisher`'s `publicKey` option expects. */
    public $publicKey;

    /** @var string Raw 64-byte libsodium sign secret key. */
    private $secretKey;

    private function __construct(string $publicKey, string $secretKey)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
    }

    public static function create(): self
    {
        $keypair = sodium_crypto_sign_keypair();
        $rawPublic = sodium_crypto_sign_publickey($keypair);
        $spki = self::SPKI_PREFIX . $rawPublic;

        return new self(base64_encode($spki), sodium_crypto_sign_secretkey($keypair));
    }

    /**
     * Phase A: the authority checks the subscription is live and signs the extension's freshly generated
     * ephemeral public key. It never sees the private half.
     *
     * @param array<string,mixed> $options `plan`, `expiresAt` (unix seconds), `version`.
     * @return array{prefix:string,ephemeralSecretKey:string}
     */
    public function issueCredential(array $options = []): array
    {
        $plan = isset($options["plan"]) ? $options["plan"] : 1;
        $version = isset($options["version"]) ? $options["version"] : 1;
        $expiresAt = isset($options["expiresAt"]) ? $options["expiresAt"] : time() + 3600;

        $ephemeral = sodium_crypto_sign_keypair();
        $ephemeralPublic = sodium_crypto_sign_publickey($ephemeral);

        $signed = chr($version) . chr($plan) . pack("V", $expiresAt) . $ephemeralPublic;
        $signature = sodium_crypto_sign_detached(self::CREDENTIAL_DOMAIN . $signed, $this->secretKey);

        return [
            "prefix" => $signed . $signature,
            "ephemeralSecretKey" => sodium_crypto_sign_secretkey($ephemeral),
        ];
    }

    /**
     * Phase B: entirely local and offline. The extension picks an unused credential, stamps the hostname
     * it is about to contact, and signs that with the ephemeral key.
     *
     * @param array{prefix:string,ephemeralSecretKey:string} $credential
     * @param array<string,mixed> $options `nonce` (8 bytes), `signingKey` (models a tampered token).
     */
    public static function bindToHostname(array $credential, string $hostname, array $options = []): string
    {
        $nonce = isset($options["nonce"]) ? $options["nonce"] : "\x01\x02\x03\x04\x05\x06\x07\x08";
        if (strlen($nonce) !== 8) {
            throw new \InvalidArgumentException("Nonce must be 8 bytes");
        }

        $head = $credential["prefix"] . $nonce; // bytes 0..110
        $message = self::HOSTNAME_DOMAIN . $head . $hostname;
        $signingKey = isset($options["signingKey"]) ? $options["signingKey"] : $credential["ephemeralSecretKey"];
        $signature = sodium_crypto_sign_detached($message, $signingKey);

        return self::toBase64Url($head . $signature);
    }

    /** Issue a credential and bind it in one step. */
    public function mintToken(string $hostname, array $options = []): string
    {
        return self::bindToHostname($this->issueCredential($options), $hostname, $options);
    }

    /** Flips one bit at `$offset`, for tampering tests. */
    public static function corruptAt(string $token, int $offset): string
    {
        $bytes = sodium_base642bin($token, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x01);
        return self::toBase64Url($bytes);
    }

    private static function toBase64Url(string $bytes): string
    {
        return sodium_bin2base64($bytes, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
