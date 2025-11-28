<?php
declare(strict_types=1);

namespace ZeroAd\Token;

require_once __DIR__ . '/../constants.php';
require_once __DIR__ . '/../helpers.php';


class ServerHeader
{
    /** @var string Header name */
    public $name;

    /** @var string Header value */
    public $value;

    const SEPARATOR = '^';

    /**
     * Constructor
     *
     * @param array $options Either ['value' => string] or ['siteId' => string, 'features' => array]
     */
    public function __construct(array $options)
    {
        $this->name = SERVER_HEADERS::WELCOME;

        if (isset($options['value'])) {
            $this->value = $options['value'];
        } else {
            $siteId = $options['siteId'] ?? '';
            $features = $options['features'] ?? [];
            $this->value = $this->encode($siteId, $features);
        }
    }

    /**
     * Encode siteId and features into header value
     *
     * @param string $siteId UUID
     * @param array  $features Array of SITE_FEATURES constants
     * @return string
     */
    public function encode(string $siteId, array $features): string
    {
        $flags = setFeatures(0, $features);
        $encodedSiteId = uuidToBase64($siteId);

        return implode(self::SEPARATOR, [$encodedSiteId, CURRENT_PROTOCOL_VERSION, $flags]);
    }

    /**
     * Decode header value into structured array
     *
     * @param string|null $headerValue
     * @return array|null ['version'=>int, 'flags'=>int, 'siteId'=>string] or null if invalid
     */
    public static function decode(?string $headerValue): ?array
    {
        if (empty($headerValue)) {
            return null;
        }

        try {
            $parts = explode(self::SEPARATOR, $headerValue);
            assert(count($parts) === 3, "Invalid header value format");

            list($encodedSiteId, $protocolVersion, $flags) = $parts;

            assert(in_array((int)$protocolVersion, [PROTOCOL_VERSION::V_1], true), "Invalid or unsupported protocol version");

            $siteId = base64ToUuid($encodedSiteId);
            assert(strlen($siteId) === 36, "Invalid siteId value");

            // Optional: validate flags for V_1
            if ((int)$protocolVersion === PROTOCOL_VERSION::V_1) {
                assert(in_array((int)$flags, range(1, 7), true), "Invalid flags value");
            }

            return [
                'version' => (int)$protocolVersion,
                'flags'   => (int)$flags,
                'siteId'  => $siteId,
            ];
        } catch (Exception $e) {
            logger('warn', 'Could not decode server header value', ['reason' => $e->getMessage()]);
            return null;
        }
    }
}
