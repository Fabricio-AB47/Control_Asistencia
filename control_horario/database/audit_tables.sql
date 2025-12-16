-- ============================================================================
-- CONTROL HORARIO - TABLAS DE SEGURIDAD Y AUDITORÍA
-- ============================================================================
-- Ejecuta este script en tu base de datos para agregar tablas de auditoría

-- Tabla para registrar logs de auditoría
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 0,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_event (user_id, event_type),
    INDEX idx_created (created_at),
    CONSTRAINT fk_audit_log_user FOREIGN KEY (user_id) 
        REFERENCES usuario(id_usuario) 
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para intentos de acceso fallidos
CREATE TABLE IF NOT EXISTS failed_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    attempt_type VARCHAR(50) NOT NULL DEFAULT 'login',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_type (identifier, attempt_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para tokens de recuperación de contraseña
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_expires (user_id, expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) 
        REFERENCES usuario(id_usuario) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para sesiones de dos factores
CREATE TABLE IF NOT EXISTS two_factor_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    method ENUM('email', 'sms') DEFAULT 'email',
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_expires (user_id, expires_at),
    CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) 
        REFERENCES usuario(id_usuario) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para registrar cambios de datos de usuario (auditoría de cambios)
CREATE TABLE IF NOT EXISTS usuario_changes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    changed_by INT,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    change_type ENUM('CREATE', 'UPDATE', 'DELETE') DEFAULT 'UPDATE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_changed (user_id, changed_by),
    INDEX idx_created (created_at),
    CONSTRAINT fk_usuario_changes_user FOREIGN KEY (user_id) 
        REFERENCES usuario(id_usuario) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para rastrear sesiones activas
CREATE TABLE IF NOT EXISTS active_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    logout_at TIMESTAMP NULL,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id),
    CONSTRAINT fk_active_sessions_user FOREIGN KEY (user_id) 
        REFERENCES usuario(id_usuario) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para políticas de contraseña
CREATE TABLE IF NOT EXISTS password_policy (
    id INT PRIMARY KEY DEFAULT 1,
    min_length INT DEFAULT 8,
    require_uppercase BOOLEAN DEFAULT TRUE,
    require_lowercase BOOLEAN DEFAULT TRUE,
    require_numbers BOOLEAN DEFAULT TRUE,
    require_special_chars BOOLEAN DEFAULT FALSE,
    special_chars VARCHAR(50) DEFAULT '!@#$%^&*()',
    expiration_days INT DEFAULT 90,
    history_count INT DEFAULT 5,
    lockout_attempts INT DEFAULT 5,
    lockout_minutes INT DEFAULT 15,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores por defecto para política de contraseña
INSERT IGNORE INTO password_policy VALUES (1, 8, TRUE, TRUE, TRUE, FALSE, '!@#$%^&*()', 90, 5, 5, 15, NOW());

-- Agregar columnas de auditoría a tabla usuario si no existen
ALTER TABLE usuario 
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS is_locked BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL;

-- Crear índices para mejorar rendimiento
ALTER TABLE usuario ADD INDEX IF NOT EXISTS idx_correo (correo);
ALTER TABLE usuario ADD INDEX IF NOT EXISTS idx_cedula (cedula);
ALTER TABLE usuario ADD INDEX IF NOT EXISTS idx_last_login (last_login);

-- ============================================================================
-- FIN DEL SCRIPT DE AUDITORÍA
-- ============================================================================
