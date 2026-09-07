<?php

declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Wire-level constants shared by every part of the SDK. These mirror the TypeScript reference
 * (`@zeroad.network/token`) byte-for-byte, so a token minted for one verifies on the other.
 */
class Constants
{
    /**
     * Official Zero Ad Network authority public key, in base64 SPKI DER (Ed25519).
     *
     * Every token a browser extension sends is anchored to this key. Publishers verify against a local
     * copy of it and never call the platform during request handling - verification is fully offline.
     */
    public const AUTHORITY_PUBLIC_KEY = "MCowBQYDK2VwAyEAignXRaTQtxEDl4ThULucKNQKEEO2Lo5bEO8qKwjSDVs=";

    /**
     * Response header a publisher sets on every page, announcing that this site participates in the
     * network. The value is the publisher ID, which is how the platform credits the site for the visit.
     */
    public const PUBLISHER_HEADER = "Better-Web-Publisher";

    /**
     * Every publisher ID carries this prefix, e.g. `zapub_7Fq2xR9nKd...`. The prefix makes the id
     * self-describing and lets a content-scanning extension tell a real id from arbitrary page text.
     */
    public const PUBLISHER_ID_SCHEME = "zapub_";

    /** Request header the browser extension attaches, carrying the visitor's subscription token. */
    public const TOKEN_HEADER = "Better-Web-Token";

    /** Lowercased `TOKEN_HEADER`, the form most frameworks hand you on the request. */
    public const TOKEN_HEADER_LOWERCASE = "better-web-token";

    /**
     * The `$_SERVER` key PHP exposes the token header under: request headers become `HTTP_*`, uppercased,
     * with dashes turned into underscores. `Better-Web-Token` -> `HTTP_BETTER_WEB_TOKEN`.
     */
    public const TOKEN_HEADER_SERVER_KEY = "HTTP_BETTER_WEB_TOKEN";

    /** Wire format version. Bumped only for a breaking change to the token byte layout. */
    public const PROTOCOL_VERSION = 1;

    /**
     * Subscription plans. Only `FREEDOM` exists today - it entitles the visitor to an ad-free,
     * tracker-free, consent-dialog-free page and to any content the site keeps behind a paywall.
     *
     * The plan travels as a single byte, so 254 more can be added without a format change. Treat an
     * unrecognised plan as "not entitled" rather than as an error.
     */
    public const PLAN = [
        "FREEDOM" => 1,
    ];

    /** Human-readable plan names, keyed by plan byte, for logs and templates. */
    public const PLAN_NAME = [
        1 => "Freedom",
    ];
}
