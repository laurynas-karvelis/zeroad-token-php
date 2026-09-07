<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Publisher;
use ZeroAd\Token\Rejection;
use ZeroAd\Token\Token;
use ZeroAd\Token\VersionWarning;
use ZeroAd\Token\Tests\Fixtures\Authority;

class PublisherTest extends TestCase
{
    private const HOSTNAME = "example.com";
    private const PUBLISHER_ID = "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe";

    /** @var Authority */
    private $authority;

    protected function setUp(): void
    {
        $this->authority = Authority::create();
    }

    private function build(array $overrides = []): Publisher
    {
        return Publisher::create(array_merge([
            "publisherId" => self::PUBLISHER_ID,
            "hostnames" => self::HOSTNAME,
            "publicKey" => $this->authority->publicKey,
        ], $overrides));
    }

    // --- construction -------------------------------------------------------

    public function testExposesTheResponseHeader(): void
    {
        $publisher = $this->build();
        $this->assertSame(Constants::PUBLISHER_HEADER, $publisher->headerName);
        $this->assertSame(self::PUBLISHER_ID, $publisher->headerValue);
        $this->assertSame([Constants::PUBLISHER_HEADER, self::PUBLISHER_ID], $publisher->header);
    }

    public function testExposesTheRequestHeaderNames(): void
    {
        $publisher = $this->build();
        $this->assertSame(Constants::TOKEN_HEADER, $publisher->tokenHeaderName);
        $this->assertSame(Constants::TOKEN_HEADER_LOWERCASE, $publisher->tokenHeaderNameLowercase);
        $this->assertSame("HTTP_BETTER_WEB_TOKEN", $publisher->tokenHeaderServerKey);
    }

    public function testCanonicalisesConfiguredHostnames(): void
    {
        $publisher = $this->build(["hostnames" => ["  Example.COM:8080 ", "https://www.example.com/blog"]]);
        $this->assertSame(["example.com", "www.example.com"], $publisher->hostnames);
    }

    public function testRejectsAPublisherIdThatCouldInjectAHeader(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->build(["publisherId" => "zapub_abc\r\nX-Evil: 1"]);
    }

