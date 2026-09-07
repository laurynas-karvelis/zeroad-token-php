<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Hostname;

class HostnameTest extends TestCase
{
    /**
     * @dataProvider canonicalCases
     */
    public function testCanonical(string $input, string $expected): void
    {
        $this->assertSame($expected, Hostname::canonical($input));
    }

    public function canonicalCases(): array
    {
        return [
            ["example.com", "example.com"],
            ["EXAMPLE.COM", "example.com"],
            ["  example.com  ", "example.com"],
            ["example.com.", "example.com"],
            ["example.com:8080", "example.com"],
            ["https://example.com", "example.com"],
            ["http://example.com:3000/blog?a=1", "example.com"],
            ["example.com/blog", "example.com"],
            ["HTTPS://Example.COM:443/", "example.com"],
            ["sub.deep.example.co.uk", "sub.deep.example.co.uk"],
            ["localhost:3000", "localhost"],
            ["127.0.0.1:8080", "127.0.0.1"],
            ["[::1]:8080", "::1"],
            ["[2001:db8::1]", "2001:db8::1"],
            ["", ""],
        ];
    }

    public function testIsIdempotent(): void
    {
        foreach (["https://Example.com:8080/x", "example.com.", "[::1]:1"] as $input) {
            $once = Hostname::canonical($input);
            $this->assertSame($once, Hostname::canonical($once));
        }
    }

    public function testKeepsWwwDistinctFromTheApex(): void
    {
        $this->assertNotSame(Hostname::canonical("example.com"), Hostname::canonical("www.example.com"));
    }

    public function testWwwVariantsPairsAnApexWithItsWwwSibling(): void
    {
        $this->assertSame(["example.com", "www.example.com"], Hostname::wwwVariants("example.com"));
    }

    public function testWwwVariantsPairsAWwwHostWithItsApexSibling(): void
    {
        $this->assertSame(["www.example.com", "example.com"], Hostname::wwwVariants("www.example.com"));
    }
}
