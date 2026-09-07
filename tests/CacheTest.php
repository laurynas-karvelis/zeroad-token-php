<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ZeroAd\Token\Publisher;
use ZeroAd\Token\Rejection;
use ZeroAd\Token\ResultCache;
use ZeroAd\Token\Token;
use ZeroAd\Token\Tests\Fixtures\Authority;

class CacheTest extends TestCase
{
    private const HOSTNAME = "example.com";
    private const PUBLISHER_ID = "zapub_7Fq2xR9nKdW3mB6tYp1sVzAe";

    /** @var Authority */
    private $authority;

    protected function setUp(): void
    {
        $this->authority = Authority::create();
    }

    private function build(array $overrides = []): Publisher
    {
        return Publisher::create(array_merge([
            "publisherId" => self::PUBLISHER_ID,
            "hostnames" => self::HOSTNAME,
            "publicKey" => $this->authority->publicKey,
        ], $overrides));
    }

    // --- caching through the publisher -------------------------------------

    public function testVerifiesOnceThenServesTheSameTokenFromMemory(): void
    {
        $publisher = $this->build();
        $token = $this->authority->mintToken(self::HOSTNAME);

        $this->assertFalse($publisher->verify($token)->cached);
        $this->assertTrue($publisher->verify($token)->cached);
        $this->assertTrue($publisher->verify($token)->cached);

        $stats = $publisher->cacheStats();
        $this->assertSame(1, $stats["size"]);
        $this->assertSame(2, $stats["hits"]);
        $this->assertSame(1, $stats["misses"]);
    }

    public function testACacheHitCarriesTheSameVerdictAsTheFreshVerification(): void
    {
        $publisher = $this->build();
        $token = $this->authority->mintToken(self::HOSTNAME);

        $fresh = $publisher->verify($token);
        $hit = $publisher->verify($token);

        $this->assertSame($fresh->subscriber, $hit->subscriber);
        $this->assertSame($fresh->plan, $hit->plan);
        $this->assertSame($fresh->planName, $hit->planName);
        $this->assertSame($fresh->hostname, $hit->hostname);
        $this->assertSame($fresh->expiresAt->getTimestamp(), $hit->expiresAt->getTimestamp());
        $this->assertTrue($hit->cached);
    }

    public function testKeysOnHostnameAsWellAsToken(): void
    {
        $publisher = $this->build(["hostnames" => ["a.example", "b.example"]]);
        $token = $this->authority->mintToken("a.example");

        $this->assertTrue($publisher->verify($token, "a.example")->subscriber);

        $atB = $publisher->verify($token, "b.example");
        $this->assertFalse($atB->subscriber);
        $this->assertSame(Rejection::WRONG_HOSTNAME, $atB->reason);
        $this->assertFalse($atB->cached);
    }

    public function testRemembersAForgedToken(): void
    {
        $publisher = $this->build();
        $forged = Authority::create()->mintToken(self::HOSTNAME);

        $first = $publisher->verify($forged);
        $this->assertSame(Rejection::FORGED, $first->reason);
        $this->assertFalse($first->cached);

        $second = $publisher->verify($forged);
        $this->assertSame(Rejection::FORGED, $second->reason);
        $this->assertTrue($second->cached);

        $stats = $publisher->cacheStats();
        $this->assertSame(1, $stats["size"]);
        $this->assertSame(1, $stats["hits"]);
    }

    public function testRemembersATokenReplayedFromAnotherSite(): void
    {
        $publisher = $this->build();
        $harvested = $this->authority->mintToken("somewhere-else.example");

        $this->assertFalse($publisher->verify($harvested)->cached);
        $second = $publisher->verify($harvested);
        $this->assertSame(Rejection::WRONG_HOSTNAME, $second->reason);
        $this->assertTrue($second->cached);
    }

    /**
     * @dataProvider cheapRejections
     */
    public function testDoesNotSpendCacheEntriesOnCheapRejections($token): void
    {
        $publisher = $this->build();
        $publisher->verify($token);
        $publisher->verify($token);
        $this->assertSame(0, $publisher->cacheStats()["size"]);
    }

    public function cheapRejections(): array
    {
        return [
            "malformed" => [str_repeat("!", Token::TOKEN_CHARACTERS)],
            "missing" => [null],
        ];
    }

    public function testAnOversizedHeaderIsDiscardedBeforeItCanBecomeACacheKey(): void
    {
        $publisher = $this->build();
        for ($index = 0; $index < 50; $index++) {
            $publisher->verify(str_repeat("z", 100000) . $index);
        }
        $this->assertSame(0, $publisher->cacheStats()["size"]);
    }

    public function testExpiredTokensAreNotCached(): void
    {
        $publisher = $this->build();
        $publisher->verify($this->authority->mintToken(self::HOSTNAME, ["expiresAt" => time() - 10]));
        $this->assertSame(0, $publisher->cacheStats()["size"]);
    }

    // --- cache configuration -----------------------------------------------

    public function testCanBeTurnedOffEntirely(): void
    {
        $publisher = $this->build(["cache" => false]);
        $token = $this->authority->mintToken(self::HOSTNAME);

        $this->assertFalse($publisher->verify($token)->cached);
        $this->assertFalse($publisher->verify($token)->cached);
        $this->assertSame(0, $publisher->cacheStats()["size"]);
    }

