<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Hostname canonicalisation.
 *
 * The extension binds a token to the hostname exactly as it appears in the address bar, lowercased and
 * without the port. A publisher gets that string back in varying shapes depending on the stack, so both
 * sides normalise the same way before the hostname is fed to a signature.
 *
 * Note what is deliberately *not* normalised here: `www.example.com` stays distinct from `example.com`.
 * A token is bound to whichever the visitor had in the address bar and is verified against that exact
 * string. The convenience of not having to list both is handled one level up, on the whitelist (see
 * `wwwVariants`), where it changes only which hosts are admitted, never what a signature is checked
 * against.
 */
class Hostname
{
    public static function canonical(string $hostname): string
    {
        $value = strtolower(trim($hostname));

        // `https://example.com/path` -> `example.com`, so a URL or an origin both work
        $schemeEnd = strpos($value, "://");
        if ($schemeEnd !== false) {
            $value = substr($value, $schemeEnd + 3);
        }

        $pathStart = strpos($value, "/");
        if ($pathStart !== false) {
            $value = substr($value, 0, $pathStart);
        }

        if (strncmp($value, "[", 1) === 0) {
            // IPv6 literal: `[::1]:8080` -> `::1`
            $close = strpos($value, "]");
            if ($close !== false) {
                return substr($value, 1, $close - 1);
            }
        }

        // A single colon is a port separator; several colons is a bare IPv6 literal, which stays whole
        $lastColon = strrpos($value, ":");
        if ($lastColon !== false && strpos($value, ":") === $lastColon) {
            $value = substr($value, 0, $lastColon);
        }

        // A fully qualified `example.com.` addresses the same host as `example.com`
        if (substr($value, -1) === ".") {
            $value = substr($value, 0, -1);
        }

        return $value;
    }

    /**
     * A host paired with its `www.` sibling: `example.com` -> `["example.com", "www.example.com"]`, and
     * `www.example.com` -> `["www.example.com", "example.com"]`.
     *
     * Publishers routinely serve both the apex and its `www` and reasonably expect listing one to cover
     * the other. This affects whitelist membership only: the token is still verified against the exact
     * host the request arrived on.
     *
     * @return string[]
     */
    public static function wwwVariants(string $hostname): array
    {
        if (strncmp($hostname, "www.", 4) === 0) {
            return [$hostname, substr($hostname, 4)];
        }

        return [$hostname, "www." . $hostname];
    }
}
