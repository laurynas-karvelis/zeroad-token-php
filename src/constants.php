<?php
declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * This is an official ZeroAd Network public key.
 * Used to verify `X-Better-Web-User` header values are not tampered with.
 */
define('ZEROAD_NETWORK_PUBLIC_KEY', 'MCowBQYDK2VwAyEAignXRaTQtxEDl4ThULucKNQKEEO2Lo5bEO8qKwjSDVs=');

class SITE_FEATURES
{
    const AD_LESS_EXPERIENCE = 1 << 0;
    const PREMIUM_CONTENT_ACCESS = 1 << 1;
    const VIP_EXPERIENCE = 1 << 2;
}

class SERVER_HEADERS
{
    const WELCOME = 'X-Better-Web-Welcome';
}

class CLIENT_HEADERS
{
    const HELLO = 'X-Better-Web-Hello';
}

class PROTOCOL_VERSION
{
    const V_1 = 1;
}

define('CURRENT_PROTOCOL_VERSION', PROTOCOL_VERSION::V_1);