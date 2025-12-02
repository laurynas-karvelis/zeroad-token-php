<?php
declare(strict_types=1);

namespace ZeroAd\Token\Tests;

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\ClientHeader;
use ZeroAd\Token\Constants;
use ZeroAd\Token\Crypto;

final class ClientHeaderTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        $keys = Crypto::generateKeys();
        $this->privateKey = $keys['privateKey'];
        $this->publicKey = $keys['publicKey'];
    }

    public function testDecodeGeneratesValidHeader()
    {
        $header = new ClientHeader($this->publicKey, $this->privateKey);

        $expiresAt = (new \DateTime())->add(new \DateInterval('P1D'));
        $features = [
            Constants::FEATURES['ADS_OFF'],
            Constants::FEATURES['COOKIE_CONSENT_OFF'],
            Constants::FEATURES['MARKETING_DIALOG_OFF']
        ];

        $headerValue = $header->encode(Constants::CURRENT_PROTOCOL_VERSION, $expiresAt, $features);

        $this->assertIsString($headerValue);

        $decoded = $header->decode($headerValue);
        $this->assertEquals(Constants::CURRENT_PROTOCOL_VERSION, $decoded['version']);
        $this->assertEquals((int)$expiresAt->format('U'), (int)$decoded['expiresAt']->format('U'));
        $this->assertEquals(7, $decoded['flags']); // ADS_OFF | COOKIE_CONSENT_OFF | MARKETING_DIALOG_OFF

        $featureMap = $header->parseToken($headerValue);
        $this->assertTrue($featureMap['ADS_OFF']);
        $this->assertTrue($featureMap['COOKIE_CONSENT_OFF']);
        $this->assertTrue($featureMap['MARKETING_DIALOG_OFF']);
        $this->assertFalse($featureMap['CONTENT_PAYWALL_OFF']);
        $this->assertFalse($featureMap['SUBSCRIPTION_ACCESS_ON']);
    }

    public function testDecodeWithExpiredToken()
    {
        $header = new ClientHeader($this->publicKey, $this->privateKey);

        $expiresAt = (new \DateTime())->sub(new \DateInterval('P1D'));
        $features = [
            Constants::FEATURES['ADS_OFF'],
            Constants::FEATURES['COOKIE_CONSENT_OFF'],
            Constants::FEATURES['MARKETING_DIALOG_OFF']
        ];

        $headerValue = $header->encode(Constants::CURRENT_PROTOCOL_VERSION, $expiresAt, $features);

        $decoded = $header->decode($headerValue);
        $this->assertEquals(Constants::CURRENT_PROTOCOL_VERSION, $decoded['version']);
        $this->assertEquals((int)$expiresAt->format('U'), (int)$decoded['expiresAt']->format('U'));
        $this->assertEquals(7, $decoded['flags']);

        $featureMap = $header->parseToken($headerValue);
        $this->assertFalse($featureMap['ADS_OFF']);
        $this->assertFalse($featureMap['COOKIE_CONSENT_OFF']);
        $this->assertFalse($featureMap['MARKETING_DIALOG_OFF']);
        $this->assertFalse($featureMap['CONTENT_PAYWALL_OFF']);
        $this->assertFalse($featureMap['SUBSCRIPTION_ACCESS_ON']);
    }

    public function testForgedHeaderReturnsFalseMap()
    {
        $header = new ClientHeader(Constants::ZEROAD_NETWORK_PUBLIC_KEY, $this->privateKey);

        $expiresAt = (new \DateTime())->sub(new \DateInterval('P1D'));
        $features = [
            Constants::FEATURES['ADS_OFF'],
            Constants::FEATURES['COOKIE_CONSENT_OFF'],
            Constants::FEATURES['MARKETING_DIALOG_OFF']
        ];

        $headerValue = $header->encode(Constants::CURRENT_PROTOCOL_VERSION, $expiresAt, $features);
        $header->decode($headerValue);

        $featureMap = $header->parseToken($headerValue);
        foreach ($featureMap as $flag) {
            $this->assertFalse($flag);
        }
    }

    public function testParseTokenHandlesNull()
    {
        $header = new ClientHeader(Constants::ZEROAD_NETWORK_PUBLIC_KEY);
        $featureMap = $header->parseToken(null);
        foreach ($featureMap as $flag) {
            $this->assertFalse($flag);
        }
    }
}
