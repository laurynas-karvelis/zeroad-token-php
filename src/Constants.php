<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class Constants
{
    public const ZEROAD_NETWORK_PUBLIC_KEY = "MCowBQYDK2VwAyEAignXRaTQtxEDl4ThULucKNQKEEO2Lo5bEO8qKwjSDVs=";

    public const FEATURES = [
        /** Render no advertisements anywhere on the page */
        'ADS_OFF' => 1 << 0,
        /** Render no Cookie Consent screens (headers, footers or dialogs) on the page with complete OPT-OUT for non-functional trackers */
        'COOKIE_CONSENT_OFF' => 1 << 1,
        /** Render no marketing dialogs or popups such as newsletter, promotion etc. on the page */
        'MARKETING_DIALOG_OFF' => 1 << 2,
        /** Provide automatic access to otherwise paywalled content such as articles, news etc. */
        'CONTENT_PAYWALL_OFF' => 1 << 3,
        /** Provide automatic access to site features provided behind a SaaS at least the basic subscription plan */
        'SUBSCRIPTION_ACCESS_ON' => 1 << 4,
    ];

    public const SERVER_HEADERS = [
        'WELCOME' => 'X-Better-Web-Welcome',
    ];

    public const CLIENT_HEADERS = [
        'HELLO' => 'X-Better-Web-Hello',
    ];

    public const PROTOCOL_VERSION = [
        'V_1' => 1,
    ];

    public const CURRENT_PROTOCOL_VERSION = self::PROTOCOL_VERSION['V_1'];
}
