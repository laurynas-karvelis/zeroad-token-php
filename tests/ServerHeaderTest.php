<?php

declare(strict_types=1);

namespace ZeroAd\Token\Tests;

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\ServerHeader;
use ZeroAd\Token\Constants;

final class ServerHeaderTest extends TestCase
{
  private string $siteId = "6418723C-9D55-4B95-B9CE-BC4DBDFFC812";

  public function testConstructorValidations()
  {
    $this->expectException(\InvalidArgumentException::class);
    new ServerHeader(null);

    $this->expectException(\InvalidArgumentException::class);
    new ServerHeader("");

    $this->expectException(\InvalidArgumentException::class);
    new ServerHeader(["features" => [Constants::FEATURES["ADS_OFF"]]]);

    $this->expectException(\InvalidArgumentException::class);
    new ServerHeader(["siteId" => "", "features" => [Constants::FEATURES["ADS_OFF"]]]);

    $this->expectException(\InvalidArgumentException::class);
    new ServerHeader(["siteId" => $this->siteId]);
  }

  public function testConstructorWithValidValue()
  {
    $header = new ServerHeader("ZBhyPJ1VS5W5zrxNvf/IEg^1^3");
    $this->assertEquals("ZBhyPJ1VS5W5zrxNvf/IEg^1^3", $header->VALUE);

    $header2 = new ServerHeader([
      "siteId" => $this->siteId,
      "features" => [Constants::FEATURES["ADS_OFF"], Constants::FEATURES["SUBSCRIPTION_ACCESS_ON"]],
    ]);
    $this->assertEquals("ZBhyPJ1VS5W5zrxNvf/IEg^1^17", $header2->VALUE);
  }

  public function testDecodeValidHeader()
  {
    $decoded = ServerHeader::decode("ZBhyPJ1VS5W5zrxNvf/IEg^1^3");
    $this->assertEquals(strtolower($this->siteId), strtolower($decoded["siteId"]));
    $this->assertContains("ADS_OFF", $decoded["features"]);
    $this->assertContains("COOKIE_CONSENT_OFF", $decoded["features"]);
    $this->assertEquals(1, $decoded["version"]);
  }

  public function testDecodeInvalidHeaders()
  {
    $this->assertNull(ServerHeader::decode(""));
    $this->assertNull(ServerHeader::decode(null));
    $this->assertNull(ServerHeader::decode("1^1"));
    $this->assertNull(ServerHeader::decode("invalid^1^1"));
  }
}
