
        if (!app_rate_limit('post_'.$action.'_'.$mod, 5, 10)) { http_response_code(429); echo 'Demasiadas solicitudes. Intenta nuevamente en unos segundos.'; return; }

        $uid = (int)$_SESSION['id_usuario'];
        $input = \app_json_input();

        try {
            $db = \conexion();
            $svc = new ControlService($db);
            switch ($action) {
                case 'ingreso':           echo $svc->registrarIngreso($uid, $input); break;
                case 'salida_almuerzo':   echo $svc->registrarSalidaAlmuerzo($uid, $input); break;
                case 'regreso_almuerzo':  echo $svc->registrarRegresoAlmuerzo($uid, $input); break;
                case 'salida_laboral':    echo $svc->registrarSalidaLaboral($uid, $input); break;
                case 'docente_ingreso':   echo $svc->registrarDocenteIngreso($uid, $input); break;
                case 'docente_fin':       echo $svc->registrarDocenteFin($uid, $input); break;
                default: http_response_code(400); echo 'Acción no soportada';
            }
        } catch (\Throwable $e) {
            error_log('registrar-mvc: '.$e->getMessage());
            http_response_code(500);
            $fileEnv = function_exists('loadDotEnv') ? loadDotEnv() : [];
            $dbg = function_exists('getEnvVar') ? ((getEnvVar('APP_DEBUG', $fileEnv, 'app_debug') ?? '0') === '1') : (getenv('APP_DEBUG')==='1');
            echo ($dbg ? ('Error: ' . $e->getMessage()) : 'Error al procesar la solicitud.');
        }
    }
}



