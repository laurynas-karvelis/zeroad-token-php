<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Everything a publisher needs, built once at startup and reused for the life of the process.
 *
 * ```php
 * use ZeroAd\Token\Publisher;
 *
 * $publisher = Publisher::create([
 *     "publisherId" => "zapub_...",
 *     "hostnames"   => "example.com", // covers www.example.com too; pass an array for other hosts
 * ]);
 *
 * header("{$publisher->headerName}: {$publisher->headerValue}");
 * $visitor = $publisher->verify($_SERVER[$publisher->tokenHeaderServerKey] ?? null);
 *
 * if ($visitor->subscriber) {
 *     // no ads, no trackers, no consent dialog, no paywall
 * }
 * ```
 */
class Publisher
{
    /** @var string From the dashboard. Announced to visitors and used to credit the site. */
    public $publisherId;

    /** @var string[] Canonicalised, in the order given. */
    public $hostnames;

    /** @var string `"Better-Web-Publisher"`. */
    public $headerName;

    /** @var string The value to send with it, computed once at startup. */
    public $headerValue;

    /** @var string[] `[name, value]` together, for convenience. */
    public $header;

    /** @var string `"Better-Web-Token"`. */
    public $tokenHeaderName;

    /** @var string `"better-web-token"`, how most frameworks key request headers. */
    public $tokenHeaderNameLowercase;

    /** @var string `"HTTP_BETTER_WEB_TOKEN"`, the `$_SERVER` key PHP exposes the token header under. */
    public $tokenHeaderServerKey;

    /** @var array<string,bool> Whitelist: each configured host and its `www` sibling. */
    private $allowed;

    /** @var string|null The one hostname, when exactly one was configured. */
    private $soleHostname;

    /** @var string Raw 32-byte Ed25519 authority key. */
    private $authorityPublicKey;

    /** @var int */
    private $clockToleranceSeconds;

    /** @var ResultCache */
    private $cache;

    /** @param array<string,mixed> $options See `create()`. */
    public function __construct(array $options)
    {
        if (!isset($options["publisherId"]) || !is_string($options["publisherId"])) {
            throw new \InvalidArgumentException(
                '`publisherId` must be "' . Constants::PUBLISHER_ID_SCHEME . '" followed by 24 alphanumerics'
            );
        }

        $this->headerValue = PublisherHeader::encode($options["publisherId"]);
        $this->publisherId = $options["publisherId"];

        $configured = isset($options["hostnames"]) ? $options["hostnames"] : [];
        if (!is_array($configured)) {
            $configured = [$configured];
        }

        $this->hostnames = [];
        foreach ($configured as $hostname) {
            $canonical = Hostname::canonical((string) $hostname);
            if ($canonical !== "") {
                $this->hostnames[] = $canonical;
            }
        }

        if (count($this->hostnames) === 0) {
            throw new \InvalidArgumentException(
                'At least one hostname must be provided, e.g. `"hostnames" => "example.com"`'
            );
        }

        // The whitelist admits each configured host and its `www` sibling, so a publisher serving both
        // the apex and its `www` need list only one. Membership widens; the signature is still checked
        // against the exact host the request arrived on.
        $this->allowed = [];
        foreach ($this->hostnames as $hostname) {
            foreach (Hostname::wwwVariants($hostname) as $variant) {
                $this->allowed[$variant] = true;
            }
        }

        $this->soleHostname = count($this->hostnames) === 1 ? $this->hostnames[0] : null;

        $publicKey = isset($options["publicKey"]) ? $options["publicKey"] : Constants::AUTHORITY_PUBLIC_KEY;
        $this->authorityPublicKey = Ed25519::rawPublicKeyFromSpkiBase64($publicKey);

        $clockTolerance = isset($options["clockToleranceSeconds"]) ? $options["clockToleranceSeconds"] : 60;
        if (!is_numeric($clockTolerance) || $clockTolerance < 0) {
            throw new \InvalidArgumentException("`clockToleranceSeconds` must be a number >= 0");
        }
        $this->clockToleranceSeconds = (int) $clockTolerance;

        $this->cache = new ResultCache(self::resolveCacheOptions($options["cache"] ?? true));

        $this->headerName = Constants::PUBLISHER_HEADER;
        $this->header = [Constants::PUBLISHER_HEADER, $this->headerValue];
        $this->tokenHeaderName = Constants::TOKEN_HEADER;
        $this->tokenHeaderNameLowercase = Constants::TOKEN_HEADER_LOWERCASE;
        $this->tokenHeaderServerKey = Constants::TOKEN_HEADER_SERVER_KEY;
    }

