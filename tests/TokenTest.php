<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Ed25519;
use ZeroAd\Token\Token;
use ZeroAd\Token\Tests\Fixtures\Authority;

class TokenTest extends TestCase
{
    public function testWireFormatIs174BytesAnd232Characters(): void
    {
        $this->assertSame(174, Token::TOKEN_BYTES);
        $this->assertSame(232, Token::TOKEN_CHARACTERS);
        $this->assertSame(
            Token::TOKEN_CHARACTERS,
            strlen(Authority::create()->mintToken("example.com"))
        );
    }

    public function testFieldOffsetsMatchTheOtherLanguagePorts(): void
    {
        $this->assertSame(6, Token::EPHEMERAL_PUBLIC_KEY_OFFSET);
        $this->assertSame(38, Token::AUTHORITY_SIGNATURE_OFFSET);
        $this->assertSame(102, Token::NONCE_OFFSET);
        $this->assertSame(110, Token::HOSTNAME_SIGNATURE_OFFSET);
    }

    public function testNeverCarriesTheHostname(): void
    {
        $hostname = "unmistakable-hostname.example";
        $bytes = sodium_base642bin(
            Authority::create()->mintToken($hostname),
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
        );

        $this->assertFalse(strpos($bytes, $hostname) !== false);
    }

    public function testReadsThePlanAndExpiryAGenuineTokenCarries(): void
    {
        $expiresAt = 1800000000;
        $parsed = Token::read(Authority::create()->mintToken("example.com", ["expiresAt" => $expiresAt]));

        $this->assertIsArray($parsed);
        $this->assertSame(Constants::PLAN["FREEDOM"], $parsed["plan"]);
        $this->assertSame($expiresAt, $parsed["expiresAt"]);
    }

    public function testReadsTheFullUnsignedExpiryRangeWithoutWrapping(): void
    {
        $parsed = Token::read(Authority::create()->mintToken("example.com", ["expiresAt" => 0xffffffff]));

        $this->assertIsArray($parsed);
        $this->assertSame(0xffffffff, $parsed["expiresAt"]);
    }

    /**
     * @dataProvider malformedTokens
     */
    public function testRejectsMalformedInput(string $token): void
    {
        $this->assertSame(Token::MALFORMED, Token::read($token));
    }

    public function malformedTokens(): array
    {
        return [
            "empty string" => [""],
            "one character short" => [str_repeat("a", Token::TOKEN_CHARACTERS - 1)],
            "one character long" => [str_repeat("a", Token::TOKEN_CHARACTERS + 1)],
            "outside the base64url alphabet" => [str_repeat("*", Token::TOKEN_CHARACTERS)],
            "standard base64 padding" => [str_repeat("a", Token::TOKEN_CHARACTERS - 1) . "="],
        ];
    }

    public function testRejectsAnUnknownPlanByte(): void
    {
        $authority = Authority::create();
        $this->assertSame(Token::MALFORMED, Token::read($authority->mintToken("example.com", ["plan" => 7])));
        $this->assertSame(Token::MALFORMED, Token::read($authority->mintToken("example.com", ["plan" => 0])));
    }

    public function testSeparatesAFutureVersionFromGarbage(): void
    {
        \ZeroAd\Token\VersionWarning::suppress();
        $this->assertSame(
            Token::UNSUPPORTED_VERSION,
            Token::read(Authority::create()->mintToken("example.com", ["version" => 2]))
        );
        \ZeroAd\Token\VersionWarning::suppress(false);
    }

    public function testAuthoritySignatureCoversVersionPlanExpiryAndEphemeralKey(): void
    {
        $authority = Authority::create();
        $parsed = Token::read($authority->mintToken("example.com"));
        $this->assertIsArray($parsed);

        $message = Token::credentialMessage($parsed["bytes"]);
        $signature = Token::authoritySignature($parsed["bytes"]);

        $this->assertTrue(
            Ed25519::verify($message, $signature, Ed25519::rawPublicKeyFromSpkiBase64($authority->publicKey))
        );
        $this->assertSame("better-web:credential:v1", substr($message, 0, 24));
    }

    public function testHostnameMessageChangesWithTheHostname(): void
    {
        $parsed = Token::read(Authority::create()->mintToken("example.com"));
        $this->assertIsArray($parsed);

        $forA = Token::hostnameMessage($parsed["bytes"], "a.example");
        $forB = Token::hostnameMessage($parsed["bytes"], "b.example");

        $this->assertNotSame($forA, $forB);
        $this->assertSame("better-web:hostname:v1", substr($forA, 0, 22));
    }
}
