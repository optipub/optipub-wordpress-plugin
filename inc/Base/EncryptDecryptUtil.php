<?php

namespace OptiPub\Base;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;

/**
 * Encrypt and decrypt utility class
 */
class EncryptDecryptUtil
{
    /**
     * Encrypts and saves API token
     *
     * @param string $token The token to encrypt and save
     * @param string $type The type of token ('api' or 'wp_auth')
     * @return bool Whether the operation was successful
     */
    public static function setEncryptedToken(string $token, string $type = ''): bool
    {
        // Set the correct option name based on token type
        if ($type === 'api') {
            $option_name = Constants::OPTIPUB_API_TOKEN;
        } elseif ($type === 'wp_auth') {
            $option_name = Constants::OPTIPUB_WP_AUTH_TOKEN;
        } else {
            BaseController::logStaticError("Invalid token type: {$type}\n");

            return false;
        }

        // Set the correct option name based on token type
        $option_name = $type === 'api' ? Constants::OPTIPUB_API_TOKEN : Constants::OPTIPUB_WP_AUTH_TOKEN;

        // Check if encryption key is defined
        if (!defined(Constants::OPTIPUB_KEY_NAME)) {
            BaseController::logStaticError("Encryption key OPTIPUB_KEY is not defined in wp-config.php\n");

            return false;
        }

        try {
            $key = Key::loadFromAsciiSafeString(OPTIPUB_KEY);
            $encryptedToken = Crypto::encrypt($token, $key);

            return update_option($option_name, $encryptedToken);
        } catch (\Exception $exception) {
            BaseController::logStaticError('Error during encrypting token: ' . $type . ' - ' . $exception->getMessage() . "\n");

            return false;
        }
    }

    /**
     * Gets decrypted token
     *
     * @param string $type The type of token ('api' or 'wp_auth')
     * @return string The decrypted token or empty string on failure
     */
    public static function getDecryptedToken(string $type = ''): string
    {
        try {
            // Set the correct option name based on token type
            if ($type === 'api') {
                $encryptedToken = get_option(Constants::OPTIPUB_API_TOKEN);
            } elseif ($type === 'wp_auth') {
                $encryptedToken = get_option(Constants::OPTIPUB_WP_AUTH_TOKEN);
            } else {
                BaseController::logStaticError("Invalid token type: {$type}\n");

                return '';
            }

            if (!$encryptedToken || !defined(Constants::OPTIPUB_KEY_NAME)) {
                if (get_option(Constants::OPTIPUB_SYNC_HAS_RUN) && $type === 'api') {
                    BaseController::logStaticError("Could not find encrypted token for type: {$type}\n");
                }

                return '';
            }

            $key = Key::loadFromAsciiSafeString(OPTIPUB_KEY);

            return Crypto::decrypt($encryptedToken, $key);
        } catch (WrongKeyOrModifiedCiphertextException $exception) {
            BaseController::logStaticError('Could not decrypt token: ' . $type . ' - ' . $exception->getMessage() . "\n");

            return '';
        } catch (\Exception $exception) {
            BaseController::logStaticError('Error during decryption: ' . $type . ' - ' . $exception->getMessage() . "\n");

            return '';
        }
    }
}
