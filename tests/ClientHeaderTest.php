<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Crypto;
use ZeroAd\Token\Headers\ClientHeader;
use ZeroAd\Token\Helpers;

class ClientHeaderTest extends TestCase
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

  public function testEncodeDecodeClientHeader()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["CLEAN_WEB"], Constants::FEATURES["ONE_PASS"]];

    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features
      ],
      $this->privateKey
    );

    $decoded = ClientHeader::decodeClientHeader($headerValue, $this->publicKey);

    $this->assertIsArray($decoded);
    $this->assertEquals(Constants::CURRENT_PROTOCOL_VERSION, $decoded["version"]);
    $this->assertEquals(Helpers::setFlags($features), $decoded["flags"]);
    $this->assertEquals((int) $expiresAt->format("U"), $decoded["expiresAt"]->getTimestamp());
  }

  public function testParseClientTokenWithCleanWeb()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["CLEAN_WEB"]];
    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features
      ],
      $this->privateKey
    );

    $parsed = ClientHeader::parseClientToken($headerValue, [
      "clientId" => $this->clientId,
      "publicKey" => $this->publicKey,
      "features" => $features
    ]);

    $expected = [
      "HIDE_ADVERTISEMENTS" => true,
      "HIDE_COOKIE_CONSENT_SCREEN" => true,
      "HIDE_MARKETING_DIALOGS" => true,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => true,
      "DISABLE_CONTENT_PAYWALL" => false,
      "ENABLE_SUBSCRIPTION_ACCESS" => false
    ];

    $this->assertEquals($expected, $parsed);
  }

  public function testParseClientTokenWithOnePass()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["ONE_PASS"]];
    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features
      ],
      $this->privateKey
    );

    $parsed = ClientHeader::parseClientToken($headerValue, [
      "clientId" => $this->clientId,
      "publicKey" => $this->publicKey,
      "features" => $features
    ]);

    $expected = [
      "HIDE_ADVERTISEMENTS" => false,
      "HIDE_COOKIE_CONSENT_SCREEN" => false,
      "HIDE_MARKETING_DIALOGS" => false,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => false,
      "DISABLE_CONTENT_PAYWALL" => true,
      "ENABLE_SUBSCRIPTION_ACCESS" => true
    ];

    $this->assertEquals($expected, $parsed);
  }

  public function testParseClientTokenWithOnePassWhenSiteHasCleanWebFeature()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["ONE_PASS"]];
    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features
      ],
      $this->privateKey
    );

    $parsed = ClientHeader::parseClientToken($headerValue, [
      "clientId" => $this->clientId,
      "publicKey" => $this->publicKey,
      "features" => [Constants::FEATURES["CLEAN_WEB"]]
    ]);

    $expected = [
      "HIDE_ADVERTISEMENTS" => false,
      "HIDE_COOKIE_CONSENT_SCREEN" => false,
      "HIDE_MARKETING_DIALOGS" => false,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => false,
      "DISABLE_CONTENT_PAYWALL" => false,
      "ENABLE_SUBSCRIPTION_ACCESS" => false
    ];

    $this->assertEquals($expected, $parsed);
  }

  public function testParseClientTokenWithCleanWebWhenSiteHasOnePassFeature()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["CLEAN_WEB"]];
    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features
      ],
      $this->privateKey
    );

    $parsed = ClientHeader::parseClientToken($headerValue, [
      "clientId" => $this->clientId,
      "publicKey" => $this->publicKey,
      "features" => [Constants::FEATURES["ONE_PASS"]]
    ]);

    $expected = [
      "HIDE_ADVERTISEMENTS" => false,
      "HIDE_COOKIE_CONSENT_SCREEN" => false,
      "HIDE_MARKETING_DIALOGS" => false,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => false,
      "DISABLE_CONTENT_PAYWALL" => false,
      "ENABLE_SUBSCRIPTION_ACCESS" => false
    ];

    $this->assertEquals($expected, $parsed);
  }

  public function testParseClientTokenWithCleanWebWhenSiteHasCleanWebAndOnePassFeatures()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["CLEAN_WEB"]];
    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features
      ],
      $this->privateKey
    );

    $parsed = ClientHeader::parseClientToken($headerValue, [
      "clientId" => $this->clientId,
      "publicKey" => $this->publicKey,
      "features" => [Constants::FEATURES["CLEAN_WEB"], Constants::FEATURES["ONE_PASS"]]
    ]);

    $expected = [
      "HIDE_ADVERTISEMENTS" => true,
      "HIDE_COOKIE_CONSENT_SCREEN" => true,
      "HIDE_MARKETING_DIALOGS" => true,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => true,
      "DISABLE_CONTENT_PAYWALL" => false,
      "ENABLE_SUBSCRIPTION_ACCESS" => false
    ];

    $this->assertEquals($expected, $parsed);
  }

  public function testParseClientTokenWithAllFeaturesButSiteSupportsOnlyCleanWeb()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["CLEAN_WEB"], Constants::FEATURES["ONE_PASS"]];
    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features
      ],
      $this->privateKey
    );

    $parsed = ClientHeader::parseClientToken($headerValue, [
      "clientId" => $this->clientId,
      "publicKey" => $this->publicKey,
      "features" => [Constants::FEATURES["CLEAN_WEB"]]
    ]);

    $expected = [
      "HIDE_ADVERTISEMENTS" => true,
      "HIDE_COOKIE_CONSENT_SCREEN" => true,
      "HIDE_MARKETING_DIALOGS" => true,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => true,
      "DISABLE_CONTENT_PAYWALL" => false,
      "ENABLE_SUBSCRIPTION_ACCESS" => false
    ];

    $this->assertEquals($expected, $parsed);
  }

  public function testParseClientTokenWithTokenClientIdAndSiteClientIdDoNotMatch()
  {
    $expiresAt = new \DateTimeImmutable("+1 day");
    $features = [Constants::FEATURES["CLEAN_WEB"]];
    $headerValue = ClientHeader::encodeClientHeader(
      [
        "version" => Constants::CURRENT_PROTOCOL_VERSION,
        "expiresAt" => $expiresAt,
        "features" => $features,
        "clientId" => $this->clientId
      ],
      $this->privateKey
    );

    $parsed = ClientHeader::parseClientToken($headerValue, [
      "clientId" => "different_client_id",
      "publicKey" => $this->publicKey,
      "features" => [Constants::FEATURES["CLEAN_WEB"], Constants::FEATURES["ONE_PASS"]]
    ]);

    $expected = [
      "HIDE_ADVERTISEMENTS" => false,
      "HIDE_COOKIE_CONSENT_SCREEN" => false,
      "HIDE_MARKETING_DIALOGS" => false,
      "DISABLE_NON_FUNCTIONAL_TRACKING" => false,
      "DISABLE_CONTENT_PAYWALL" => false,
      "ENABLE_SUBSCRIPTION_ACCESS" => false
    ];

    $this->assertEquals($expected, $parsed);
  }
}
