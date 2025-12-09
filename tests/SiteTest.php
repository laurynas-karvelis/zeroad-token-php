<?php

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Crypto;
use ZeroAd\Token\Site;

class SiteTest extends TestCase
{
  private $privateKey;
  private $publicKey;
  private $clientId;

  protected function setUp(): void
  {
    $keys = Crypto::generateKeys();
    $this->privateKey = $keys["privateKey"];
    $this->publicKey = $keys["publicKey"];
    $this->clientId = bin2hex(random_bytes(16));
  }

  public function testSiteServerHeader()
  {
    $site = new Site([
      "clientId" => $this->clientId,
      "features" => [Constants::FEATURES["CLEAN_WEB"], Constants::FEATURES["ONE_PASS"]],
    ]);

    $this->assertEquals(Constants::SERVER_HEADERS["WELCOME"], $site->SERVER_HEADER_NAME);
    $this->assertEquals("{$this->clientId}^1^3", $site->SERVER_HEADER_VALUE);
  }

  public function testParseClientToken()
  {
    $site = new Site([
      "clientId" => $this->clientId,
      "features" => [Constants::FEATURES["CLEAN_WEB"]],
    ]);

    $expiresAt = new \DateTimeImmutable("+1 day");
    $headerValue = \ZeroAd\Token\Headers\ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => [Constants::FEATURES["CLEAN_WEB"]],
      ],
      $this->privateKey,
    );

    $parsed = $site->parseClientToken($headerValue);

    // Public key is different
    $expected = [
      "HIDE_ADVERTISEMENTS" => false,
      "HIDE_COOKIE_CONSENT_SCREEN" => false,
      "HIDE_MARKETING_DIALOGS" => false,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => false,
      "DISABLE_CONTENT_PAYWALL" => false,
      "ENABLE_SUBSCRIPTION_ACCESS" => false,
    ];

    $this->assertEquals($expected, $parsed);
  }
}
