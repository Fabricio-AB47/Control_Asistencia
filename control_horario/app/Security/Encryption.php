<?php
declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Servicio de encriptación para datos sensibles
 * Utiliza sodium (libsodium) cuando está disponible, fallback a openssl
 */
class Encryption
{
    private static ?string $encryptionKey = null;

    /**
     * Inicializa la clave de encriptación desde variables de entorno
     */
    public static function initialize(?string $key = null): void
    {
        if ($key !== null) {
            self::$encryptionKey = $key;
            return;
        }

        if (function_exists('loadDotEnv')) {
            $env = loadDotEnv();
            $key = $env['ENCRYPTION_KEY'] ?? null;
        } else {
            $key = $_ENV['ENCRYPTION_KEY'] ?? null;
        }

        if (!$key) {
            throw new RuntimeException('ENCRYPTION_KEY no está configurada en .env');
        }

        self::$encryptionKey = $key;
    }

    /**
     * Encripta datos sensibles
     */
    public static function encrypt(string $plaintext): string
    {
        if (!self::$encryptionKey) {
            self::initialize();
        }

        // Intenta usar sodium si está disponible
        if (function_exists('sodium_crypto_secretbox')) {
            return self::encryptWithSodium($plaintext);
        }

        // Fallback a openssl
        return self::encryptWithOpenSSL($plaintext);
    }

    /**
     * Desencripta datos sensibles
     */
    public static function decrypt(string $ciphertext): string
    {
        if (!self::$encryptionKey) {
            self::initialize();
        }

        // Detecta formato (sodium usa formato específico)
        if (strpos($ciphertext, 'sodium:') === 0) {
            return self::decryptWithSodium($ciphertext);
        }

        return self::decryptWithOpenSSL($ciphertext);
    }

    private static function encryptWithSodium(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox(
            $plaintext,
            $nonce,
            self::deriveKeyFromPassword(self::$encryptionKey)
        );
        return 'sodium:' . base64_encode($nonce . $ciphertext);
    }

    private static function decryptWithSodium(string $ciphertext): string
    {
        $ciphertext = substr($ciphertext, 7);
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false) {
            throw new RuntimeException('Datos encriptados inválidos');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open(
            $encrypted,
            $nonce,
            self::deriveKeyFromPassword(self::$encryptionKey)
        );

        if ($plaintext === false) {
            throw new RuntimeException('Error al desencriptar datos');
        }

        return $plaintext;
    }

    private static function encryptWithOpenSSL(string $plaintext): string
    {
        $cipher = 'aes-256-gcm';
        $iv = openssl_random_pseudo_bytes(12);
        $key = hash('sha256', self::$encryptionKey, true);

        $ciphertext = openssl_encrypt(
            $plaintext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return 'openssl:' . base64_encode($iv . $ciphertext . $tag);
    }

    private static function decryptWithOpenSSL(string $ciphertext): string
    {
        $ciphertext = substr($ciphertext, 8);
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false) {
            throw new RuntimeException('Datos encriptados inválidos');
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, -16);
        $encrypted = substr($decoded, 12, -16);
        $key = hash('sha256', self::$encryptionKey, true);
        $cipher = 'aes-256-gcm';

        $plaintext = openssl_decrypt(
            $encrypted,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Error al desencriptar datos');
        }

        return $plaintext;
    }

    private static function deriveKeyFromPassword(string $password): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            base64_decode('SodiumChloride'), // OWASP recomendación
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE
        );
    }
}
