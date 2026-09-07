<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * A token whose version is newer than this module understands means the Zero Ad Network has moved to a
 * token format this build predates. The token is rejected either way, but the maintainer needs to know
 * an upgrade is due, so we say so.
 */
class VersionWarning
{
    private static $suppressed = false;

    /**
     * Silences (or re-enables) the future-version warning. Handy when a site expects to see newer tokens
     * for a while - during a staged rollout - or in tests that deliberately feed unsupported versions.
     */
    public static function suppress(bool $suppressed = true): void
    {
        self::$suppressed = $suppressed;
    }

    /** Warns once for a token from a newer protocol than this build. Older or matching versions are ignored. */
    public static function warnIfAhead(int $version): void
    {
        if (self::$suppressed || $version <= Constants::PROTOCOL_VERSION) {
            return;
        }

        error_log(
            "[zeroad-token] Received a token using protocol version {$version}, but this module only "
            . "understands version " . Constants::PROTOCOL_VERSION . ", so the token was rejected. The Zero "
            . "Ad Network has moved to a newer token format - upgrade zeroad.network/token to keep admitting "
            . "subscribers on the new version."
        );
    }
}
