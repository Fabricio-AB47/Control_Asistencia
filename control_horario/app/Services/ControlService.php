<?php
namespace App\Services;

use PDO;
use DateTime;
use RuntimeException;

class ControlService
{
    private PDO $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // --- Helpers compartidos ---
    private function normalizeDireccion(?string $direccion): string
    {
        $direccion = isset($direccion) ? trim((string)$direccion) : '';
        if ($direccion === '') return '';
        if (function_exists('mb_substr')) {
            return mb_substr($direccion, 0, 190, 'UTF-8');
        }
        return substr($direccion, 0, 190);
    }

    private function validateUbicacion($latitud, $longitud, string $direccion): array
    {
        if ($latitud === null || $longitud === null || $direccion === '') {
            throw new RuntimeException('No se pudo obtener la ubicación.');
        }
        $latF = (float)$latitud;
        $lonF = (float)$longitud;
        if ($latF < -90 || $latF > 90 || $lonF < -180 || $lonF > 180) {
            throw new RuntimeException('Coordenadas inválidas.');
        }
        $latitud  = number_format($latF, 6, '.', '');
        $longitud = number_format($lonF, 6, '.', '');
        return [$latitud, $longitud];
    }

    private function getUserTipo(int $uid): int
    {
        $st = $this->db->prepare('SELECT id_tp_user FROM usuario WHERE id_usuario = ? LIMIT 1');
        $st->execute([$uid]);
        $idTpUser = (int)($st->fetchColumn() ?: 0);
        if (!$idTpUser) {
            throw new RuntimeException('Usuario sin tipo asignado.');
        }
        return $idTpUser;
    }

    private function getOrCreateFechaRegistro(int $uid, string $fechaYmd): int
    {
        $st = $this->db->prepare('SELECT id_fecha_registro FROM fecha_registro WHERE id_usuario = ? AND fecha_ingreso = ? LIMIT 1');
        $st->execute([$uid, $fechaYmd]);
        $idFecha = $st->fetchColumn();
        if (!$idFecha) {
            $ins = $this->db->prepare('INSERT INTO fecha_registro (id_usuario, fecha_ingreso) VALUES (?, ?)');
            $ins->execute([$uid, $fechaYmd]);
            return (int)$this->db->lastInsertId();
        }
        return (int)$idFecha;
    }

    private function ensureCatalog(string $table, string $pk, string $col, string $detalle): int
    {
        $q = $this->db->prepare("SELECT $pk FROM $table WHERE $col = ? LIMIT 1");
        $q->execute([$detalle]);
        $id = (int)($q->fetchColumn() ?: 0);
        if (!$id) {
            $ins = $this->db->prepare("INSERT INTO $table ($col) VALUES (?)");
            $ins->execute([$detalle]);
            $id = (int)$this->db->lastInsertId();
        }
        return $id;
    }

