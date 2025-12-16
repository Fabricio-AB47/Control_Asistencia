# GUÍA DE IMPLEMENTACIÓN RÁPIDA

## Pasos para activar todas las mejoras de seguridad

### 1. Preparación (5 minutos)

```bash
# Crear directorio de logs
mkdir -p logs
chmod 750 logs

# Copiar configuración
cp .env.example .env

# Generar clave de encriptación
php -r "echo 'ENCRYPTION_KEY=' . bin2hex(random_bytes(32)) . PHP_EOL;"
# Copiar el output anterior y agregar a .env
```

### 2. Configurar .env (2 minutos)

Editar `.env` con tus valores:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=control_horario_db
DB_USER=root
DB_PASSWORD=tu_contraseña

APP_DEBUG=0  # IMPORTANTE: 0 en producción
APP_BASE_PATH=/Control_Asistencia/control_horario
FORCE_SECURE_COOKIES=1  # Si tienes HTTPS

ENCRYPTION_KEY=tu_clave_generada_arriba

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_correo@gmail.com
MAIL_PASSWORD=tu_contraseña_app
```

### 3. Base de datos (1 minuto)

```bash
# Ejecutar script de auditoría en tu BD
mysql -u root -p tu_base_de_datos < database/audit_tables.sql
```

### 4. Permisos de archivos (1 minuto)

```bash
# Linux/Mac
chmod 640 .env
chmod 640 logs/*
chmod 755 app/
chmod 755 app/Security/

# Windows (en PowerShell como admin)
icacls ".env" /inheritance:r /grant:r "%USERNAME%:F"
icacls "logs" /inheritance:r /grant:r "%USERNAME%:F"
```

### 5. Actualizar controladores (2 minutos)

En tus controladores, usar las nuevas funciones:

```php
// Validar entrada
use App\Security\InputValidator;

$email = InputValidator::validateEmail($_POST['email']);
$password = InputValidator::validatePassword($_POST['password']);

// Escapar salida en vistas
echo h($user_input);      // HTML
echo attr($attr_input);   // Atributos
echo json_safe($data);    // JSON

// Auditoría
$logger = new AuditLogger($db, $userId);
$logger->logLoginAttempt($email, $success);
```

### 6. Vistas (1 minuto)

Revisar `app/Views/SECURITY_GUIDE.php` para ejemplos

Reemplazar en vistas:
- `<?= $var ?>` → `<?= h($var) ?>`
- `<?= $_GET['id'] ?>` → `<?= attr($_GET['id']) ?>`

### 7. Testing (5 minutos)

```bash
# Verificar que todo funciona
curl -X POST http://localhost/Control_Asistencia/control_horario/api.php \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'

# Debe retornar error (falta CSRF), no error de servidor
```

---

## Checklist de Seguridad Mínimo

- [ ] `.env` configurado
- [ ] `ENCRYPTION_KEY` en `.env`
- [ ] Directorio `logs/` creado
- [ ] Script SQL ejecutado
- [ ] `.env` no está en git (agregar a `.gitignore`)
- [ ] `APP_DEBUG=0` en producción
- [ ] HTTPS habilitado (o `FORCE_SECURE_COOKIES=0`)
- [ ] Headers de seguridad activos (verificar con curl -I)
- [ ] Vistas usan `h()`, `attr()`, `json_safe()`

---

## Verificación Rápida

```bash
# Ver headers de seguridad
curl -I http://localhost/Control_Asistencia/control_horario/

# Debería mostrar:
# X-Content-Type-Options: nosniff
# X-Frame-Options: DENY
# Content-Security-Policy: ...
# Cache-Control: no-store, no-cache...

# Ver logs de auditoría
tail -f logs/audit-*.log
```

---

## Troubleshooting

### Error: "ENCRYPTION_KEY no está configurada"
```
→ Falta agregar ENCRYPTION_KEY a .env
→ Generar con: php -r "echo bin2hex(random_bytes(32));"
```

### Error: "CSRF inválido"
```
→ Asegurar que token CSRF está en form
→ <input type="hidden" name="csrf_token" value="<?= attr($_SESSION['token'] ?? '') ?>">
```

### Error: "Rate limit excedido"
```
→ Esperar 60 segundos
→ O desde otra IP
→ En desarrollo, puede deshabilitar en init.php
```

### Error: "No se puede escribir en logs/"
```
→ Verificar permisos: chmod 750 logs
→ Verificar que PHP user puede escribir
→ En Windows, editar permisos en Propiedades
```

---

## Monitoreo Después de Implementar

### Diario
- Revisar `logs/audit-*.log` para actividades sospechosas
- Verificar que no hay errores de seguridad en PHP error log

### Semanal
- Ejecutar: `grep SECURITY_ERROR logs/audit-*.log`
- Revisar intentos fallidos de login
- Actualizar contraseña admin

### Mensual
- Backup de `audit_log` en BD
- Rotación de logs antiguos
- Revisión de políticas de contraseña

---

## Próximos Pasos

1. **2FA (Dos factores)**: Ver archivo de tablas SQL
2. **Recuperación de Contraseña**: Implementar con `password_reset_tokens`
3. **Política de Contraseñas**: Usar tabla `password_policy`
4. **WAF**: Evaluar ModSecurity o CloudFlare WAF
5. **Análisis**: Usar OWASP ZAP periódicamente

---

**¡Sistema listo para producción!** 🔒
