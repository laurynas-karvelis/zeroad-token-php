<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Verification result cache, owned per publisher.
 *
 * A token is bound to one hostname and reused by the extension for the whole of its life, so a busy site
 * sees the same 232 characters on every request from a returning visitor. Verifying it once and
 * remembering the answer turns a pair of curve operations into a map lookup.
 *
 * Failures are cached alongside successes: a forged or replayed token costs as much to reject as a real
 * one costs to accept, and an attacker who retries will retry with the same bytes. Caching a negative is
 * safe because, for a fixed public key, no rejection can later turn into an acceptance - the only
 * direction a verdict can move is valid to expired, which the entry's own expiry already handles.
 *
 * Only verdicts that actually cost cryptography are stored. A malformed token is rejected by a length
 * check, so caching one would buy nothing while letting anybody who can send requests fill memory with
 * distinct junk keys.
 *
 * A verdict is an array: `["subscriber" => true, "plan" => int, "expiresAt" => int]` (unix seconds) or
 * `["subscriber" => false, "reason" => string]`.
 */
class ResultCache
{
    public const DEFAULT_OPTIONS = [
        "enabled" => true,
        "maxSize" => 1000,
        "ttl" => 600000,
    ];

    /** Entries are swept for expiry every N writes rather than on a timer, so an idle process stays idle. */
    private const SWEEP_INTERVAL = 128;

    private $enabled;
    private $maxSize;
    private $ttl;

    /** @var array<string,array> key => ["verdict" => array, "goodUntil" => int, "hits" => int, "storedAt" => int] */
    private $entries = [];

    private $hits = 0;
    private $misses = 0;
    private $evictions = 0;
    private $writesSinceSweep = 0;

    /** @param array<string,mixed> $overrides Any of `enabled`, `maxSize`, `ttl`. */
    public function __construct(array $overrides = [])
    {
        $options = array_merge(self::DEFAULT_OPTIONS, $overrides);

        if (!is_int($options["ttl"]) || $options["ttl"] < 0) {
            throw new \InvalidArgumentException("Cache `ttl` must be an integer >= 0");
        }
        if (!is_int($options["maxSize"]) || $options["maxSize"] < 1) {
            throw new \InvalidArgumentException("Cache `maxSize` must be an integer >= 1");
        }

        $this->enabled = (bool) $options["enabled"];
        $this->maxSize = $options["maxSize"];
        $this->ttl = $options["ttl"];
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

        if (!isset($this->entries[$key])) {
            $this->misses++;
            return null;
        }

        if ($this->entries[$key]["goodUntil"] <= $now) {
            unset($this->entries[$key]);
            $this->misses++;
            return null;
        }

        $this->entries[$key]["hits"]++;
        $this->hits++;
        return $this->entries[$key]["verdict"];
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

        $this->entries[$key] = [
            "verdict" => $verdict,
            "goodUntil" => $goodUntil,
            "hits" => 0,
            "storedAt" => $now,
        ];

        // Counting writes rather than the entry count, because eviction pins the size at `maxSize` and a
        // size-based trigger would stop firing the moment the cache filled up
        if (++$this->writesSinceSweep >= self::SWEEP_INTERVAL) {
            $this->writesSinceSweep = 0;
            $this->sweep($now);
        }

        while (count($this->entries) > $this->maxSize) {
            $this->evictOne();
        }
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    /** @return array<string,int> `size`, `maxSize`, `hits`, `misses`, `evictions`. */
    public function stats(): array
    {
        return [
            "size" => count($this->entries),
            "maxSize" => $this->maxSize,
            "hits" => $this->hits,
            "misses" => $this->misses,
            "evictions" => $this->evictions,
        ];
    }

    private function sweep(int $now): void
    {
        foreach ($this->entries as $key => $entry) {
            if ($entry["goodUntil"] <= $now) {
                unset($this->entries[$key]);
            }
        }
    }

    private function evictOne(): void
    {
        $weakestKey = null;
        $weakest = null;

        foreach ($this->entries as $key => $entry) {
            if ($weakest === null || self::isLessValuable($entry, $weakest)) {
                $weakestKey = $key;
                $weakest = $entry;
            }
        }

        if ($weakestKey !== null) {
            unset($this->entries[$weakestKey]);
            $this->evictions++;
        }
    }

    /** Least valuable first: fewest hits, oldest breaking the tie. */
    private static function isLessValuable(array $candidate, array $incumbent): bool
    {
        if ($candidate["hits"] !== $incumbent["hits"]) {
            return $candidate["hits"] < $incumbent["hits"];
        }
        return $candidate["storedAt"] < $incumbent["storedAt"];
    }
}
