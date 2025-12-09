<?php

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Headers\ServerHeader;

class ServerHeaderTest extends TestCase
{
  private $clientId;

  protected function setUp(): void
  {
    $this->clientId = bin2hex(random_bytes(16));
  }

  public function testEncodeDecodeServerHeader()
  {
    $features = [Constants::FEATURES["CLEAN_WEB"], Constants::FEATURES["ONE_PASS"]];
    $header = ServerHeader::encodeServerHeader($this->clientId, $features);

    $this->assertEquals("{$this->clientId}^1^3", $header);

    $decoded = ServerHeader::decodeServerHeader($header);
    $this->assertEquals($this->clientId, $decoded["clientId"]);
    $this->assertEquals(["CLEAN_WEB", "ONE_PASS"], $decoded["features"]);
    $this->assertEquals(1, $decoded["version"]);
  }

  public function testDecodeInvalidHeader()
  {
    $this->assertNull(ServerHeader::decodeServerHeader(""));
    $this->assertNull(ServerHeader::decodeServerHeader(null));
    $this->assertNull(ServerHeader::decodeServerHeader("invalid^header"));
  }
}
