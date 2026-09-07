<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * The `Better-Web-Publisher` response header. The value is the publisher ID and nothing else:
 *
 * ```
 *   Better-Web-Publisher: zapub_7Fq2xR9nKd...
 * ```
 *
 * The same string is what goes in a `<meta name="Better-Web-Publisher">` tag and what a publisher prints
 * in page content on a platform they don't fully control - one id, one representation. The publisher ID
 * is what credits the visit for revenue sharing.
 */
class PublisherHeader
{
    /** The random part of a publisher id. Must match the platform's `PUBLISHER_ID_RANDOM_LENGTH`. */
    private const PUBLISHER_ID_RANDOM_LENGTH = 24;

    /**
     * A publisher ID is the prefix followed by exactly 24 alphanumerics, e.g.
     * `zapub_7Fq2xR9nKdW3mB6tYp1sVzAe`. The prefix is required, which is what lets a content scan reject
     * stray page text that happens to look id-shaped. Case-sensitive: the id is used verbatim wherever it
     * appears, so a re-cased copy is not the same id. The `D` modifier keeps `$` from matching before a
     * trailing newline, so a smuggled `\n` cannot slip through.
     */
    private const VALID_PUBLISHER_ID = '/^zapub_[A-Za-z0-9]{24}$/D';

    /** The header value for a publisher id: the id itself, once validated. Throws if it is malformed. */
    public static function encode(string $publisherId): string
    {
        if (!preg_match(self::VALID_PUBLISHER_ID, $publisherId)) {
            throw new \InvalidArgumentException(
                '`publisherId` must be "' . Constants::PUBLISHER_ID_SCHEME . '" followed by '
                . self::PUBLISHER_ID_RANDOM_LENGTH . " alphanumerics"
            );
        }

        return $publisherId;
    }

    /**
     * Reads a `Better-Web-Publisher` value and returns the publisher id, or `null` if it is absent or
     * unusable. Surrounding whitespace and any trailing `;`-separated parameters (which the format no
     * longer uses) are tolerated, so a header still carrying a legacy parameter continues to resolve.
     */
    public static function parse(?string $headerValue): ?string
    {
        if ($headerValue === null || $headerValue === "") {
            return null;
        }

        $publisherId = trim(explode(";", $headerValue)[0]);

        return preg_match(self::VALID_PUBLISHER_ID, $publisherId) ? $publisherId : null;
    }
}
