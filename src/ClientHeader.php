<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class ClientHeader
{
    private const VERSION_BYTES = 1;
    private const NONCE_BYTES = 4;
    private const SEPARATOR = '.';

    private string $publicKey;
    private ?string $privateKey;
    private ?string $cryptoPublicKey = null;
    private ?string $cryptoPrivateKey = null;

    public string $NAME;

    public function __construct(string $publicKey, ?string $privateKey = null)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->NAME = 'HTTP_' . strtoupper(str_replace('-', '_', Constants::CLIENT_HEADERS['HELLO']));
    }

    public function parseToken(?string $headerValue): array
    {
        $data = $this->decode($headerValue);
        $result = [];

        if (empty($data) || $data['expiresAt']->getTimestamp() < (new \DateTime())->getTimestamp()) {
            foreach (Constants::FEATURES as $key => $bit) {
                $result[$key] = false;
            }
            return $result;
        }

        foreach (Constants::FEATURES as $key => $bit) {
            $result[$key] = $data ? Helpers::hasFeature($data['flags'], $bit) : false;
        }
        return $result;
    }

    public function decode(?string $headerValue): ?array
    {
        if (empty($headerValue)) {
            return null;
        }

        if (!$this->cryptoPublicKey) {
            $this->cryptoPublicKey = Crypto::importPublicKey($this->publicKey);
        }

        try {
            [$dataB64, $sigB64] = explode(self::SEPARATOR, $headerValue);
            $data = base64_decode($dataB64);
            $signature = base64_decode($sigB64);

            if (!Crypto::verify($data, $signature, $this->cryptoPublicKey)) {
                throw new \RuntimeException("Forged header value provided");
            }

            $version = ord($data[0]);

            if ($version === Constants::CURRENT_PROTOCOL_VERSION) {
                $expiresAt = unpack('V', substr($data, self::VERSION_BYTES + self::NONCE_BYTES, 4))[1];
                $flags = unpack('V', substr($data, -4))[1];

                return [
                    'version' => $version,
                    'expiresAt' => (new \DateTime())->setTimestamp($expiresAt),
                    'flags' => $flags
                ];
            }
        } catch (\Exception $e) {
            Helpers::logger('warn', 'Could not decode client header value', ['reason' => $e->getMessage()]);
            return null;
        }
    }

    public function encode(int $version, \DateTime $expiresAt, array $features): string
    {
        if (!$this->privateKey) {
            throw new \RuntimeException("Private key is required for encoding");
        }

        $data = random_bytes(self::NONCE_BYTES);
        $data = chr($version) . $data;
        $data .= pack('V', $expiresAt->getTimestamp());
        $data .= pack('V', Helpers::setFeatures(0, $features));

        $signature = sodium_crypto_sign_detached($data, base64_decode($this->privateKey));
        return base64_encode($data) . self::SEPARATOR . base64_encode($signature);
    }
}
