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

  /**
   * IMPORTANT: Requirements listed for each feature class MUST be fulfilled fully.
   * Failure to comply will result in the site getting banned from Zero Ad Network platform.
   */
  public const FEATURES = [
    /**
     * Feature requirements:
     *  - Disable all advertisements on the page;
     *  - Disable all Cookie Consent screens (headers, footers, or dialogs);
     *  - Fully opt out the user of non-functional trackers;
     *  - Disable all marketing dialogs or popups (newsletters, promotions, etc.).
     */
    "CLEAN_WEB" => 1 << 0,

    /**
     * Feature requirements:
     *  - Provide Free access to content behind a paywall (news, articles, etc.);
     *  - Provide Free access to your base subscription plan (if subscription model is present).
     */
    "ONE_PASS" => 1 << 1
  ];

  public const SERVER_HEADERS = [
    "WELCOME" => "X-Better-Web-Welcome"
  ];

  public const CLIENT_HEADERS = [
    "HELLO" => "X-Better-Web-Hello"
  ];

  public const PROTOCOL_VERSION = [
    "V_1" => 1
  ];

  public const CURRENT_PROTOCOL_VERSION = self::PROTOCOL_VERSION["V_1"];
}
