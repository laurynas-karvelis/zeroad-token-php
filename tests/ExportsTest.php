<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Base64;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Ed25519;
use ZeroAd\Token\Hostname;
use ZeroAd\Token\Publisher;
use ZeroAd\Token\PublisherHeader;
use ZeroAd\Token\Rejection;
use ZeroAd\Token\ResultCache;
use ZeroAd\Token\Token;
use ZeroAd\Token\VerificationResult;
use ZeroAd\Token\Verify;
use ZeroAd\Token\VersionWarning;

/**
 * The published surface, asserted deliberately. Anything asserted here is a promise to keep working;
 * removing it is a breaking change for every publisher on the network.
 */
class ExportsTest extends TestCase
{
    public function testShipsEveryPublicClass(): void
    {
        foreach ([
            Base64::class,
            Constants::class,
            Ed25519::class,
            Hostname::class,
            Publisher::class,
            PublisherHeader::class,
            Rejection::class,
            ResultCache::class,
            Token::class,
            VerificationResult::class,
            Verify::class,
            VersionWarning::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "$class should exist");
        }
    }

    public function testShipsTheHeaderNamesAPublisherWiresUp(): void
    {
        $this->assertSame("Better-Web-Publisher", Constants::PUBLISHER_HEADER);
        $this->assertSame("Better-Web-Token", Constants::TOKEN_HEADER);
        $this->assertSame("better-web-token", Constants::TOKEN_HEADER_LOWERCASE);
    }

    public function testShipsAUsablePlatformPublicKey(): void
    {
        $this->assertMatchesBase64(Constants::AUTHORITY_PUBLIC_KEY);

        $publisher = Publisher::create([
            "publisherId" => "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe",
            "hostnames" => "example.com",
        ]);
        $this->assertSame("example.com", $publisher->hostnames[0]);
    }

    public function testDescribesTheSinglePlanOnOffer(): void
    {
        $this->assertSame(["FREEDOM" => 1], Constants::PLAN);
        $this->assertSame("Freedom", Constants::PLAN_NAME[Constants::PLAN["FREEDOM"]]);
    }

    public function testEnumeratesEveryRejectionReason(): void
    {
        $reasons = [
            Rejection::EXPIRED,
            Rejection::FORGED,
            Rejection::MALFORMED,
            Rejection::MISSING,
            Rejection::UNKNOWN_HOSTNAME,
            Rejection::UNSUPPORTED_VERSION,
            Rejection::WRONG_HOSTNAME,
        ];
        sort($reasons);

        $this->assertSame([
            "expired",
            "forged",
            "malformed",
            "missing",
            "unknown_hostname",
            "unsupported_version",
            "wrong_hostname",
        ], $reasons);
    }

    public function testDoesNotLeakIssuanceOrSigningHelpers(): void
    {
        // This package verifies. Anything able to mint a token belongs behind the platform's own auth.
        foreach (glob(__DIR__ . "/../src/*.php") as $file) {
            $source = file_get_contents($file);
            $this->assertNotRegExp(
                '/\bsodium_crypto_sign_detached\b|\bsodium_crypto_sign_keypair\b|function\s+(sign|mint|issue)/i',
                $source,
                "src/" . basename($file) . " must not carry a signing or issuing helper"
            );
        }
    }

    private function assertMatchesBase64(string $value): void
    {
        $this->assertRegExp('#^[A-Za-z0-9+/]+=*$#', $value);
    }
}
