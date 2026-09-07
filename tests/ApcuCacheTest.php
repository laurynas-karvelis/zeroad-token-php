<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\ApcuResultCache;
use ZeroAd\Token\Publisher;
use ZeroAd\Token\Rejection;
use ZeroAd\Token\Tests\Fixtures\Authority;

/**
 * Exercises the APCu-backed cache. Every test is skipped where the extension is not available, so the
 * suite still passes on a build without APCu - the memory cache is covered exhaustively in CacheTest.
 */
class ApcuCacheTest extends TestCase
{
    private const HOSTNAME = "example.com";
    private const PUBLISHER_ID = "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe";
    private const PREFIX = "zeroad:test:";

    protected function setUp(): void
    {
        if (!ApcuResultCache::isSupported()) {
            $this->markTestSkipped("APCu is not available (needs the apcu extension, and apcu.enable_cli=1 on CLI)");
        }

        (new ApcuResultCache(["prefix" => self::PREFIX]))->clear();
    }

    private function cache(array $overrides = []): ApcuResultCache
    {
        return new ApcuResultCache(array_merge(["prefix" => self::PREFIX], $overrides));
    }

    private function good(): array
    {
        return ["subscriber" => true, "plan" => 1, "expiresAt" => 4000000000];
    }

    private function bad(): array
    {
        return ["subscriber" => false, "reason" => Rejection::FORGED];
    }

    // --- store internals ----------------------------------------------------

    public function testStoresAndReturnsAVerdict(): void
    {
        $cache = $this->cache();
        $cache->set("k", $this->good(), 1000);

        $this->assertSame($this->good(), $cache->get("k", 2000));
        $this->assertSame(1, $cache->stats()["hits"]);
    }

    public function testMissesOnAnAbsentKey(): void
    {
        $cache = $this->cache();
        $this->assertNull($cache->get("nope", 0));
        $this->assertSame(1, $cache->stats()["misses"]);
    }

    public function testSurvivesAcrossInstances(): void
    {
        $this->cache()->set("k", $this->good(), 1000);

        // A brand-new instance stands in for the next request against the same APCu segment
        $this->assertSame($this->good(), $this->cache()->get("k", 2000));
    }

    public function testNeverTrustsASuccessPastTheTokensOwnExpiry(): void
    {
        $cache = $this->cache(["ttl" => 10000000]);
        $expiresAt = 1000000;

        $cache->set("k", ["subscriber" => true, "plan" => 1, "expiresAt" => $expiresAt], 999000000);
        $this->assertNotNull($cache->get("k", 999500000));
        $this->assertNull($cache->get("k", $expiresAt * 1000 + 1));
    }

    public function testRefusesToStoreAVerdictThatIsAlreadyStale(): void
    {
        $cache = $this->cache(["ttl" => 60000]);
        $cache->set("k", ["subscriber" => true, "plan" => 1, "expiresAt" => 500], 1000000);
        $this->assertSame(0, $cache->stats()["size"]);
    }

    public function testClearDropsOnlyOwnKeys(): void
    {
        $this->cache()->set("mine", $this->good(), 0);
        apcu_store("someone-elses-key", "value");

        $this->cache()->clear();

        $found = false;
        apcu_fetch("someone-elses-key", $found);
        $this->assertTrue($found, "clear() must not touch keys outside the prefix");
        $this->assertNull($this->cache()->get("mine", 1));

        apcu_delete("someone-elses-key");
    }

    public function testReportsSizeOverOwnKeysOnly(): void
    {
        $cache = $this->cache();
        $cache->set("a", $this->good(), 0);
        $cache->set("b", $this->bad(), 0);

        $this->assertSame(2, $cache->stats()["size"]);
    }

    public function testDisabledMeansNothingIsStoredOrReturned(): void
    {
        $cache = $this->cache(["enabled" => false]);
        $cache->set("a", $this->good(), 0);
        $this->assertNull($cache->get("a", 1));
    }

    // --- through the publisher ----------------------------------------------

    public function testPublisherWithApcuStoreServesTheSecondHitFromCache(): void
    {
        $authority = Authority::create();
        $publisher = Publisher::create([
            "publisherId" => self::PUBLISHER_ID,
            "hostnames" => self::HOSTNAME,
            "publicKey" => $authority->publicKey,
            "cache" => ["store" => "apcu", "prefix" => self::PREFIX],
        ]);
        $token = $authority->mintToken(self::HOSTNAME);

        $this->assertFalse($publisher->verify($token)->cached);
        $this->assertTrue($publisher->verify($token)->cached);
    }

    public function testAutoStorePrefersApcuWhenAvailable(): void
    {
        $authority = Authority::create();
        $publisher = Publisher::create([
            "publisherId" => self::PUBLISHER_ID,
            "hostnames" => self::HOSTNAME,
            "publicKey" => $authority->publicKey,
            "cache" => ["store" => "auto", "prefix" => self::PREFIX],
        ]);
        $token = $authority->mintToken(self::HOSTNAME);

        $publisher->verify($token);
        $this->assertTrue($publisher->verify($token)->cached);
    }
}
