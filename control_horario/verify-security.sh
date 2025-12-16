#!/bin/bash
# ============================================================================
# SCRIPT DE VERIFICACIÓN DE SEGURIDAD
# ============================================================================
# Ejecutar este script para verificar que todo está correctamente configurado
# Uso: bash verify-security.sh
# ============================================================================

set -e

echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║          VERIFICACIÓN DE SEGURIDAD - CONTROL HORARIO                      ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

check_count=0
pass_count=0
fail_count=0

# Función para verificar
check() {
    local description=$1
    local command=$2
    check_count=$((check_count+1))
    
    printf "[%d] %-50s ... " $check_count "$description"
    
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ OK${NC}"
        pass_count=$((pass_count+1))
        return 0
    else
        echo -e "${RED}✗ FALLO${NC}"
        fail_count=$((fail_count+1))
        return 1
    fi
}

# Función para info
info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Función para advertencia
warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"
echo "1. VERIFICACIÓN DE ARCHIVOS"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"

check "Archivo .env.example existe" "test -f .env.example"
check "Archivo .env existe" "test -f .env"
check "Directorio app/Security existe" "test -d app/Security"
check "Archivo SecurityHeaders.php existe" "test -f app/Security/SecurityHeaders.php"
check "Archivo InputValidator.php existe" "test -f app/Security/InputValidator.php"
check "Archivo Encryption.php existe" "test -f app/Security/Encryption.php"
check "Archivo AuditLogger.php existe" "test -f app/Security/AuditLogger.php"
check "Archivo SecurityUtils.php existe" "test -f app/Security/SecurityUtils.php"
check "Archivo audit_tables.sql existe" "test -f database/audit_tables.sql"
check "Archivo logs existe (directorio)" "test -d logs"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"
echo "2. VERIFICACIÓN DE PERMISOS"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"

# En sistemas Windows, estos checks pueden no funcionar
if [[ "$OSTYPE" == "linux-gnu"* ]] || [[ "$OSTYPE" == "darwin"* ]]; then
    check ".env tiene permisos 640 o 600" "test -f .env && [ $(stat -f '%OLp' .env 2>/dev/null || stat -c '%a' .env) != '777' ]"
    check "logs tiene permisos 750 o 755" "test -d logs && [ $(stat -f '%OLp' logs 2>/dev/null || stat -c '%a' logs) != '777' ]"
    check "app/Security es accesible" "test -d app/Security && [ -x app/Security ]"
else
    warn "Sistema Windows: verifica manualmente los permisos en Propiedades"
fi

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"
echo "3. VERIFICACIÓN DE CONFIGURACIÓN"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"

check ".env contiene DB_HOST" "grep -q '^DB_HOST' .env"
check ".env contiene DB_NAME" "grep -q '^DB_NAME' .env"
check ".env contiene DB_USER" "grep -q '^DB_USER' .env"
check ".env contiene ENCRYPTION_KEY" "grep -q '^ENCRYPTION_KEY' .env"
check ".env contiene APP_DEBUG" "grep -q '^APP_DEBUG' .env"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"
echo "4. VERIFICACIÓN DE DOCUMENTACIÓN"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"

check "SECURITY.md existe" "test -f SECURITY.md"
check "IMPLEMENTATION_GUIDE.md existe" "test -f IMPLEMENTATION_GUIDE.md"
check "DEPLOYMENT_CHECKLIST.md existe" "test -f DEPLOYMENT_CHECKLIST.md"
check "FILES_OVERVIEW.md existe" "test -f FILES_OVERVIEW.md"
check "SECURITY_SUMMARY.md existe" "test -f SECURITY_SUMMARY.md"
check "app/Views/SECURITY_GUIDE.php existe" "test -f app/Views/SECURITY_GUIDE.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"
echo "5. VERIFICACIÓN DE CÓDIGO"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"

check "app/init.php usa SecurityHeaders" "grep -q 'SecurityHeaders::setHeaders' app/init.php"
check "app/Controllers/Core.php tiene helper h()" "grep -q 'function h(' app/Controllers/Core.php"
check "index.php valida entrada" "grep -q 'InputValidator' index.php"
check "handle_registrar.php valida JSON" "grep -q 'validateJsonRequest' app/Endpoints/handle_registrar.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"
echo "6. VERIFICACIÓN DE .gitignore"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"

check ".gitignore existe" "test -f .gitignore"
check ".gitignore ignora .env" "grep -q '^.env' .gitignore"
check ".gitignore ignora logs/" "grep -q '^logs/' .gitignore"
check ".gitignore ignora vendor/" "grep -q '^vendor/' .gitignore"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"
echo "RESUMEN"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════════════════${NC}"

echo ""
printf "Total de verificaciones: $check_count\n"
printf "Pasadas: ${GREEN}$pass_count${NC}\n"
printf "Fallidas: ${RED}$fail_count${NC}\n"
echo ""

if [ $fail_count -eq 0 ]; then
    echo -e "${GREEN}✓ TODAS LAS VERIFICACIONES PASARON${NC}"
    echo ""
    echo "Próximos pasos:"
    echo "  1. Ejecutar: mysql -u root -p database < database/audit_tables.sql"
    echo "  2. Crear clave: php -r \"echo bin2hex(random_bytes(32))\""
    echo "  3. Agregar ENCRYPTION_KEY a .env"
    echo "  4. Revisar documentación: IMPLEMENTATION_GUIDE.md"
    exit 0
else
    echo -e "${RED}✗ ALGUNAS VERIFICACIONES FALLARON${NC}"
    echo ""
    echo "Revisar:"
    echo "  - Archivos están en lugar correcto"
    echo "  - Permisos de archivo son correctos"
    echo "  - Variables en .env están configuradas"
    exit 1
fi
