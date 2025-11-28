<?php
declare(strict_types=1);

namespace ZeroAd\Token;

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/headers/server.php';
require_once __DIR__ . '/headers/client.php';

/**
 * Site class to enable convenient Server Header injection and Client Header parsing
 */
class Site
{
    /** @var ServerHeader */
    private $serverHeader;

    /** @var ClientHeader */
    private $clientHeader;

    /** @var self|null Default site instance */
    private static $defaultSite = null;

    /**
     * Constructor
     *
     * @param array $serverHeaderOptions ['value' => string] or ['siteId' => string, 'features' => array]
     */
    public function __construct(array $serverHeaderOptions)
    {
        $this->serverHeader = new ServerHeader($serverHeaderOptions);
        $this->clientHeader = new ClientHeader(ZEROAD_NETWORK_PUBLIC_KEY);
    }

    /**
     * Initialize default site instance
     *
     * @param array $options
     */
    public static function init(array $options): void
    {
        self::$defaultSite = new self($options);
    }

    /**
     * Process client header value
     *
     * @param string|null $headerValue
     * @return array
     */
    public static function processRequest(?string $headerValue): array
    {
        self::assertInitialized();
        return self::$defaultSite->clientHeader->processRequest($headerValue);
    }

    /**
     * Get client header name
     *
     * @return string
     */
    public static function getClientHeaderName(): string
    {
        self::assertInitialized();
        return self::$defaultSite->clientHeader->name;
    }

    /**
     * Get server header name
     *
     * @return string
     */
    public static function getServerHeaderName(): string
    {
        self::assertInitialized();
        return self::$defaultSite->serverHeader->name;
    }

    /**
     * Get server header value
     *
     * @return string
     */
    public static function getServerHeaderValue(): string
    {
        self::assertInitialized();
        return self::$defaultSite->serverHeader->value;
    }

    /**
     * Ensure default site has been initialized
     *
     * @throws RuntimeException
     */
    private static function assertInitialized(): void
    {
        if (self::$defaultSite === null) {
            throw new \RuntimeException("Site not initialized. Call Site::init() first.");
        }
    }
}
