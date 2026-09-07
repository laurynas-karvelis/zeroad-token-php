<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Ed25519;
use ZeroAd\Token\Publisher;
use ZeroAd\Token\Rejection;
use ZeroAd\Token\Token;
use ZeroAd\Token\VersionWarning;
use ZeroAd\Token\Tests\Fixtures\Authority;

/**
 * Everything here asks the same question: can anything other than a token the platform issued, for this
 * host, still in date, come back a subscriber?
 */
class HardeningTest extends TestCase
{
    private const HOSTNAME = "example.com";
    private const PUBLISHER_ID = "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe";

    /** @var Authority */
    private $authority;

    protected function setUp(): void
    {
        $this->authority = Authority::create();
    }

    private function build(): Publisher
    {
        return Publisher::create([
            "publisherId" => self::PUBLISHER_ID,
            "hostnames" => self::HOSTNAME,
            "publicKey" => $this->authority->publicKey,
        ]);
    }

    private function decode(string $token): string
    {
        return sodium_base642bin($token, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private function encode(string $bytes): string
    {
        return sodium_bin2base64($bytes, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    // --- no substitution of one signature for the other --------------------

    public function testTheHostnameSignatureCannotStandInForTheAuthoritySignature(): void
    {
        $bytes = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $hostnameSig = substr($bytes, Token::HOSTNAME_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);
        $bytes = substr_replace($bytes, $hostnameSig, Token::AUTHORITY_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);

        $this->assertSame(Rejection::FORGED, $this->build()->verify($this->encode($bytes))->reason);
    }

    public function testTheAuthoritySignatureCannotStandInForTheHostnameSignature(): void
    {
        $bytes = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $authSig = substr($bytes, Token::AUTHORITY_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);
        $bytes = substr_replace($bytes, $authSig, Token::HOSTNAME_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);

        $this->assertSame(Rejection::WRONG_HOSTNAME, $this->build()->verify($this->encode($bytes))->reason);
    }

    public function testACredentialCannotBePairedWithAnotherCredentialsHostnameSignature(): void
    {
        $first = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $second = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $secondSig = substr($second, Token::HOSTNAME_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);
        $first = substr_replace($first, $secondSig, Token::HOSTNAME_SIGNATURE_OFFSET, Ed25519::SIGNATURE_BYTES);

        $this->assertSame(Rejection::WRONG_HOSTNAME, $this->build()->verify($this->encode($first))->reason);
    }

    public function testSwappingInAnotherCredentialsEphemeralKeyBreaksTheAuthoritySignature(): void
    {
        $target = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $donor = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $donorKey = substr($donor, Token::EPHEMERAL_PUBLIC_KEY_OFFSET, Ed25519::RAW_PUBLIC_KEY_BYTES);
        $target = substr_replace($target, $donorKey, Token::EPHEMERAL_PUBLIC_KEY_OFFSET, Ed25519::RAW_PUBLIC_KEY_BYTES);

        $this->assertSame(Rejection::FORGED, $this->build()->verify($this->encode($target))->reason);
    }

    // --- degenerate cryptographic material ---------------------------------

    public function testAnAllZeroEphemeralKeyDoesNotVerify(): void
    {
        $bytes = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $bytes = substr_replace($bytes, str_repeat("\0", 32), Token::EPHEMERAL_PUBLIC_KEY_OFFSET, 32);
        $this->assertFalse($this->build()->verify($this->encode($bytes))->subscriber);
    }

    /**
     * @dataProvider signatureRanges
     */
    public function testAnAllZeroSignatureDoesNotVerify(int $from): void
    {
        $bytes = $this->decode($this->authority->mintToken(self::HOSTNAME));
        $bytes = substr_replace($bytes, str_repeat("\0", Ed25519::SIGNATURE_BYTES), $from, Ed25519::SIGNATURE_BYTES);
        $this->assertFalse($this->build()->verify($this->encode($bytes))->subscriber);
    }

    public function signatureRanges(): array
    {
        return [
            "authority" => [Token::AUTHORITY_SIGNATURE_OFFSET],
            "hostname" => [Token::HOSTNAME_SIGNATURE_OFFSET],
        ];
    }

    public function testAnAllZeroPublicKeyNeverValidatesASignature(): void
    {
        $this->assertFalse(Ed25519::verify(str_repeat("\0", 32), str_repeat("\0", 64), str_repeat("\0", 32)));
    }

    // --- fuzzing -----------------------------------------------------------

    public function testNoRandomInputEverProducesASubscriber(): void
    {
        VersionWarning::suppress();
        $publisher = $this->build();

        for ($attempt = 0; $attempt < 500; $attempt++) {
            $bytes = random_bytes(Token::TOKEN_BYTES);
            // Force a valid version, a known plan and a future expiry, so the fuzzer spends its attempts
            // on the signatures rather than dying at the layout check
            $bytes[0] = chr(1);
            $bytes[1] = chr(1);
            $bytes = substr_replace($bytes, pack("V", 0x70000000), 2, 4);

            $this->assertFalse($publisher->verify($this->encode($bytes))->subscriber);
        }
        VersionWarning::suppress(false);
    }

    public function testArbitraryStringsAreRejectedWithoutThrowing(): void
    {
        $publisher = $this->build();

        $inputs = [
            "",
            " ",
            "null",
            "%s%s%s",
            "../../etc/passwd",
            str_repeat("a", Token::TOKEN_CHARACTERS),
            str_repeat("=", Token::TOKEN_CHARACTERS),
            str_repeat("é", Token::TOKEN_CHARACTERS),
            base64_encode(str_repeat("\0", Token::TOKEN_BYTES)),
            bin2hex(str_repeat("\0", Token::TOKEN_BYTES)),
            str_repeat("z", 100000),
        ];

        foreach ($inputs as $input) {
            $this->assertFalse($publisher->verify($input)->subscriber);
        }
    }

    public function testRandomBytesNeverParseAsAWellFormedToken(): void
    {
        VersionWarning::suppress();
        $parsed = 0;
        for ($attempt = 0; $attempt < 5000; $attempt++) {
            if (is_array(Token::read($this->encode(random_bytes(Token::TOKEN_BYTES))))) {
                $parsed++;
            }
        }
        VersionWarning::suppress(false);

        // Only version 1 with a known plan byte gets through, roughly 1 in 65536 of random inputs
        $this->assertLessThan(5, $parsed);
    }
}
