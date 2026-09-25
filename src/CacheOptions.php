<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/** @internal Shared configuration for the memory and APCu cache backends. */
final class CacheOptions
{
    public const DEFAULTS = [
        "enabled" => true,
        "maxSize" => 1000,
        "ttl" => 600000,
    ];

    public static function resolve(array $overrides): array
    {
        $options = array_merge(self::DEFAULTS, $overrides);

        if (!is_int($options["ttl"]) || $options["ttl"] < 0) {
            throw new \InvalidArgumentException("Cache `ttl` must be an integer >= 0");
        }

        if (!is_int($options["maxSize"]) || $options["maxSize"] < 1) {
            throw new \InvalidArgumentException("Cache `maxSize` must be an integer >= 1");
        }

        return $options;
    }
}