    public function testRejectsAnEmptyHostnameList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/At least one hostname/");
        $this->build(["hostnames" => []]);
    }

    public function testRejectsABlankHostname(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/At least one hostname/");
        $this->build(["hostnames" => "   "]);
    }

    public function testRejectsAPublicKeyThatIsNotEd25519Spki(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/SPKI DER Ed25519/");
        $this->build(["publicKey" => "bm90LWEta2V5"]);
    }

    public function testRejectsANegativeClockTolerance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/clockToleranceSeconds/");
        $this->build(["clockToleranceSeconds" => -1]);
    }

    // --- accepting a genuine token -----------------------------------------

    public function testAcceptsATokenBoundToTheConfiguredHostname(): void
    {
        $result = $this->build()->verify($this->authority->mintToken(self::HOSTNAME));

        $this->assertTrue($result->subscriber);
        $this->assertSame(Constants::PLAN["FREEDOM"], $result->plan);
        $this->assertSame("Freedom", $result->planName);
        $this->assertSame(self::HOSTNAME, $result->hostname);
        $this->assertFalse($result->cached);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->expiresAt);
        $this->assertGreaterThan(time(), $result->expiresAt->getTimestamp());
    }

    public function testAcceptsTheHostnamePassedExplicitlyInAnyCasingOrWithAPort(): void
    {
        $publisher = $this->build();
        $token = $this->authority->mintToken(self::HOSTNAME);

        foreach (["example.com", "EXAMPLE.com", "example.com:443", "example.com."] as $host) {
            $this->assertTrue($publisher->verify($token, $host)->subscriber);
        }
    }

    public function testTakesTheFirstValueWhenTheHeaderArrivedMoreThanOnce(): void
    {
        $token = $this->authority->mintToken(self::HOSTNAME);
        $result = $this->build()->verify([$token, $this->authority->mintToken("elsewhere.example")]);

        $this->assertTrue($result->subscriber);
    }

    public function testServesSeveralHostnamesIndependently(): void
    {
        $publisher = $this->build(["hostnames" => ["example.com", "www.example.com"]]);

        $apex = $publisher->verify($this->authority->mintToken("example.com"), "example.com");
        $www = $publisher->verify($this->authority->mintToken("www.example.com"), "www.example.com");

        $this->assertTrue($apex->subscriber);
        $this->assertTrue($www->subscriber);
    }

    public function testVerifiesAgainstTheExactHostSoAWwwBoundTokenDoesNotPassOnTheApex(): void
    {
        $publisher = $this->build(["hostnames" => ["example.com", "www.example.com"]]);
        $result = $publisher->verify($this->authority->mintToken("www.example.com"), "example.com");

        $this->assertFalse($result->subscriber);
        $this->assertSame(Rejection::WRONG_HOSTNAME, $result->reason);
    }

    public function testListingTheApexAdmitsTheWwwSibling(): void
    {
        $publisher = $this->build(["hostnames" => "example.com"]);
        $result = $publisher->verify($this->authority->mintToken("www.example.com"), "www.example.com");
        $this->assertTrue($result->subscriber);
    }

    public function testListingTheWwwAdmitsTheApexSibling(): void
    {
        $publisher = $this->build(["hostnames" => "www.example.com"]);
        $result = $publisher->verify($this->authority->mintToken("example.com"), "example.com");
        $this->assertTrue($result->subscriber);
    }

    // --- visitors without a usable token -----------------------------------

    /**
     * @dataProvider missingTokens
     */
    public function testReportsMissing($token): void
    {
        $result = $this->build()->verify($token);
        $this->assertFalse($result->subscriber);
        $this->assertSame(Rejection::MISSING, $result->reason);
        $this->assertFalse($result->cached);
    }

    public function missingTokens(): array
    {
        return [
            "null" => [null],
            "empty string" => [""],
            "empty array" => [[]],
        ];
    }

    /**
     * @dataProvider malformedTokens
     */
    public function testReportsMalformed(string $token): void
    {
        $result = $this->build()->verify($token);
        $this->assertFalse($result->subscriber);
        $this->assertSame(Rejection::MALFORMED, $result->reason);
    }

    public function malformedTokens(): array
    {
        return [
            "too short" => ["abc"],
            "too long" => [str_repeat("a", Token::TOKEN_CHARACTERS + 1)],
            "right length, not base64url" => [str_repeat("!", Token::TOKEN_CHARACTERS)],
        ];
    }

    public function testReportsAnUnknownPlanByteAsMalformed(): void
    {
        $token = $this->authority->mintToken(self::HOSTNAME, ["plan" => 99]);
        $this->assertSame(Rejection::MALFORMED, $this->build()->verify($token)->reason);
    }

    public function testReportsAFutureProtocolVersionDistinctly(): void
    {
        VersionWarning::suppress();
        $token = $this->authority->mintToken(self::HOSTNAME, ["version" => 2]);
        $this->assertSame(Rejection::UNSUPPORTED_VERSION, $this->build()->verify($token)->reason);
        VersionWarning::suppress(false);
    }

    public function testRejectsAnExpiredToken(): void
    {
        $token = $this->authority->mintToken(self::HOSTNAME, ["expiresAt" => time() - 3600]);
        $this->assertSame(Rejection::EXPIRED, $this->build()->verify($token)->reason);
    }

    public function testAllowsClockToleranceEitherSideOfExpiry(): void
    {
        $justExpired = time() - 30;
        $token = $this->authority->mintToken(self::HOSTNAME, ["expiresAt" => $justExpired]);

        $strict = $this->build(["clockToleranceSeconds" => 0]);
        $lenient = $this->build(["clockToleranceSeconds" => 120]);

        $this->assertSame(Rejection::EXPIRED, $strict->verify($token)->reason);
        $this->assertTrue($lenient->verify($token)->subscriber);
    }

    public function testRejectsAHostnameThisPublisherDoesNotServe(): void
    {
        $result = $this->build()->verify($this->authority->mintToken("other.example"), "other.example");
        $this->assertFalse($result->subscriber);
        $this->assertSame(Rejection::UNKNOWN_HOSTNAME, $result->reason);
        $this->assertSame("other.example", $result->hostname);
    }

    public function testDemandsAHostnameWhenSeveralAreConfigured(): void
    {
        $multi = $this->build(["hostnames" => ["a.example", "b.example"]]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/several hostnames/");
        $multi->verify($this->authority->mintToken("a.example"));
    }

    // --- attacks the two-tier binding is meant to stop ---------------------

    public function testOnePublisherCannotReplayAVisitorsTokenAtAnother(): void
    {
        $harvested = $this->authority->mintToken("site-a.example");
        $siteB = $this->build(["hostnames" => "site-b.example"]);

        $this->assertSame(Rejection::WRONG_HOSTNAME, $siteB->verify($harvested)->reason);
    }

    public function testRebindingAHarvestedCredentialFailsWithoutTheEphemeralPrivateKey(): void
    {
        $credential = $this->authority->issueCredential();
        $attackersKey = $this->authority->issueCredential()["ephemeralSecretKey"];

        $forged = Authority::bindToHostname($credential, "site-b.example", ["signingKey" => $attackersKey]);
        $siteB = $this->build(["hostnames" => "site-b.example"]);

        $this->assertSame(Rejection::WRONG_HOSTNAME, $siteB->verify($forged)->reason);
    }

    public function testATokenSignedByAnybodyButTheAuthorityIsForged(): void
    {
        $impostor = Authority::create();
        $result = $this->build()->verify($impostor->mintToken(self::HOSTNAME));
        $this->assertSame(Rejection::FORGED, $result->reason);
    }

    public function testEditingThePlanInvalidatesTheAuthoritySignature(): void
    {
        $token = Authority::corruptAt($this->authority->mintToken(self::HOSTNAME), 1);
        $this->assertSame(Rejection::MALFORMED, $this->build()->verify($token)->reason);
    }

    public function testExtendingTheExpiryInvalidatesTheAuthoritySignature(): void
    {
        $token = $this->authority->mintToken(self::HOSTNAME, ["expiresAt" => 0x70000000]);
        $extended = Authority::corruptAt($token, 5);
        $publisher = $this->build();

        $this->assertTrue($publisher->verify($token)->subscriber);
        $this->assertSame(Rejection::FORGED, $publisher->verify($extended)->reason);
    }

    /**
     * @dataProvider tamperOffsets
     */
    public function testTamperingWithAFieldIsCaught(int $offset): void
    {
        $result = $this->build()->verify(Authority::corruptAt($this->authority->mintToken(self::HOSTNAME), $offset));

        $this->assertFalse($result->subscriber);
        $this->assertContains($result->reason, [Rejection::FORGED, Rejection::WRONG_HOSTNAME]);
    }

    public function tamperOffsets(): array
    {
        return [
            "ephemeral public key" => [6],
            "authority signature" => [38],
            "nonce" => [102],
            "hostname signature" => [110],
        ];
    }

    public function testATokenFromADifferentOriginIsNotAdmittedBySpoofingTheHostHeader(): void
    {
        $publisher = $this->build();
        $attackerToken = $this->authority->mintToken("attacker.example");

        $this->assertSame(Rejection::UNKNOWN_HOSTNAME, $publisher->verify($attackerToken, "attacker.example")->reason);
        $this->assertSame(Rejection::WRONG_HOSTNAME, $publisher->verify($attackerToken, self::HOSTNAME)->reason);
    }
}
