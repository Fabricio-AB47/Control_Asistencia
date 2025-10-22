<?php
namespace App\Controllers;

class BaseController
{
    protected function render(string $view, array $params = []): void
    {
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Vista no encontrada: ' . htmlspecialchars($view);
            return;
        }

        // Extrae variables para la vista
        extract($params, EXTR_SKIP);

        // Renderiza contenido
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Layout principal
        require __DIR__ . '/../Views/layouts/main.php';
    }
}

