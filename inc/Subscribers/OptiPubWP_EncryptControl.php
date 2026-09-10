<?php

namespace OptiPub\Subscribers;

use Exception;
use OptiPub\Base\EncryptDecryptUtil;

/**
 * Encrypt and decrypt control class for WP Auth Token
 */
class OptiPubWP_EncryptControl
{
    /**
     * WordPress function to encrypt data compatible with Laravel
     */
    public function wp_laravel_encrypt_decrypt($value, bool $encrypt = true)
    {
        // OptiPub Data Encryption Key
        $key = EncryptDecryptUtil::getDecryptedToken('wp_auth');

        try {
            if ($encrypt) {
                return $this->laravelCompatibleEncrypt($value, $key);
            }

            return $this->laravelCompatibleDecrypt($value, $key);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Encrypt a value compatible with Laravel's Encrypter class
     */
    public function laravelCompatibleEncrypt($value, $key)
    {
        // Process the key
        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        } else {
            $key = base64_decode($key);
        }

        // Generate a random initialization vector
        $iv = random_bytes(16);

        // Encrypt the value - Laravel serializes the data before encryption
        $value = serialize($value);

        $encrypted = openssl_encrypt(
            $value,
            'AES-256-CBC',
            $key,
            0,
            $iv
        );

        if ($encrypted === false) {
            throw new Exception('Could not encrypt the data.');
        }

        // Create a MAC for security
        $mac = hash_hmac('sha256', base64_encode($iv) . $encrypted, $key);

        // Create the payload
        $json = wp_json_encode([
            'iv'    => base64_encode($iv),
            'value' => $encrypted,
            'mac'   => $mac,
        ]);

        return base64_encode($json);
    }

    /**
     * Decrypt a value encrypted with laravelCompatibleEncrypt
     */
    public function laravelCompatibleDecrypt($encrypted, $key)
    {
        // Process the key
        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        } else {
            $key = base64_decode($key);
        }

        // Decode the base64 encrypted payload
        $payload = json_decode(base64_decode($encrypted), true);

        // Check if JSON decoding succeeded
        if (!$payload || !isset($payload['iv']) || !isset($payload['value']) || !isset($payload['mac'])) {
            throw new Exception('The payload is invalid.');
        }

        // Get the IV, encrypted value, and MAC
        $iv = base64_decode($payload['iv']);
        $value = base64_decode($payload['value']);
        $mac = $payload['mac'];

        // Verify the MAC
        $calculatedMac = hash_hmac('sha256', $payload['iv'] . $payload['value'], $key, false);

        if (!hash_equals($calculatedMac, $mac)) {
            throw new Exception('The MAC is invalid.');
        }

        // Decrypt the value
        $decrypted = openssl_decrypt(
            $value,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new Exception('Could not decrypt the data.');
        }

        return $decrypted;
    }
}
