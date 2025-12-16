# DEPLOYMENT CHECKLIST DE SEGURIDAD

## Pre-Deployment (Ambiente de Desarrollo)

- [ ] Todas las vulnerabilidades OWASP Top 10 abordadas
- [ ] Code review completado
- [ ] Testing de seguridad manual completado
- [ ] OWASP ZAP scan sin problemas críticos
- [ ] Logs configurados y funcionando
- [ ] Auditoría de base de datos implementada
- [ ] .env.example documentado
- [ ] Documentación de seguridad completa

## Pre-Deployment (Staging)

- [ ] APP_DEBUG = 0
- [ ] FORCE_SECURE_COOKIES = 1 (si HTTPS disponible)
- [ ] ENCRYPTION_KEY generada y configurada
- [ ] Base de datos con audit_tables.sql ejecutado
- [ ] Directorio logs/ creado con permisos 750
- [ ] .env NO está versionado (en .gitignore)
- [ ] Todos los headers de seguridad activos
- [ ] HTTPS habilitado

## Pre-Deployment (Producción)

### Código
- [ ] Todas las dependencias actualizadas
- [ ] Composer.lock verificado
- [ ] No hay vendor/ en git
- [ ] No hay archivos temporales
- [ ] No hay comentarios con info sensible

### Base de Datos
- [ ] Backup pre-deployment
- [ ] Migrations ejecutadas
- [ ] audit_tables.sql ejecutado
- [ ] Índices creados
- [ ] User de BD con permisos mínimos
- [ ] Contraseña de BD fuerte (>15 chars, símbolos)

### Seguridad
- [ ] Certificado SSL/TLS válido
- [ ] HTTPS redirige HTTP
- [ ] Headers HTTP verificados con curl
- [ ] Permisos de archivo correctos (644 PHP, 750 dirs)
- [ ] .env con permisos 600
- [ ] logs/ con permisos 750

### Infrastructure
- [ ] Firewall configurado
- [ ] Puerto 22 (SSH) restringido
- [ ] Puerto 3306 (MySQL) NO expuesto
- [ ] WAF instalado (ModSecurity o CloudFlare)
- [ ] Rate limiting en nivel HTTP
- [ ] Backups automáticos configurados
- [ ] Monitoreo de servidor activo

### Credenciales
- [ ] DB_PASSWORD fuerte
- [ ] Credenciales SMTP funcionales
- [ ] ENCRYPTION_KEY respaldada seguramente
- [ ] Credenciales 2FA (si aplica)
- [ ] Todas en .env, NUNCA en código

### Testing Pre-Producción
- [ ] Login funciona
- [ ] CSRF token valida correctamente
- [ ] Rate limiting funciona
- [ ] Logs se escriben correctamente
- [ ] Auditoría registra eventos
- [ ] HTTPS redirige correctamente
- [ ] Headers de seguridad presentes
- [ ] Error 500 no muestra detalles

## Post-Deployment (Primeras 24h)

### Monitoreo
- [ ] Revisar error_log en tiempo real
- [ ] Revisar audit_log en BD
- [ ] Revisar logs de acceso del servidor
- [ ] Verificar que no hay errores de permisos
- [ ] Confirmar que HTTPS es forzado

### Pruebas de Humo
```bash
# Health check
curl -I https://tudominio.com/app/

# Login test
curl -X POST https://tudominio.com/app/index.php \
  -d "usuario=test&password=test" \
  -H "Content-Type: application/x-www-form-urlencoded"

# API test
curl -X POST https://tudominio.com/app/api/endpoint \
  -H "Content-Type: application/json" \
  -d '{"test":"data"}'
```

### Alertas
- [ ] Sistema de alertas para errores críticos configurado
- [ ] Notificaciones de login fallidos (si aplica)
- [ ] Monitoreo de uso de servidor
- [ ] Uptime monitoring configurado

## Post-Deployment (Semana 1)

- [ ] Revisar logs diarios
- [ ] Confirmar backups ejecutándose
- [ ] Verificar métricas de uso
- [ ] Feedback de usuarios sobre rendimiento
- [ ] Documentar cualquier issue encontrado

## Post-Deployment (Mes 1)

- [ ] Análisis de vulnerabilidades externo
- [ ] Penetration testing (si presupuesto)
- [ ] Revisión de políticas de contraseña
- [ ] Análisis de audit_log para patrones sospechosos
- [ ] Actualización de documentación

---

## Comandos Útiles

### Verificar Headers
```bash
curl -I https://tudominio.com/app/

# Debe mostrar:
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Strict-Transport-Security: max-age=...
Content-Security-Policy: ...
```

### Ver Últimos Logs
```bash
tail -f logs/audit-*.log
tail -f /var/log/apache2/error.log
tail -f /var/log/php-fpm.log
```

### Verificar Permisos
```bash
ls -la .env        # debe ser -rw------- (600)
ls -la logs/       # debe ser drwxr-x--- (750)
find app/ -name "*.php" -exec ls -l {} \;  # debe ser -rw-r--r-- (644)
```

### Generar ENCRYPTION_KEY
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Test de Contraseña
```bash
# Verificar requisitos de pwd
php -r "
\$pwd = 'MyPass123!';
if (strlen(\$pwd) >= 8 && preg_match('/[A-Z]/', \$pwd) && 
    preg_match('/[a-z]/', \$pwd) && preg_match('/[0-9]/', \$pwd)) {
    echo 'OK';
} else {
    echo 'FALLA';
}
"
```

### Verificar SSL/TLS
```bash
# Verificar certificado
openssl s_client -connect tudominio.com:443 -showcerts

# Ver fecha de expiración
echo | openssl s_client -servername tudominio.com -connect tudominio.com:443 2>/dev/null | openssl x509 -noout -dates
```

---

## Rollback Plan

Si algo falla en producción:

1. **Revertir cambios**
   ```bash
   git revert <commit>
   composer install
   php artisan migrate:rollback
   ```

2. **Restaurar base de datos**
   ```bash
   mysql -u root -p database < backup.sql
   ```

3. **Limpiar caché y sesiones**
   ```bash
   rm -rf logs/*
   rm -rf /tmp/php_sessions/*
   ```

4. **Notificar a usuarios**
   - Email sobre el rollback
   - Instrucciones si es necesario
   - ETA de re-deploy

---

## Auditoría Post-Deployment

Crear reporte con:
- Fecha y hora de deployment
- Cambios realizados
- Testing realizado
- Issues encontrados y resueltos
- Plan de monitoreo
- Contactos de emergencia

---

## Contact de Emergencia

**En caso de problema crítico:**
- Contacto 1: [nombre] [email] [teléfono]
- Contacto 2: [nombre] [email] [teléfono]
- Escalación: [departamento] [email] [teléfono]

---

**Documento generado**: 2024-12-16
**Última revisión**: 2024-12-16
