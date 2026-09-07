<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * A verification result cache backed by APCu, so a verdict survives beyond the single request that
 * computed it.
 *
 * PHP shares nothing between requests: the in-memory {@see ResultCache} starts empty on every request
 * and, under PHP-FPM, is not even shared between workers. That is fine for a long-lived process but
 * wastes the cache entirely on a classic PHP stack, where a returning visitor's identical token is
 * re-verified from scratch on every page load. APCu is process-pool shared memory, so the answer
 * computed by one request (or one worker) is there for the next.
 *
 * The public surface matches {@see ResultCache} exactly - `get`, `set`, `clear`, `stats` - so a
 * `Publisher` can hold either without caring which. What differs is bookkeeping: APCu owns expiry and
 * eviction, so `maxSize` is advisory here and `evictions` is always reported as zero.
 *
 * A verdict is an array: `["subscriber" => true, "plan" => int, "expiresAt" => int]` (unix seconds) or
 * `["subscriber" => false, "reason" => string]`.
 */
class ApcuResultCache
{
    public const DEFAULT_OPTIONS = [
        "enabled" => true,
        "maxSize" => 1000,
        "ttl" => 600000,
        "prefix" => "zeroad:token:",
    ];

    /** @var bool */
    private $enabled;

    /** @var int Advisory only; APCu evicts under its own memory pressure. */
    private $maxSize;

    /** @var int Milliseconds a verdict is trusted. */
    private $ttl;

    /** @var string Namespaces our keys so several sites can share one APCu segment without colliding. */
    private $prefix;

    private $hits = 0;
    private $misses = 0;

    /** Whether this build can actually use APCu right now. CLI needs `apcu.enable_cli=1` to qualify. */
    public static function isSupported(): bool
    {
        return extension_loaded("apcu") && function_exists("apcu_enabled") && apcu_enabled();
    }

    /** @param array<string,mixed> $overrides Any of `enabled`, `maxSize`, `ttl`, `prefix`. */
    public function __construct(array $overrides = [])
    {
        $options = array_merge(self::DEFAULT_OPTIONS, $overrides);

        if (!is_int($options["ttl"]) || $options["ttl"] < 0) {
            throw new \InvalidArgumentException("Cache `ttl` must be an integer >= 0");
        }
        if (!is_int($options["maxSize"]) || $options["maxSize"] < 1) {
            throw new \InvalidArgumentException("Cache `maxSize` must be an integer >= 1");
        }

        $this->enabled = (bool) $options["enabled"] && self::isSupported();
        $this->maxSize = $options["maxSize"];
        $this->ttl = $options["ttl"];
        $this->prefix = (string) $options["prefix"];
    }

    /**
     * @param int $now Wall-clock milliseconds.
     * @return array|null The stored verdict, or null on miss.
     */
    public function get(string $key, int $now): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $found = false;
        $entry = apcu_fetch($this->prefix . $key, $found);

        if (!$found || !is_array($entry) || !isset($entry["verdict"], $entry["goodUntil"])) {
            $this->misses++;
            return null;
        }

        // APCu's own TTL is second-granular; the millisecond `goodUntil` is the exact bound, and never
        // trusting a success past the token's own expiry is folded into it at write time
        if ($entry["goodUntil"] <= $now) {
            apcu_delete($this->prefix . $key);
            $this->misses++;
            return null;
        }

        $this->hits++;
        return $entry["verdict"];
    }

    /**
     * @param array $verdict See the class docblock for the shape.
     * @param int   $now     Wall-clock milliseconds.
     */
    public function set(string $key, array $verdict, int $now): void
    {
        if (!$this->enabled) {
            return;
        }

        // A success is never trusted past the token's own expiry, however generous the TTL is
        $ttlExpiry = $now + $this->ttl;
        $goodUntil = $verdict["subscriber"]
            ? min($ttlExpiry, $verdict["expiresAt"] * 1000)
            : $ttlExpiry;

        if ($goodUntil <= $now) {
            return;
        }

        // APCu takes a second-granular TTL; round up so the entry never expires before its `goodUntil`,
        // which stays the authoritative bound checked on read
        $ttlSeconds = (int) ceil(($goodUntil - $now) / 1000);

        apcu_store(
            $this->prefix . $key,
            ["verdict" => $verdict, "goodUntil" => $goodUntil],
            $ttlSeconds
        );
    }

    /** Drops every verdict this cache owns, leaving any other APCu user's keys untouched. */
    public function clear(): void
    {
        if (!self::isSupported()) {
            return;
        }

        if (class_exists("APCUIterator")) {
            apcu_delete(new \APCUIterator("/^" . preg_quote($this->prefix, "/") . "/"));
            return;
        }

        // Without the iterator there is no way to target a prefix, so drop the whole segment
        apcu_clear_cache();
    }

    /** @return array<string,int> `size`, `maxSize`, `hits`, `misses`, `evictions`. */
    public function stats(): array
    {
        return [
            "size" => $this->countOwnKeys(),
            "maxSize" => $this->maxSize,
            "hits" => $this->hits,
            "misses" => $this->misses,
            "evictions" => 0,
        ];
    }

    private function countOwnKeys(): int
    {
        if (!$this->enabled || !class_exists("APCUIterator")) {
            return 0;
        }

        return iterator_count(new \APCUIterator("/^" . preg_quote($this->prefix, "/") . "/", APC_ITER_KEY));
    }
}
