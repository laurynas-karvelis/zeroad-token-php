<?php
declare(strict_types=1);

namespace ZeroAd\Token;

/**
 * Convert UUID string to Base64
 */
function uuidToBase64(string $uuid): string
{
    $hex = str_replace('-', '', $uuid);
    $bin = hex2bin($hex);
    return base64_encode($bin);
}

/**
 * Convert Base64 string to UUID string
 */
function base64ToUuid(string $base64): string
{
    $bin = base64_decode($base64, true);
    $hex = bin2hex($bin);
    return substr($hex, 0, 8) . '-' .
           substr($hex, 8, 4) . '-' .
           substr($hex, 12, 4) . '-' .
           substr($hex, 16, 4) . '-' .
           substr($hex, 20, 12);
}

/**
 * Convert 4-byte big-endian binary string to DateTime object
 *
 * @param string $bytes 4-byte binary string
 * @return \DateTime
 */
function bytesToUnixTimestamp(string $bytes): \DateTime
{
    if (strlen($bytes) !== 4) {
        throw new \InvalidArgumentException("Expected 4-byte string for timestamp");
    }
    $arr = unpack('N', $bytes);
    $timestamp = $arr[1];
    return (new \DateTime())->setTimestamp($timestamp);
}

/**
 * Assert a condition, throw exception if false
 *
 * @param mixed $value
 * @param string $message
 * @throws \Exception
 */
function assertValue($value, string $message)
{
    if (!$value) {
        throw new \Exception($message);
    }
}

/**
 * Check if a specific feature flag is set
 *
 * @param int $flags
 * @param int $feature
 * @return bool
 */
function hasFeature(int $flags, int $feature): bool
{
    return (bool)($flags & $feature);
}

/**
 * Clear a specific feature flag
 *
 * @param int $flags
 * @param int $feature
 * @return int
 */
function clearFeature(int $flags, int $feature): int
{
    return $flags & (~$feature);
}

/**
 * Toggle a specific feature flag
 *
 * @param int $flags
 * @param int $feature
 * @return int
 */
function toggleFeature(int $flags, int $feature): int
{
    return $flags ^ $feature;
}

/**
 * Set multiple feature flags
 *
 * @param int $flags
 * @param int[] $features
 * @return int
 */
function setFeatures(int $flags = 0, array $features = []): int
{
    foreach ($features as $feature) {
        $flags |= $feature;
    }
    return $flags;
}

/**
 * Minimal logger stub
 */
function logger(string $level, string $message, array $context = [])
{
    error_log(strtoupper($level) . ': ' . $message . ' ' . json_encode($context));
}
