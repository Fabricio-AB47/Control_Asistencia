<?php
/**
 * GUÍA DE SEGURIDAD PARA VISTAS
 * 
 * Este archivo documenta cómo usar las funciones de seguridad en vistas
 * para prevenir XSS y otros ataques.
 */

// ============================================================================
// 1. ESCAPAR SALIDA HTML
// ============================================================================

// ❌ MAL - Sin escapar (vulnerable a XSS)
?>
<!-- <p><?= $user_input ?></p> -->

<?php
// ✅ BIEN - Con escaping
?>
<!-- <p><?= h($user_input) ?></p> -->

<?php
// ============================================================================
// 2. ESCAPAR ATRIBUTOS HTML
// ============================================================================

// ❌ MAL
?>
<!-- <input value="<?= $value ?>"> -->

<?php
// ✅ BIEN
?>
<!-- <input value="<?= attr($value) ?>"> -->

<?php
// ============================================================================
// 3. ESCAPAR JSON
// ============================================================================

// ❌ MAL - JSON sin escapar en HTML
?>
<!-- <script>const data = <?= json_encode($data) ?></script> -->

<?php
// ✅ BIEN
?>
<!-- <script>const data = <?= json_safe($data) ?></script> -->

<?php
// ============================================================================
// 4. USAR NONCE EN SCRIPTS INLINE (opcional pero recomendado)
// ============================================================================

// En init.php ya se genera: $_SESSION['csp_nonce']
$nonce = $_SESSION['csp_nonce'] ?? '';
?>

<!-- <script nonce="<?= attr($nonce) ?>">
  // Código JavaScript aquí
  console.log('seguro');
</script> -->

<?php
// ============================================================================
// 5. VALIDAR EN SERVIDOR, NO EN CLIENTE
// ============================================================================

// ❌ MAL - Solo validación en cliente
?>
<!-- <input type="email" name="email"> -->

<?php
// ✅ BIEN - Validación en servidor (después verificar)
?>
<!-- 
<?php
use App\Security\InputValidator;

if ($_POST) {
    try {
        $email = InputValidator::validateEmail($_POST['email']);
        // Procesar email seguro
    } catch (RuntimeException $e) {
        echo h($e->getMessage());
    }
}
?>
 -->

<?php
// ============================================================================
// 6. CSRF TOKENS EN FORMULARIOS
// ============================================================================
?>

<!-- <form method="POST">
  <input type="hidden" name="csrf_token" value="<?= attr($_SESSION['token'] ?? '') ?>">
  <!-- resto del formulario -->
</form> -->

<?php
// O si se envía en header (para AJAX):
?>

<!-- <form method="POST">
  <!-- resto del formulario -->
  <!-- En JavaScript:
    fetch('/api/endpoint', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '<?= attr($_SESSION['token'] ?? '') ?>',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });
  -->
</form> -->

<?php
// ============================================================================
// 7. LINKS Y REDIRECCIONES
// ============================================================================

// ❌ MAL - URL sin validar
?>
<!-- <a href="<?= $_GET['redirect'] ?>">Ir a</a> -->

<?php
// ✅ BIEN - URL validada
?>
<!-- <a href="<?= attr(validateSafeUrl($_GET['redirect'] ?? '/')) ?>">Ir a</a> -->

<?php
// ============================================================================
// 8. MOSTRAR ERRORES DE MANERA SEGURA
// ============================================================================

// ❌ MAL
?>
<!-- <p><?= $error_message ?></p> -->

<?php
// ✅ BIEN
?>
<!-- <p><?= h($error_message) ?></p> -->

<?php
// ============================================================================
// 9. ITERAR SOBRE ARRAYS DE DATOS
// ============================================================================

// ✅ BIEN
$users = [
    ['name' => '<script>alert("xss")</script>', 'email' => 'test@test.com'],
];
?>

<!-- <ul>
<?php foreach ($users as $user): ?>
  <li>
    <strong><?= h($user['name']) ?></strong>
    <em><?= h($user['email']) ?></em>
  </li>
<?php endforeach; ?>
</ul> -->

<?php
// ============================================================================
// 10. DATOS SENSIBLES (NO mostrar directamente)
// ============================================================================

// ❌ MAL - Mostrar contraseña
?>
<!-- <p>Contraseña: <?= h($user['password']) ?></p> -->

<?php
// ✅ BIEN - No mostrar contraseña
?>
<!-- <p>✓ Contraseña configurada</p> -->

<?php
// ============================================================================
// 11. FECHAS Y NÚMEROS
// ============================================================================

// ✅ BIEN - Formatear antes de mostrar
$fecha = new DateTime($db_date);
$formatted = $fecha->format('d/m/Y H:i:s');
?>

<!-- <span><?= h($formatted) ?></span> -->

<?php
// ============================================================================
// FUNCIONES DE UTILIDAD PARA VISTAS
// ============================================================================

/**
 * Valida y escapa URLs seguras
 */
function validateSafeUrl($url) {
    // Solo permitir URLs relativas y HTTPS
    if (empty($url)) {
        return '/';
    }
    if (strpos($url, 'javascript:') === 0 || strpos($url, 'data:') === 0) {
        return '/';
    }
    if ($url[0] !== '/' && strpos($url, 'https://') !== 0) {
        return '/';
    }
    return $url;
}

/**
 * Obtiene el nonce CSP para usar en atributos
 */
function getCspNonce() {
    return $_SESSION['csp_nonce'] ?? '';
}

/**
 * Escapa JSON para usar en atributos data-*
 */
function escapeJsonAttribute($data) {
    return htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
}

// ============================================================================
// EJEMPLO COMPLETO DE VISTA SEGURA
// ============================================================================
?>

<!-- 
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?= h($pageTitle) ?></title>
</head>
<body>
  <h1><?= h($pageTitle) ?></h1>
  
  <?php if (!empty($message)): ?>
    <div class="alert">
      <?= h($message) ?>
    </div>
  <?php endif; ?>
  
  <form method="POST" action="<?= attr($action) ?>">
    <input type="hidden" name="csrf_token" value="<?= attr($_SESSION['token'] ?? '') ?>">
    
    <input type="email" name="email" value="<?= attr($_POST['email'] ?? '') ?>" required>
    <textarea name="message"><?= h($_POST['message'] ?? '') ?></textarea>
    
    <button type="submit">Enviar</button>
  </form>
  
  <table>
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Email</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
      <tr>
        <td><?= h($user['nombre']) ?></td>
        <td><a href="mailto:<?= attr($user['email']) ?>"><?= h($user['email']) ?></a></td>
        <td><?= h($user['fecha_creacion']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
 -->

<?php
// ============================================================================
// CHECKLIST PARA REVISIÓN DE VISTAS
// ============================================================================

/*
Antes de commitar una vista, verificar:

☐ Todas las variables de usuario escapadas con h()
☐ Atributos HTML escapados con attr()
☐ JSON escapado con json_safe()
☐ Formularios POST tienen token CSRF
☐ URLs validadas con validateSafeUrl()
☐ Errores mostrados con h()
☐ Datos sensibles (contraseñas, tokens) NO mostrados
☐ Fechas formateadas legiblemente
☐ Links tienen href validados
☐ Scripts inline tienen nonce si existe
☐ No hay comentarios con información sensible

*/
?>
