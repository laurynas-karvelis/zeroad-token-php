<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class Constants
{
  /**
   * Official Zero Ad Network public key.
   * Used to verify that `X-Better-Web-Hello` header values are authentic
   * and have not been tampered with.
   */
  public const ZEROAD_NETWORK_PUBLIC_KEY = "MCowBQYDK2VwAyEAignXRaTQtxEDl4ThULucKNQKEEO2Lo5bEO8qKwjSDVs=";

  public const FEATURES = [
    /** Disable all advertisements on the page */
    "ADS_OFF" => 1 << 0,

    /** Disable all Cookie Consent screens (headers, footers, or dialogs)
     *   and fully opt out of non-functional trackers */
    "COOKIE_CONSENT_OFF" => 1 << 1,

    /** Disable all marketing dialogs or popups (e.g., newsletters, promotions) */
    "MARKETING_DIALOG_OFF" => 1 << 2,

    /** Provide automatic access to content that is normally paywalled */
    "CONTENT_PAYWALL_OFF" => 1 << 3,

    /** Grant automatic access to site features behind a SaaS,
     *   at least the basic subscription plan */
    "SUBSCRIPTION_ACCESS_ON" => 1 << 4,
  ];

  public const SERVER_HEADERS = [
    "WELCOME" => "X-Better-Web-Welcome",
  ];

  public const CLIENT_HEADERS = [
    "HELLO" => "X-Better-Web-Hello",
  ];

  public const PROTOCOL_VERSION = [
    "V_1" => 1,
  ];

  public const CURRENT_PROTOCOL_VERSION = self::PROTOCOL_VERSION["V_1"];
}
