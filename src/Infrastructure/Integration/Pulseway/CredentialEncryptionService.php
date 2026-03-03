<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Integration\Pulseway;

/**
 * Encrypts and decrypts Pulseway API credentials using AES-256-CBC
 * with a key derived from WordPress auth salts.
 *
 * Secrets are stored as base64(iv + ciphertext) and never appear in plaintext
 * in logs, error messages, or database queries.
 */
final class CredentialEncryptionService
{
    private const CIPHER = 'aes-256-cbc';

    public function encrypt(string $plaintext): string
    {
        $key = $this->deriveKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $ciphertext);
    }

    public function decrypt(string $encrypted): string
    {
        $key = $this->deriveKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $data = base64_decode($encrypted, true);

        if ($data === false || strlen($data) < $ivLength) {
            throw new \RuntimeException('Decryption failed: invalid data');
        }

        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }

    private function deriveKey(): string
    {
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'pet-fallback-salt';
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'pet-fallback-key';

        return hash('sha256', $key . $salt . 'pet-pulseway', true);
    }
}