    // ---- Registros de personal administrativo ----
    public function registrarIngreso(int $uid, array $input, string $tz = 'America/Guayaquil'): string
    {
        date_default_timezone_set($tz);
        $hoy   = date('Y-m-d');
        $ahora = date('H:i:s');

        $direccion = $this->normalizeDireccion($input['direccion'] ?? null);
        [$latitud, $longitud] = $this->validateUbicacion($input['latitud'] ?? null, $input['longitud'] ?? null, $direccion);

        $this->db->beginTransaction();
        try {
            $idTpUser = $this->getUserTipo($uid);

            $st = $this->db->prepare('SELECT id_hora_entrada, hora_ingreso_personal FROM horario_entrada_personal WHERE id_usuario = ? AND id_tp_user = ? LIMIT 1');
            $st->execute([$uid, $idTpUser]);
            $horario = $st->fetch(PDO::FETCH_ASSOC);
            if (!$horario) {
                throw new RuntimeException('No se encontró un horario de entrada para tu usuario/tipo.');
            }
            $idHoraEntrada  = (int)$horario['id_hora_entrada'];
            $horaProgramada = $horario['hora_ingreso_personal'];

            $idFecha = $this->getOrCreateFechaRegistro($uid, $hoy);

            $st = $this->db->prepare('SELECT 1 FROM horario_ingreso WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1');
            $st->execute([$uid, $idFecha]);
            if ($st->fetch()) {
                $this->db->rollBack();
                return 'Ya existe un registro de ingreso para hoy.';
            }

            $prog   = new DateTime("$hoy $horaProgramada");
            $now    = new DateTime("$hoy $ahora");
            $tolEnd = (clone $prog)->modify('+10 minutes');

            if ($now < $prog) {
                $detalleEstado = 'Ingreso antes de su hora de ingreso';
            } elseif ($now <= $tolEnd) {
                $detalleEstado = 'Ingreso a tiempo';
            } else {
                $detalleEstado = 'Atraso';
            }

            $idEstado = $this->ensureCatalog('estado_ingreso', 'id_estado_ingreso', 'detalle_ingreso', $detalleEstado);

            // Guardar también el id_hora_entrada para respetar la FK
            $ins = $this->db->prepare('INSERT INTO horario_ingreso (id_usuario, id_fecha_registro, id_estado_ingreso, id_hora_entrada, hora_ingreso, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $idFecha, $idEstado, $idHoraEntrada, $ahora, $latitud, $longitud, $direccion]);

            $this->db->commit();
            return 'Ingreso registrado con estado: ' . $detalleEstado . '.';
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarSalidaAlmuerzo(int $uid, array $input, string $tz = 'America/Guayaquil'): string
    {
        date_default_timezone_set($tz);
        $hoy   = (new DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d');
        $ahora = date('H:i:s');

        $direccion = $this->normalizeDireccion($input['direccion'] ?? null);
        [$latitud, $longitud] = $this->validateUbicacion($input['latitud'] ?? null, $input['longitud'] ?? null, $direccion);

        $this->db->beginTransaction();
        try {
            // Fecha del día (NO crear si falta)
            $q = $this->db->prepare('SELECT id_fecha_registro FROM fecha_registro WHERE id_usuario = ? AND fecha_ingreso = ? LIMIT 1 FOR UPDATE');
            $q->execute([$uid, $hoy]);
            $idFecha = $q->fetchColumn();
            if (!$idFecha) {
                $this->db->rollBack();
                return 'No existe una fecha de registro para hoy. No se registró la salida al almuerzo.';
            }
            $idFecha = (int)$idFecha;

            // Debe existir ingreso
            $q = $this->db->prepare('SELECT id_hora_ingreso FROM horario_ingreso WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1');
            $q->execute([$uid, $idFecha]);
            if (!($q->fetchColumn())) {
                $this->db->rollBack();
                return 'No puedes registrar salida al almuerzo sin haber registrado el ingreso.';
            }

            // Evitar duplicado
            $q = $this->db->prepare('SELECT 1 FROM horario_sl_almuerzo WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1');
            $q->execute([$uid, $idFecha]);
            if ($q->fetch()) {
                $this->db->rollBack();
                return 'Ya se registró la salida al almuerzo para hoy.';
            }

            $idEstado = $this->ensureCatalog('estado_salida_almuerzo', 'id_estado_salida_almuerzo', 'detalle_salida_almuerzo', 'Salida al almuerzo');

            $ins = $this->db->prepare('INSERT INTO horario_sl_almuerzo (id_usuario, id_fecha_registro, id_estado_salida_almuerzo, hora_sl_almuerzo, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $idFecha, $idEstado, $ahora, $latitud, $longitud, $direccion]);

            $this->db->commit();
            return 'Salida al almuerzo registrada a las ' . $ahora . '.';
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarRegresoAlmuerzo(int $uid, array $input, string $tz = 'America/Guayaquil'): string
    {
        date_default_timezone_set($tz);
        $hoy   = (new DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d');
        $ahora = date('H:i:s');

        $direccion = $this->normalizeDireccion($input['direccion'] ?? null);
        [$latitud, $longitud] = $this->validateUbicacion($input['latitud'] ?? null, $input['longitud'] ?? null, $direccion);

        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT id_fecha_registro FROM fecha_registro WHERE id_usuario = ? AND fecha_ingreso = ? LIMIT 1 FOR UPDATE');
            $q->execute([$uid, $hoy]);
            $idFecha = $q->fetchColumn();
            if (!$idFecha) {
                $this->db->rollBack();
                return 'No existe una fecha de registro para hoy. No se registró el regreso del almuerzo.';
            }
            $idFecha = (int)$idFecha;

            // Evitar duplicado
            $q = $this->db->prepare('SELECT 1 FROM horario_rt_almuerzo WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1');
            $q->execute([$uid, $idFecha]);
            if ($q->fetch()) {
                $this->db->rollBack();
                return 'Ya se registró el regreso del almuerzo para hoy.';
            }

            $idEstado = $this->ensureCatalog('estado_retorno_almuerzo', 'id_estado_retorno_almuerzo', 'detalle_retorno_almuerzo', 'Regreso de almuerzo');

            $ins = $this->db->prepare('INSERT INTO horario_rt_almuerzo (id_usuario, id_fecha_registro, id_estado_retorno_almuerzo, hora_rt_almuerzo, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $idFecha, $idEstado, $ahora, $latitud, $longitud, $direccion]);

            $this->db->commit();
            return 'Regreso del almuerzo registrado a las ' . $ahora . '.';
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarSalidaLaboral(int $uid, array $input, string $tz = 'America/Guayaquil'): string
    {
        date_default_timezone_set($tz);
        $hoy   = (new DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d');
        $ahora = date('H:i:s');

        $direccion = $this->normalizeDireccion($input['direccion'] ?? null);
        [$latitud, $longitud] = $this->validateUbicacion($input['latitud'] ?? null, $input['longitud'] ?? null, $direccion);

        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT id_fecha_registro FROM fecha_registro WHERE id_usuario = ? AND fecha_ingreso = ? LIMIT 1 FOR UPDATE');
            $q->execute([$uid, $hoy]);
            $idFecha = $q->fetchColumn();
            if (!$idFecha) {
                $this->db->rollBack();
                return 'No existe una fecha de registro para hoy. No se registró la salida laboral.';
            }
            $idFecha = (int)$idFecha;

            // Evitar duplicado salida laboral
            $q = $this->db->prepare('SELECT 1 FROM horario_salida WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1');
            $q->execute([$uid, $idFecha]);
            if ($q->fetch()) {
                $this->db->rollBack();
                return 'Ya se registró la salida laboral para hoy.';
            }

            $idTpUser = $this->getUserTipo($uid);
            $q = $this->db->prepare('SELECT hora_salida_personal FROM horario_salida_personal WHERE id_usuario = ? AND id_tp_user = ? LIMIT 1');
            $q->execute([$uid, $idTpUser]);
            $horaSalidaProg = $q->fetchColumn();
            if (!$horaSalidaProg) {
                throw new RuntimeException('No se encontró un horario de salida para tu usuario/tipo.');
            }

            $prog = new DateTime("$hoy $horaSalidaProg");
            $now  = new DateTime("$hoy $ahora");
            $detalleEstado = ($now >= $prog) ? 'Fin de jornada laboral' : 'Salida anticipada';
            $idEstadoSalida = $this->ensureCatalog('estado_salida', 'id_estado_salida', 'detalle_salida', $detalleEstado);

            // Placeholders de almuerzo si faltan (direccion='NA')
            $q = $this->db->prepare('SELECT 1 FROM horario_sl_almuerzo WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1 FOR UPDATE');
            $q->execute([$uid, $idFecha]);
            if (!$q->fetchColumn()) {
                $idEstadoSalidaAlm = $this->ensureCatalog('estado_salida_almuerzo', 'id_estado_salida_almuerzo', 'detalle_salida_almuerzo', 'No registrado');
                $ins = $this->db->prepare('INSERT INTO horario_sl_almuerzo (id_usuario, id_fecha_registro, id_estado_salida_almuerzo, hora_sl_almuerzo, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$uid, $idFecha, $idEstadoSalidaAlm, '00:00:00', '0.000000', '0.000000', 'NA']);
            }

            $q = $this->db->prepare('SELECT 1 FROM horario_rt_almuerzo WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1 FOR UPDATE');
            $q->execute([$uid, $idFecha]);
            if (!$q->fetchColumn()) {
                $idEstadoRet = $this->ensureCatalog('estado_retorno_almuerzo', 'id_estado_retorno_almuerzo', 'detalle_retorno_almuerzo', 'No registrado');
                $ins = $this->db->prepare('INSERT INTO horario_rt_almuerzo (id_usuario, id_fecha_registro, id_estado_retorno_almuerzo, hora_rt_almuerzo, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$uid, $idFecha, $idEstadoRet, '00:00:00', '0.000000', '0.000000', 'NA']);
            }

            $ins = $this->db->prepare('INSERT INTO horario_salida (id_usuario, id_fecha_registro, id_estado_salida, hora_salida, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $idFecha, $idEstadoSalida, $ahora, $latitud, $longitud, $direccion]);

            $this->db->commit();
            return 'Salida laboral registrada a las ' . $ahora . ' (estado: ' . $detalleEstado . ').';
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ====== REGISTROS DOCENTE (sin horario programado, ventanas específicas) ======
    private function docenteSlotIndex(string $type, string $tz, string $ahora)
    {
        $dtz = new \DateTimeZone($tz);
        $todayDt = new DateTime('now', $dtz);
        $today = $todayDt->format('Y-m-d');
        $dow = (int)$todayDt->format('N'); // 1=Lunes .. 7=Domingo
        if ($dow >= 5 && $dow <= 6) {
            // Viernes (5) y Sábado (6): 18:00-20:00, 20:00-22:00
            $slots = ($type === 'ingreso') ? ['18:00:00', '20:00:00'] : ['20:00:00', '22:00:00'];
        } elseif ($dow >= 1 && $dow <= 4) {
            // Lunes a Jueves: 18:30, 19:30, 20:30 (ingresos) / 19:30, 20:30, 21:30 (fines)
            $slots = ($type === 'ingreso') ? ['18:30:00', '19:30:00', '20:30:00'] : ['19:30:00', '20:30:00', '21:30:00'];
        } else {
            return null; // Domingo: sin ventanas
        }
        $now = new DateTime($today . ' ' . $ahora, $dtz);
        for ($i = 0; $i < count($slots); $i++) {
            $t = new DateTime($today . ' ' . $slots[$i], $dtz);
            $start = (clone $t)->modify('-10 minutes'); // 10 min antes como máximo
            $end = ($i < count($slots) - 1)
                ? (new DateTime($today . ' ' . $slots[$i + 1], $dtz))->modify('-10 minutes')
                : (clone $t)->modify('+90 minutes');
            if ($now >= $start && $now < $end) {
                return $i; // ventana válida
            }
        }
        return null;
    }

    private function docenteSlotUsed(string $table, string $timeCol, int $uid, int $idFecha, string $tz, string $ahora, string $type): bool
    {
        $q = $this->db->prepare("SELECT $timeCol FROM $table WHERE id_usuario = ? AND id_fecha_registro = ? ORDER BY $timeCol ASC");
        $q->execute([$uid, $idFecha]);
        $rows = $q->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $slotNow = $this->docenteSlotIndex($type, $tz, $ahora);
        if ($slotNow === null) {
            return true; // fuera de ventana => tratar como usado para bloquear
        }
        foreach ($rows as $t) {
            $slotPrev = $this->docenteSlotIndex($type, $tz, (string)$t);
            if ($slotPrev === $slotNow) {
                return true; // ya usó esta ventana
            }
        }
        return false;
    }

    public function registrarDocenteIngreso(int $uid, array $input, string $tz = 'America/Guayaquil'): string
    {
        date_default_timezone_set($tz);
        $hoy   = date('Y-m-d');
        $ahora = date('H:i:s');

        // Solo permitir si el tipo de usuario es DOCENTE (id_tp_user = 7)
        $idTpUser = $this->getUserTipo($uid);
        if ($idTpUser !== 7) {
            return 'Solo usuarios con tipo DOCENTE pueden registrar ingresos de docente.';
        }

        $direccion = $this->normalizeDireccion($input['direccion'] ?? null);
        [$latitud, $longitud] = $this->validateUbicacion($input['latitud'] ?? null, $input['longitud'] ?? null, $direccion);
        $this->db->beginTransaction();
        try {
            $idFecha = $this->getOrCreateFechaRegistro($uid, $hoy);
            // Limite 3 por día
            $q = $this->db->prepare('SELECT COUNT(*) FROM horario_docente_ingreso_1 WHERE id_usuario = ? AND id_fecha_registro = ?');
            $q->execute([$uid, $idFecha]);
            if ((int)$q->fetchColumn() >= 3) {
                $this->db->rollBack();
                return 'Ya registraste 3 ingresos hoy.';
            }
            // Ventana específica
            if ($this->docenteSlotIndex('ingreso', $tz, $ahora) === null) {
                $this->db->rollBack();
                return 'Fuera de la ventana permitida para ingreso (18:30, 19:30 o 20:30 con 10 min antes).';
            }
            if ($this->docenteSlotUsed('horario_docente_ingreso_1', 'hora_ing_doc', $uid, $idFecha, $tz, $ahora, 'ingreso')) {
                $this->db->rollBack();
                return 'Ya registraste en esta ventana de ingreso.';
            }
            $ins = $this->db->prepare('INSERT INTO horario_docente_ingreso_1 (id_usuario, id_fecha_registro, hora_ing_doc, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $idFecha, $ahora, $latitud, $longitud, $direccion]);
            $this->db->commit();
            return 'Ingreso docente registrado a las ' . $ahora . '.';
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarDocenteFin(int $uid, array $input, string $tz = 'America/Guayaquil'): string
    {
        date_default_timezone_set($tz);
        $hoy   = date('Y-m-d');
        $ahora = date('H:i:s');

        // Solo permitir si el tipo de usuario es DOCENTE (id_tp_user = 7)
        $idTpUser = $this->getUserTipo($uid);
        if ($idTpUser !== 7) {
            return 'Solo usuarios con tipo DOCENTE pueden registrar fines de docente.';
        }

        $direccion = $this->normalizeDireccion($input['direccion'] ?? null);
        [$latitud, $longitud] = $this->validateUbicacion($input['latitud'] ?? null, $input['longitud'] ?? null, $direccion);
        $this->db->beginTransaction();
        try {
            $idFecha = $this->getOrCreateFechaRegistro($uid, $hoy);
            $q = $this->db->prepare('SELECT COUNT(*) FROM horario_fin_docente_1 WHERE id_usuario = ? AND id_fecha_registro = ?');
            $q->execute([$uid, $idFecha]);
            if ((int)$q->fetchColumn() >= 3) {
                $this->db->rollBack();
                return 'Ya registraste 3 fines hoy.';
            }
            if ($this->docenteSlotIndex('fin', $tz, $ahora) === null) {
                $this->db->rollBack();
                return 'Fuera de la ventana permitida para fin (19:30, 20:30 o 21:30 con 10 min antes).';
            }
            if ($this->docenteSlotUsed('horario_fin_docente_1', 'hora_sl_doc', $uid, $idFecha, $tz, $ahora, 'fin')) {
                $this->db->rollBack();
                return 'Ya registraste en esta ventana de fin.';
            }
            $ins = $this->db->prepare('INSERT INTO horario_fin_docente_1 (id_usuario, id_fecha_registro, hora_sl_doc, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $idFecha, $ahora, $latitud, $longitud, $direccion]);
            $this->db->commit();
            return 'Fin docente registrado a las ' . $ahora . '.';
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
