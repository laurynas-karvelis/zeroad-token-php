<?php
declare(strict_types=1);

namespace ZeroAd\Token;

class Crypto
{
    /**
     * Generate an Ed25519 key pair and return base64-encoded strings.
     */
    public static function generateKeys(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        // cspell:words secretkey
        $privateKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        return [
            'privateKey' => base64_encode($privateKey),
            'publicKey' => base64_encode($publicKey),
        ];
    }

    /**
     * Import a private key from base64 string.
     */
    public static function importPrivateKey(string $privateKeyBase64): string
    {
        $key = base64_decode($privateKeyBase64, true);
        // cspell:words SECRETKEYBYTES
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException("Invalid private key");
        }
        return $key;
    }

    /**
     * Import public key from Base64
     *
     * @param string $publicKeyBase64
     * @return string Raw key binary
     */
    public static function importPublicKey(string $publicKeyBase64): string
    {
        try {
            $der = base64_decode($publicKeyBase64, true);
            if ($der === false) {
                throw new \InvalidArgumentException("Invalid Base64 public key");
            }

            $pem = self::spkiDerToPem($der);
            $pkey = openssl_pkey_get_public($pem);
            if ($pkey === false) {
                throw new \RuntimeException("Could not parse SPKI public key");
            }

            $details = openssl_pkey_get_details($pkey);
            if ($details === false || !isset($details['key'])) {
                throw new \RuntimeException("Could not extract public key details");
            }

            return self::pemToRawEd25519Public($details['key']);
            
        } catch(\Exception $e) {
            $key = base64_decode($publicKeyBase64, true);
            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                throw new \InvalidArgumentException("Invalid public key");
            }
            return $key;
        }
        
    }

    /**
     * Verify signature with Ed25519 public key
     *
     * @param string $data Binary string
     * @param string $signature Binary signature
     * @param string $publicKey Raw public key binary
     * @return bool
     */
    public static function verify(string $data, string $signature, string $publicKey): bool
    {
        return sodium_crypto_sign_verify_detached($signature, $data, $publicKey);
    }

    /**
     * Convert PEM Ed25519 public key to raw 32-byte key
     *
     * @param string $pem
     * @return string
     */
    private static function pemToRawEd25519Public(string $pem): string
    {
        // Remove PEM headers/footers
        $pem = preg_replace('/-----.*?-----/', '', $pem);
        $der = base64_decode($pem, true);
        if ($der === false) {
            throw new \RuntimeException("Invalid PEM encoding");
        }

        // For Ed25519 SPKI, the last 32 bytes of DER are the public key
        // cspell:words PUBLICKEYBYTES
        $raw = substr($der, -SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);

        if (strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \RuntimeException("Invalid public key length: " . strlen($raw));
        }

        return $raw;
    }

    /**
     * Converts SPKI DER encoded key to PEM
     */
    private static function spkiDerToPem(string $der): string
    {
        $base64 = chunk_split(base64_encode($der), 64);
        return "-----BEGIN PUBLIC KEY-----\n{$base64}-----END PUBLIC KEY-----\n";
    }
}