    /**
     * Factory mirroring the TypeScript `createPublisher(options)`.
     *
     * @param array<string,mixed> $options Keys:
     *   - `publisherId` (string, required) - `zapub_` + 24 alphanumerics.
     *   - `hostnames` (string|string[], required) - every host you serve; an apex covers its `www`.
     *   - `publicKey` (string, optional) - override the platform key. Staging and tests only.
     *   - `clockToleranceSeconds` (int, optional) - slack on expiry. Default 60.
     *   - `cache` (bool|array, optional) - `false` disables it; an array overrides parts. Default on.
     */
    public static function create(array $options): self
    {
        return new self($options);
    }

    /**
     * Verifies a visitor's token against one of this publisher's hostnames. Never throws on bad input; a
     * junk token is a result, not an exception. It throws in exactly one case: several hostnames are
     * configured and none was passed here.
     *
     * @param string|string[]|null $token    Raw header value. An array (a repeated header) uses its first.
     * @param string|null          $hostname Omit when exactly one hostname was configured; otherwise pass
     *                                        the request's host - a value outside the whitelist is rejected.
     */
    public function verify($token, ?string $hostname = null): VerificationResult
    {
        // A repeated header arrives as an array; the first wins, since a second token is not a merge case
        if (is_string($token)) {
            $value = $token;
        } elseif (is_array($token)) {
            $value = isset($token[0]) && is_string($token[0]) ? $token[0] : null;
        } else {
            $value = null;
        }

        $target = $hostname === null ? $this->soleHostname : Hostname::canonical($hostname);

        if ($target === null) {
            throw new \InvalidArgumentException(
                "This publisher serves several hostnames, so `verify()` needs one: "
                . '`verify($token, $_SERVER["HTTP_HOST"])`'
            );
        }

        if (!isset($this->allowed[$target])) {
            return Verify::toResult(["subscriber" => false, "reason" => Rejection::UNKNOWN_HOSTNAME], $target, false);
        }

        if ($value === null || $value === "") {
            return Verify::toResult(["subscriber" => false, "reason" => Rejection::MISSING], $target, false);
        }

        // Bounding the key length before it can reach the cache, alongside the exact-length check inside
        // `Token::read`, is what keeps a flood of oversized headers from being expensive
        if (strlen($value) !== Token::TOKEN_CHARACTERS) {
            return Verify::toResult(["subscriber" => false, "reason" => Rejection::MALFORMED], $target, false);
        }

        $now = (int) (microtime(true) * 1000);
        $key = $target . " " . $value;

        $cached = $this->cache->get($key, $now);
        if ($cached !== null) {
            return Verify::toResult($cached, $target, true);
        }

        $verdict = Verify::verifyToken(
            $value,
            $target,
            $this->authorityPublicKey,
            intdiv($now, 1000),
            $this->clockToleranceSeconds
        );

        if (Verify::isCacheable($verdict)) {
            $this->cache->set($key, $verdict, $now);
        }

        return Verify::toResult($verdict, $target, false);
    }

    /** @return array<string,int> `size`, `maxSize`, `hits`, `misses`, `evictions`. */
    public function cacheStats(): array
    {
        return $this->cache->stats();
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * @param bool|array $cache
     * @return array<string,mixed>
     */
    private static function resolveCacheOptions($cache): array
    {
        if ($cache === false) {
            return ["enabled" => false];
        }
        if ($cache === true) {
            return [];
        }
        if (is_array($cache)) {
            return $cache;
        }
        return [];
    }
}