    public function testCacheTrueMeansTheDefaults(): void
    {
        $publisher = $this->build(["cache" => true]);
        $token = $this->authority->mintToken(self::HOSTNAME);

        $publisher->verify($token);
        $this->assertTrue($publisher->verify($token)->cached);
        $this->assertSame(ResultCache::DEFAULT_OPTIONS["maxSize"], $publisher->cacheStats()["maxSize"]);
    }

    public function testAZeroTtlDisablesReuseWithoutDisablingTheCache(): void
    {
        $publisher = $this->build(["cache" => ["ttl" => 0]]);
        $token = $this->authority->mintToken(self::HOSTNAME);

        $publisher->verify($token);
        $this->assertFalse($publisher->verify($token)->cached);
    }

    public function testClearCacheDropsEverything(): void
    {
        $publisher = $this->build();
        $token = $this->authority->mintToken(self::HOSTNAME);

        $publisher->verify($token);
        $publisher->clearCache();

        $this->assertSame(0, $publisher->cacheStats()["size"]);
        $this->assertFalse($publisher->verify($token)->cached);
    }

    public function testRejectsNonsensicalTtlAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/ttl/");
        $this->build(["cache" => ["ttl" => -1]]);
    }

    public function testRejectsNonsensicalMaxSizeAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/maxSize/");
        $this->build(["cache" => ["maxSize" => 0]]);
    }

    // --- result cache internals --------------------------------------------

    private function good(): array
    {
        return ["subscriber" => true, "plan" => 1, "expiresAt" => 4000000000];
    }

    private function bad(): array
    {
        return ["subscriber" => false, "reason" => Rejection::FORGED];
    }

    public function testNeverTrustsAVerdictPastTheTokensOwnExpiry(): void
    {
        $cache = new ResultCache(["ttl" => 10000000]);
        $expiresAt = 1000000;

        $cache->set("k", ["subscriber" => true, "plan" => 1, "expiresAt" => $expiresAt], 999000000);
        $this->assertNotNull($cache->get("k", 999500000));
        $this->assertNull($cache->get("k", $expiresAt * 1000 + 1));
    }

    public function testDropsAnEntryOnceItsTtlElapses(): void
    {
        $cache = new ResultCache(["ttl" => 1000]);
        $cache->set("k", $this->bad(), 0);
        $this->assertSame($this->bad(), $cache->get("k", 999));
        $this->assertNull($cache->get("k", 1000));
    }

    public function testRefusesToStoreAVerdictThatIsAlreadyStale(): void
    {
        $cache = new ResultCache(["ttl" => 60000]);
        $cache->set("k", ["subscriber" => true, "plan" => 1, "expiresAt" => 500], 1000000);
        $this->assertSame(0, $cache->stats()["size"]);
    }

    public function testHoldsTheSizeAtMaxSizeByEvictingTheLeastUsedEntry(): void
    {
        $cache = new ResultCache(["maxSize" => 3]);

        $cache->set("a", $this->good(), 0);
        $cache->set("b", $this->good(), 1);
        $cache->set("c", $this->good(), 2);

        $cache->get("a", 3);
        $cache->get("a", 4);
        $cache->get("c", 5);

        $cache->set("d", $this->good(), 6);

        $this->assertNull($cache->get("b", 7));
        $this->assertNotNull($cache->get("a", 7));
        $this->assertNotNull($cache->get("c", 7));
        $this->assertNotNull($cache->get("d", 7));
        $stats = $cache->stats();
        $this->assertSame(3, $stats["size"]);
        $this->assertSame(1, $stats["evictions"]);
    }

    public function testBreaksAnEvictionTieOnAgeOldestFirst(): void
    {
        $cache = new ResultCache(["maxSize" => 2]);

        $cache->set("old", $this->good(), 0);
        $cache->set("new", $this->good(), 100);
        $cache->set("newest", $this->good(), 200);

        $this->assertNull($cache->get("old", 300));
        $this->assertNotNull($cache->get("new", 300));
    }

    public function testStaysBoundedUnderSustainedUniqueTraffic(): void
    {
        $cache = new ResultCache(["maxSize" => 50]);
        for ($index = 0; $index < 5000; $index++) {
            $cache->set("key-$index", $this->good(), $index);
        }
        $this->assertSame(50, $cache->stats()["size"]);
    }

    public function testSweepsExpiredEntriesAsWritesAccumulate(): void
    {
        $cache = new ResultCache(["maxSize" => 10000, "ttl" => 100]);

        for ($index = 0; $index < 200; $index++) {
            $cache->set("key-$index", $this->bad(), 0);
        }
        $this->assertSame(200, $cache->stats()["size"]);

        for ($index = 0; $index < 128; $index++) {
            $cache->set("late-$index", $this->bad(), 1000);
        }
        $this->assertLessThan(200, $cache->stats()["size"]);
    }

    public function testCountsHitsMissesAndEvictions(): void
    {
        $cache = new ResultCache(["maxSize" => 1]);

        $cache->get("nothing", 0);
        $cache->set("a", $this->good(), 0);
        $cache->get("a", 1);
        $cache->set("b", $this->good(), 2);

        $stats = $cache->stats();
        $this->assertSame(1, $stats["size"]);
        $this->assertSame(1, $stats["hits"]);
        $this->assertSame(1, $stats["misses"]);
        $this->assertSame(1, $stats["evictions"]);
    }

    public function testDisabledMeansNothingIsStoredOrReturned(): void
    {
        $cache = new ResultCache(["enabled" => false]);
        $cache->set("a", $this->good(), 0);
        $this->assertNull($cache->get("a", 1));
        $this->assertSame(0, $cache->stats()["size"]);
    }
}
