<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * The outcome of `Publisher::verify()`. A single shape covers both branches: `subscriber` says which
 * one you are in. When it is `true`, `plan`, `planName` and `expiresAt` are set; when it is `false`,
 * `reason` says why. `hostname` and `cached` are always present.
 *
 * Read the fields directly, or call `toArray()` for a JSON-friendly copy.
 */
class VerificationResult
{
    /** @var bool Whether the visitor holds a live subscription this site must honour. */
    public $subscriber;

    /** @var int|null The plan byte, e.g. `Constants::PLAN["FREEDOM"]`. Null for a non-subscriber. */
    public $plan;

    /** @var string|null Human-readable plan name, e.g. `"Freedom"`. Null for a non-subscriber. */
    public $planName;

    /** @var \DateTimeImmutable|null When the token expires. Null for a non-subscriber. */
    public $expiresAt;

    /** @var string|null One of the `Rejection::*` reasons. Null for a subscriber. */
    public $reason;

    /** @var string The hostname the token was verified against. */
    public $hostname;

    /** @var bool Whether this verdict came from the cache rather than fresh cryptography. */
    public $cached;

    private function __construct()
    {
    }

    public static function subscriber(
        int $plan,
        string $planName,
        \DateTimeImmutable $expiresAt,
        string $hostname,
        bool $cached
    ): self {
        $result = new self();
        $result->subscriber = true;
        $result->plan = $plan;
        $result->planName = $planName;
        $result->expiresAt = $expiresAt;
        $result->hostname = $hostname;
        $result->cached = $cached;
        return $result;
    }

    public static function rejected(string $reason, string $hostname, bool $cached): self
    {
        $result = new self();
        $result->subscriber = false;
        $result->reason = $reason;
        $result->hostname = $hostname;
        $result->cached = $cached;
        return $result;
    }

    /** @return array<string,mixed> A JSON-friendly copy; `expiresAt` becomes a unix timestamp. */
    public function toArray(): array
    {
        return [
            "subscriber" => $this->subscriber,
            "plan" => $this->plan,
            "planName" => $this->planName,
            "expiresAt" => $this->expiresAt === null ? null : $this->expiresAt->getTimestamp(),
            "reason" => $this->reason,
            "hostname" => $this->hostname,
            "cached" => $this->cached,
        ];
    }
}
