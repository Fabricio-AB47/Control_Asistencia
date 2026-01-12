<?php
declare(strict_types=1);

namespace App\Security;

use PDO;

/**
 * Logger de auditoría para eventos de seguridad
 * OWASP A09:2021 - Logging & Monitoring Failures
 */
class AuditLogger
{
    private PDO $db;
    private int $userId;
    private string $logDir;

    public function __construct(PDO $db, int $userId = 0)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->logDir = dirname(__DIR__, 2) . '/logs';
        
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0750, true);
        }
    }

    /**
     * Registra intento de login
     */
    public function logLoginAttempt(string $email, bool $success, string $reason = ''): void
    {
        $this->log('LOGIN_ATTEMPT', [
            'email' => $email,
            'success' => $success,
            'reason' => $reason,
            'ip' => $this->getClientIP(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }

    /**
     * Registra cambios de contraseña
     */
    public function logPasswordChange(int $targetUserId): void
    {
        $this->log('PASSWORD_CHANGED', [
            'target_user_id' => $targetUserId,
            'changed_by' => $this->userId,
        ]);
    }

    /**
     * Registra acceso a funcionalidad sensible
     */
    public function logSensitiveAccess(string $action, array $details = []): void
    {
        $this->log('SENSITIVE_ACCESS', array_merge([
            'action' => $action,
            'ip' => $this->getClientIP(),
        ], $details));
    }

    /**
     * Registra intento de acceso denegado
     */
    public function logAccessDenied(string $resource, string $reason = ''): void
    {
        $this->log('ACCESS_DENIED', [
            'resource' => $resource,
            'reason' => $reason,
            'ip' => $this->getClientIP(),
        ]);
    }

    /**
     * Registra error de seguridad
     */
    public function logSecurityError(string $type, string $message): void
    {
        $this->log('SECURITY_ERROR', [
            'type' => $type,
            'message' => $message,
            'ip' => $this->getClientIP(),
        ]);
    }

    /**
     * Método privado para registrar eventos
     */
    private function log(string $eventType, array $data): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $schema = (function_exists('isMssql') && isMssql()) ? (dbSchema() . '.') : '';
        $nowFn = (function_exists('isMssql') && isMssql()) ? 'GETDATE()' : 'NOW()';
        
        // Intenta insertar en la base de datos (requiere tabla de auditoría)
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$schema}audit_log (user_id, event_type, event_data, ip_address, created_at)
                 VALUES (?, ?, ?, ?, {$nowFn})"
            );
            $stmt->execute([
                $this->userId,
                $eventType,
                json_encode($data),
                $this->getClientIP(),
            ]);
        } catch (\Exception $e) {
            // Falla silenciosa pero registra en archivo
        }

        // Siempre registra en archivo para redundancia
        $this->logToFile($eventType, $data, $timestamp);
    }

    /**
     * Registra en archivo (almacenamiento redundante)
     */
    private function logToFile(string $eventType, array $data, string $timestamp): void
    {
        $file = $this->logDir . '/audit-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] USER:%d EVENT:%s DATA:%s\n",
            $timestamp,
            $this->userId,
            $eventType,
            json_encode($data)
        );
        
        @error_log($line, 3, $file);
        // Restringe permisos del archivo de log
        @chmod($file, 0640);
    }

    /**
     * Obtiene IP del cliente de forma segura
     */
    private function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            return $ips[0] ?? '';
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
