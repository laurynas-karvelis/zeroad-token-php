<?php
declare(strict_types=1);

namespace ZeroAd\Token\Tests;

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Site;
use ZeroAd\Token\Constants;

final class SiteTest extends TestCase
{
    private string $siteId = "073C3D79-B960-4335-B948-416AC1E3DBD4";
    private string $expiredToken = "Aav2IXRoh0oKBw==.2yZfC2/pM9DWfgX+von4IgWLmN9t67HJHLiee/gx4+pFIHHurwkC3PCHT1Kaz0yUhx3crUaxST+XLlRtJYacAQ==";

    public function testServerHeaderGeneration()
    {
        $site = new Site(['siteId' => $this->siteId, 'features' => [Constants::FEATURES['ADS_OFF'], Constants::FEATURES['COOKIE_CONSENT_OFF']]]);
        $this->assertEquals(Constants::SERVER_HEADERS['WELCOME'], $site->SERVER_HEADER_NAME);
        $this->assertEquals("Bzw9eblgQzW5SEFqwePb1A^1^3", $site->SERVER_HEADER_VALUE);
    }

    public function testClientHeaderName()
    {
        $site = new Site(['siteId' => $this->siteId, 'features' => [Constants::FEATURES['ADS_OFF']]]);
        $this->assertEquals('HTTP_' . strtoupper(str_replace('-', '_', Constants::CLIENT_HEADERS['HELLO'])), $site->CLIENT_HEADER_NAME);
    }

    public function testParseTokenWithOfficialPublicKey()
    {
        $site = new Site(['siteId' => $this->siteId, 'features' => [Constants::FEATURES['ADS_OFF']]]);
        $parsed = $site->parseToken($this->expiredToken);

        foreach ($parsed as $flag) {
            $this->assertFalse($flag);
        }
    }
}
