<?php

declare(strict_types=1);

namespace ZeroAd\Token;

use ZeroAd\Token\Headers\ClientHeader;
use ZeroAd\Token\Headers\ServerHeader;

class Site
{
  private $clientId;
  private $features;

  public $CLIENT_HEADER_NAME;
  public $SERVER_HEADER_NAME;
  public $SERVER_HEADER_VALUE;

  public function __construct(array $params)
  {
    if (!isset($params["clientId"]) || !is_string($params["clientId"]) || $params["clientId"] === "") {
      throw new \InvalidArgumentException("`clientId` must be a non-empty string.");
    }

    if (!isset($params["features"]) || !is_array($params["features"]) || count($params["features"]) === 0) {
      throw new \InvalidArgumentException("At least one site feature must be provided.");
    }

    $this->clientId = $params["clientId"];
    $this->features = $params["features"];

    $this->SERVER_HEADER_VALUE = ServerHeader::encodeServerHeader($params["clientId"], $params["features"]);
    $this->SERVER_HEADER_NAME = Constants::SERVER_HEADERS["WELCOME"];
    $this->CLIENT_HEADER_NAME = "HTTP_" . strtoupper(str_replace("-", "_", Constants::CLIENT_HEADERS["HELLO"]));
  }

  public function parseClientToken(?string $headerValue): array
  {
    return ClientHeader::parseClientToken($headerValue, [
      "clientId" => $this->clientId,
      "features" => $this->features
    ]);
  }
}
