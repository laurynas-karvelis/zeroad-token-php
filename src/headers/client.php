<?php
declare(strict_types=1);

namespace ZeroAd\Token;

require_once __DIR__ . '/../constants.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../crypto.php';


class ClientHeader
{
    private $cryptoPublicKey;
    private $publicKey;

    public $name;

    const NONCE_BYTES = 4;
    const SEPARATOR = '.';

    /**
     * Constructor
     *
     * @param string      $publicKey Base64-encoded public key
     */
    public function __construct(string $publicKey)
    {
        $this->publicKey = $publicKey;
        $this->name = CLIENT_HEADERS::HELLO;
    }

    /**
     * Decode and verify client header
     *
     * @param string|null $headerValue
     * @return array|null ['version'=>int, 'expiresAt'=>DateTime, 'expired'=>bool, 'flags'=>int] or null
     */
    public function decode(?string $headerValue): ?array
    {
        if (empty($headerValue)) {
            return null;
        }

        if (!$this->cryptoPublicKey) {
            $this->cryptoPublicKey = Crypto::importPublicKey($this->publicKey);
        }

        try {
            $parts = explode(self::SEPARATOR, $headerValue);
            if (count($parts) !== 2) {
                throw new \RuntimeException("Invalid client header format");
            }

            list($dataB64, $sigB64) = $parts;
            $data = base64_decode($dataB64, true);
            $signature = base64_decode($sigB64, true);

            if (!Crypto::verify($data, $signature, $this->cryptoPublicKey)) {
                throw new \RuntimeException("Forged header value provided");
            }

            $version = ord($data[0]);

            if ($version === PROTOCOL_VERSION::V_1) {
                $flagsByte = ord($data[strlen($data) - 1]);
                $expiresBytes = substr($data, 5, 4); // bytes 5..8
                $expiresAt = bytesToUnixTimestamp($expiresBytes);
                $expired = $expiresAt->getTimestamp() < (new \DateTime())->getTimestamp();

                return [
                    'version'   => $version,
                    'expiresAt' => $expiresAt,
                    'expired'   => $expired,
                    'flags'     => $flagsByte
                ];
            }

        } catch (\Exception $e) {
            logger('warn', 'Could not decode client header value', ['reason' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Process request token header
     *
     * @param string|null $headerValue
     * @return array
     */
    public function processRequest(?string $headerValue): array
    {
        $data = $this->decode($headerValue);

        return [
            '_raw' => $data,
            'shouldRemoveAds' => $this->shouldRemoveAds($data),
            'shouldEnableVipExperience' => $this->shouldEnableVipExperience($data),
            'shouldEnablePremiumContentAccess' => $this->shouldEnablePremiumContentAccess($data)
        ];
    }

    private function testFeature(?array $data, int $feature): bool
    {
        return $data && !$data['expired'] && hasFeature($data['flags'], $feature);
    }

    private function shouldRemoveAds(?array $data): bool
    {
        return $this->testFeature($data, SITE_FEATURES::AD_LESS_EXPERIENCE);
    }

    private function shouldEnableVipExperience(?array $data): bool
    {
        return $this->testFeature($data, SITE_FEATURES::VIP_EXPERIENCE);
    }

    private function shouldEnablePremiumContentAccess(?array $data): bool
    {
        return $this->testFeature($data, SITE_FEATURES::PREMIUM_CONTENT_ACCESS);
    }
}
