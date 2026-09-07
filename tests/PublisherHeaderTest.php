<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\PublisherHeader;

class PublisherHeaderTest extends TestCase
{
    private const PUBLISHER_ID = "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe";

    public function testEncodeReturnsThePublisherIdAsTheBareHeaderValue(): void
    {
        $this->assertSame(self::PUBLISHER_ID, PublisherHeader::encode(self::PUBLISHER_ID));
    }

    /**
     * @dataProvider invalidIds
     */
    public function testEncodeRefusesAnInvalidPublisherId(string $publisherId): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/zapub_/");
        PublisherHeader::encode($publisherId);
    }

    public function invalidIds(): array
    {
        return [
            "empty" => [""],
            "no prefix" => ["7Fq2xR9nKdW3mB6tYp1sVzAe"],
            "too short" => ["zapub_7Fq2xR9nKd"],
            "too long" => ["zapub_7Fq2xR9nKdW3mB6tYp1sVzAeXY"],
            "a space" => ["zapub_7Fq2xR9nKdW3mB6tYp1sVz e"],
            "a newline" => ["zapub_7Fq2xR9nKdW3mB6tYp1sVz\ne"],
            "a trailing newline" => ["zapub_7Fq2xR9nKdW3mB6tYp1sVzAe\n"],
            "a header injection attempt" => ["zapub_7Fq2xR9nKd\r\nSet-Cookie: x=1"],
            "a non-ascii character" => ["zapub_7Fq2xR9nKdW3mB6tYp1sVzÉé"],
        ];
    }

    public function testParseRoundTripsWhatEncodeProduced(): void
    {
        $this->assertSame(self::PUBLISHER_ID, PublisherHeader::parse(PublisherHeader::encode(self::PUBLISHER_ID)));
    }

    public function testParseToleratesWhitespaceAndALegacyTrailingParameter(): void
    {
        $this->assertSame(self::PUBLISHER_ID, PublisherHeader::parse("  " . self::PUBLISHER_ID . " ; v=1 "));
    }

    /**
     * @dataProvider unparseable
     */
    public function testParseReturnsNull(?string $headerValue): void
    {
        $this->assertNull(PublisherHeader::parse($headerValue));
    }

    public function unparseable(): array
    {
        return [
            "null" => [null],
            "empty string" => [""],
            "only a separator" => [";"],
            "no prefix" => ["7Fq2xR9nKdW3mB6tYp1sVzAe"],
            "a spaced id" => ["zapub_7Fq2xR9nKdW3mB6tYp1sVz e"],
            "a re-cased id" => [strtoupper(self::PUBLISHER_ID)],
        ];
    }
}
