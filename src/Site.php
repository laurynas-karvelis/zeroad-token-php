<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class Site
{
    public string $CLIENT_HEADER_NAME;
    public string $SERVER_HEADER_NAME;
    public string $SERVER_HEADER_VALUE;

    private ClientHeader $clientHeader;

    public function __construct($options)
    {
        $serverHeader = new ServerHeader($options);
        $this->clientHeader = new ClientHeader(Constants::ZEROAD_NETWORK_PUBLIC_KEY);

        $this->SERVER_HEADER_NAME  = $serverHeader->NAME;
        $this->SERVER_HEADER_VALUE = $serverHeader->VALUE;
        $this->CLIENT_HEADER_NAME  = $this->clientHeader->NAME;
    }

    /**
     * Parses a client token and returns a feature map
     */
    public function parseToken(?string $headerValue): array
    {
        return $this->clientHeader->parseToken($headerValue);
    }
}
