<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Site;
use ZeroAd\Token\Logger;
use ZeroAd\Token\Headers\ServerHeader;
use ZeroAd\Token\Headers\ClientHeader;

class ModuleTest extends TestCase
{
  public function testModuleExports()
  {
    $this->assertTrue(class_exists(Site::class));
    $this->assertTrue(method_exists(Logger::class, "setLogLevel"));
    $this->assertTrue(method_exists(ClientHeader::class, "encodeClientHeader"));
    $this->assertTrue(method_exists(ClientHeader::class, "decodeClientHeader"));
    $this->assertTrue(method_exists(ClientHeader::class, "parseClientToken"));
    $this->assertTrue(method_exists(ServerHeader::class, "encodeServerHeader"));
    $this->assertTrue(method_exists(ServerHeader::class, "decodeServerHeader"));
  }
}
