<?php
namespace App\Services;

class RbacService
{
    private function normalize(string $role): string
    {
        $r = trim($role);
        if ($r === '') return '';
        // Quitar acentos comunes y pasar a MAYÚSCULAS
        $r = strtr($r, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
        ]);
        return strtoupper($r);
    }

    public function allowedModulesForRole(string $role): array
    {
        $key = $this->normalize($role);
        switch ($key) {
            case 'ADMIN':
            case 'ADMINISTRADOR':
                // Admin no timbra: solo módulo 'admin' en la UI
                return ['admin'];
            case 'TI':
                return ['ti'];
            case 'FINANCIERO':
                return ['financiero'];
            case 'ADMISIONES':
                return ['admisiones'];
            case 'ACADEMICO':
            case 'ACADEMICO(A)':
                return ['academico'];
            case 'DOCENTES':
            case 'DOCENTE':
                return ['academico'];
            case 'BIENESTAR':
                return ['bienestar'];
            default:
                // nombres capitalizados que venían antes
                if ($role === 'Financiero') return ['financiero'];
                if ($role === 'Admisiones') return ['admisiones'];
                if ($role === 'Académico' || $role === 'Academico') return ['academico'];
                if ($role === 'Bienestar') return ['bienestar'];
                if ($role === 'Admin') return ['admin','ti','financiero','admisiones','academico','bienestar'];
                if ($role === 'TI') return ['ti'];
                return [];
        }
    }

    public function canAccessModule(string $role, string $module): bool
    {
        $allowed = $this->allowedModulesForRole($role);
        if (empty($allowed)) return false;
        return in_array($module, $allowed, true);
    }

    public function dashboardForModule(string $module): string
    {
        $base = function_exists('appBasePath') ? appBasePath() : '';
        return $base . '/public/index.php?r=dashboard&mod=' . $module;
    }

    public function menuForRole(string $role, string $currentModule = ''): array
    {
        $mods = $this->allowedModulesForRole($role);
        if (empty($mods)) return [];
        $menu = [];
        foreach ($mods as $m) {
            $menu[] = [
                'mod'     => $m,
                'label'   => ucfirst($m),
                'current' => ($m === $currentModule),
            ];
        }
        return $menu;
    }
}
