<?php
declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Validador centralizado de entrada OWASP A03:2021
 * Implementa validación whitelist y sanitización
 */
class InputValidator
{
    /**
     * Valida y sanitiza email
     */
    public static function validateEmail(string $email): string
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email inválido');
        }
        if (strlen($email) > 254) {
            throw new RuntimeException('Email demasiado largo');
        }
        return strtolower($email);
    }

    /**
     * Valida contraseña (requiere complejidad mínima)
     */
    public static function validatePassword(string $password): string
    {
        if (strlen($password) < 8) {
            throw new RuntimeException('La contraseña debe tener al menos 8 caracteres');
        }
        if (strlen($password) > 128) {
            throw new RuntimeException('La contraseña es demasiado larga');
        }
        // Requiere mayúsculas, minúsculas, números
        if (!preg_match('/[A-Z]/', $password)) {
            throw new RuntimeException('Debe contener al menos una mayúscula');
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new RuntimeException('Debe contener al menos una minúscula');
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new RuntimeException('Debe contener al menos un número');
        }
        return $password;
    }

    /**
     * Valida cédula
     */
    public static function validateCedula(string $cedula): string
    {
        $cedula = preg_replace('/[^0-9]/', '', $cedula);
        if (strlen($cedula) < 8 || strlen($cedula) > 13) {
            throw new RuntimeException('Cédula inválida');
        }
        return $cedula;
    }

    /**
     * Valida nombre/apellido
     */
    public static function validateName(string $name): string
    {
        $name = trim($name);
        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new RuntimeException('Nombre debe tener entre 2 y 100 caracteres');
        }
        // Permite letras, espacios y algunos diacríticos
        if (!preg_match('/^[\p{L}\s\-\.\']{2,100}$/u', $name)) {
            throw new RuntimeException('Nombre contiene caracteres inválidos');
        }
        return $name;
    }

    /**
     * Valida identificador de usuario (entero positivo)
     */
    public static function validateUserId($id): int
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new RuntimeException('ID de usuario inválido');
        }
        return $id;
    }

    /**
     * Valida fecha en formato Y-m-d
     */
    public static function validateDate(string $date): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new RuntimeException('Fecha inválida');
        }
        return $date;
    }

    /**
     * Valida latitud
     */
    public static function validateLatitude($lat): float
    {
        $lat = (float)$lat;
        if ($lat < -90 || $lat > 90) {
            throw new RuntimeException('Latitud inválida');
        }
        return round($lat, 6);
    }

    /**
     * Valida longitud
     */
    public static function validateLongitude($lon): float
    {
        $lon = (float)$lon;
        if ($lon < -180 || $lon > 180) {
            throw new RuntimeException('Longitud inválida');
        }
        return round($lon, 6);
    }

    /**
     * Valida dirección (texto corto)
     */
    public static function validateAddress(string $address): string
    {
        $address = trim($address);
        if (strlen($address) > 255) {
            throw new RuntimeException('Dirección demasiado larga');
        }
        // Básicamente previene inyección pero permite caracteres comunes
        if (preg_match('/<|>|"|\'|;|&|`|~/', $address)) {
            throw new RuntimeException('Dirección contiene caracteres no permitidos');
        }
        return $address;
    }

    /**
     * Valida que sea string y no está vacío
     */
    public static function validateString(string $value, int $minLen = 1, int $maxLen = 255): string
    {
        $value = trim($value);
        if (strlen($value) < $minLen || strlen($value) > $maxLen) {
            throw new RuntimeException("Valor debe tener entre {$minLen} y {$maxLen} caracteres");
        }
        return $value;
    }

    /**
     * Valida que sea uno de los valores permitidos
     */
    public static function validateEnum(string $value, array $allowed): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException('Valor no permitido');
        }
        return $value;
    }
}
