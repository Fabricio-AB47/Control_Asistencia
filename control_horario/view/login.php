<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="build/css/app.css">
    <title>Control Horario Ingreso</title>
</head>
<body>
    <header class="header">
        <img src="src/img/intec.png" alt="Logo Intec" class="logo">
    </header>
    <main class="login-main">
        <div class="login-container">
            <form method="POST" class="login-form">
                <h2 class="login-title">Iniciar sesión</h2>
    
                <div class="form-group">
                    <label for="email" class="form-label">Ingrese el correo</label>
                    <input type="email" id="email" name="usuario" class="form-input" placeholder="Correo electrónico" required>
                </div>
    
                <div class="form-group">
                    <label for="password" class="form-label">Ingrese la contraseña</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Contraseña" required>
                </div>
    
                <div class="form-group">
                    <button type="submit" class="login-btn">Iniciar sesión</button>
                </div>
            </form>
            <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <?php if (isset($_GET['error'])) echo "<p style='color:red;'>Sesión expirada. Por favor, inicia sesión de nuevo.</p>"; ?>
        </div>
    </main>
</body>
</html>