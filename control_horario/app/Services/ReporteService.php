<?php
namespace App\Services;

use PDO;

class ReporteService
{
    private PDO $db;
    private string $schemaPrefix;
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->schemaPrefix = \isMssql() ? (\dbSchema() . '.') : '';
    }

    public function horarioProgramaUsuario(int $uid): array
    {
        $sql = \isMssql()
            ? "SELECT TOP 1 u.id_tp_user, he.hora_ingreso_personal, hs.hora_salida_personal
               FROM {$this->schemaPrefix}usuario u
          LEFT JOIN {$this->schemaPrefix}horario_entrada_personal he ON he.id_usuario = u.id_usuario AND he.id_tp_user = u.id_tp_user
          LEFT JOIN {$this->schemaPrefix}horario_salida_personal  hs ON hs.id_usuario = u.id_usuario AND hs.id_tp_user = u.id_tp_user
              WHERE u.id_usuario = ?"
            : "SELECT u.id_tp_user, he.hora_ingreso_personal, hs.hora_salida_personal
               FROM {$this->schemaPrefix}usuario u
          LEFT JOIN {$this->schemaPrefix}horario_entrada_personal he ON he.id_usuario = u.id_usuario AND he.id_tp_user = u.id_tp_user
          LEFT JOIN {$this->schemaPrefix}horario_salida_personal  hs ON hs.id_usuario = u.id_usuario AND hs.id_tp_user = u.id_tp_user
              WHERE u.id_usuario = ?
              LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uid]);
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'hora_ingreso_personal' => $cfg['hora_ingreso_personal'] ?? null,
            'hora_salida_personal'  => $cfg['hora_salida_personal'] ?? null,
        ];
    }

    public function timbres(int $uid, string $desde, string $hasta): array
    {
        $sql = "
        SELECT
            fr.id_fecha_registro,
            fr.fecha_ingreso                                     AS fecha,

            hi.hora_ingreso, ei.detalle_ingreso                  AS estado_ingreso,
            hi.latitud   AS lat_in,  hi.longitud AS lon_in,  hi.direccion AS dir_in,

            sla.hora_sl_almuerzo, esa.detalle_salida_almuerzo    AS estado_sl_alm,
            sla.latitud  AS lat_sl,  sla.longitud AS lon_sl, sla.direccion AS dir_sl,

            rta.hora_rt_almuerzo, era.detalle_retorno_almuerzo   AS estado_rt_alm,
            rta.latitud  AS lat_rt,  rta.longitud AS lon_rt, rta.direccion AS dir_rt,

            sa.hora_salida, es.detalle_salida                    AS estado_salida,
            sa.latitud   AS lat_out, sa.longitud AS lon_out, sa.direccion  AS dir_out

        FROM {$this->schemaPrefix}fecha_registro fr
        LEFT JOIN {$this->schemaPrefix}horario_ingreso hi
               ON hi.id_usuario = fr.id_usuario AND hi.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_ingreso ei
               ON ei.id_estado_ingreso = hi.id_estado_ingreso

        LEFT JOIN {$this->schemaPrefix}horario_sl_almuerzo sla
               ON sla.id_usuario = fr.id_usuario AND sla.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_salida_almuerzo esa
               ON esa.id_estado_salida_almuerzo = sla.id_estado_salida_almuerzo

        LEFT JOIN {$this->schemaPrefix}horario_rt_almuerzo rta
               ON rta.id_usuario = fr.id_usuario AND rta.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_retorno_almuerzo era
               ON era.id_estado_retorno_almuerzo = rta.id_estado_retorno_almuerzo

        LEFT JOIN {$this->schemaPrefix}horario_salida sa
               ON sa.id_usuario = fr.id_usuario AND sa.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_salida es
               ON es.id_estado_salida = sa.id_estado_salida

        WHERE fr.id_usuario = :uid
          AND fr.fecha_ingreso BETWEEN :desde AND :hasta
        ORDER BY fr.fecha_ingreso DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid',   $uid,   PDO::PARAM_INT);
        $stmt->bindValue(':desde', $desde, PDO::PARAM_STR);
        $stmt->bindValue(':hasta', $hasta, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function timbresAll(string $desde, string $hasta, ?string $nameFilter = null, ?string $rolFilter = null): array
    {
        $usuarioExpr = \isMssql()
            ? "LTRIM(RTRIM(COALESCE(u.primer_nombre, '') + ' ' + COALESCE(u.primer_apellido, '')))"
            : "CONCAT_WS(' ', u.primer_nombre, u.primer_apellido)";
        $nameSearchExpr = \isMssql()
            ? "LTRIM(RTRIM(COALESCE(u.primer_nombre, '') + ' ' + COALESCE(u.segundo_nombre, '') + ' ' + COALESCE(u.primer_apellido, '') + ' ' + COALESCE(u.segundo_apellido, '')))"
            : "CONCAT_WS(' ', u.primer_nombre, u.segundo_nombre, u.primer_apellido, u.segundo_apellido)";
        $toUpper = static function (string $v): string {
            return function_exists('mb_strtoupper') ? mb_strtoupper($v, 'UTF-8') : strtoupper($v);
        };

        $sql = "
        SELECT DISTINCT
            fr.fecha_ingreso                                     AS fecha,
            u.id_usuario,
            {$usuarioExpr}                                        AS usuario,
            tu.detalle_tp_user                                   AS rol,
            he.hora_ingreso_personal                              AS hora_prog_in,
            hs.hora_salida_personal                               AS hora_prog_out,

            hi.hora_ingreso,
            ei.detalle_ingreso                                   AS estado_ingreso,
            hi.latitud   AS lat_in,  hi.longitud AS lon_in,  hi.direccion AS dir_in,

            sla.hora_sl_almuerzo,
            esa.detalle_salida_almuerzo                          AS estado_sl_alm,
            sla.latitud  AS lat_sl,  sla.longitud AS lon_sl, sla.direccion AS dir_sl,

            rta.hora_rt_almuerzo,
            era.detalle_retorno_almuerzo                         AS estado_rt_alm,
            rta.latitud  AS lat_rt,  rta.longitud AS lon_rt, rta.direccion AS dir_rt,

            sa.hora_salida,
            es.detalle_salida                                    AS estado_salida,
            sa.latitud   AS lat_out, sa.longitud AS lon_out, sa.direccion  AS dir_out

        FROM {$this->schemaPrefix}fecha_registro fr
        INNER JOIN {$this->schemaPrefix}usuario u ON u.id_usuario = fr.id_usuario
        LEFT JOIN {$this->schemaPrefix}tipo_usuario tu ON tu.id_tp_user = u.id_tp_user
        LEFT JOIN {$this->schemaPrefix}horario_entrada_personal he ON he.id_usuario = u.id_usuario AND he.id_tp_user = u.id_tp_user
        LEFT JOIN {$this->schemaPrefix}horario_salida_personal  hs ON hs.id_usuario = u.id_usuario AND hs.id_tp_user = u.id_tp_user

        LEFT JOIN {$this->schemaPrefix}horario_ingreso hi
               ON hi.id_usuario = fr.id_usuario AND hi.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_ingreso ei
               ON ei.id_estado_ingreso = hi.id_estado_ingreso

        LEFT JOIN {$this->schemaPrefix}horario_sl_almuerzo sla
               ON sla.id_usuario = fr.id_usuario AND sla.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_salida_almuerzo esa
               ON esa.id_estado_salida_almuerzo = sla.id_estado_salida_almuerzo

        LEFT JOIN {$this->schemaPrefix}horario_rt_almuerzo rta
               ON rta.id_usuario = fr.id_usuario AND rta.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_retorno_almuerzo era
               ON era.id_estado_retorno_almuerzo = rta.id_estado_retorno_almuerzo

        LEFT JOIN {$this->schemaPrefix}horario_salida sa
               ON sa.id_usuario = fr.id_usuario AND sa.id_fecha_registro = fr.id_fecha_registro
        LEFT JOIN {$this->schemaPrefix}estado_salida es
               ON es.id_estado_salida = sa.id_estado_salida

        WHERE fr.fecha_ingreso BETWEEN :desde AND :hasta
          AND (u.id_tp_user IS NULL OR u.id_tp_user <> 1)
          /**filters**/
        ORDER BY fr.fecha_ingreso DESC, u.primer_apellido, u.primer_nombre";

        $filters = '';
        $params = [':desde'=>$desde, ':hasta'=>$hasta];
        if ($nameFilter !== null && $nameFilter !== '') {
            $filters .= " AND UPPER({$nameSearchExpr}) LIKE :q ";
            $params[':q'] = '%' . $toUpper($nameFilter) . '%';
        }
        if ($rolFilter !== null && $rolFilter !== '') {
            $filters .= " AND UPPER(tu.detalle_tp_user) = :rol ";
            $params[':rol'] = $toUpper($rolFilter);
        }
        $sql = str_replace('/**filters**/', $filters, $sql);

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v, \PDO::PARAM_STR); }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function timbresDocente(int $uid, string $desde, string $hasta): array
    {
        if (\isMssql()) {
            $ingSub = "SELECT STRING_AGG(CONCAT(ISNULL(hi.hora_ing_doc,''),'|',ISNULL(hi.latitud,''),'|',ISNULL(hi.longitud,''),'|',ISNULL(hi.direccion,'')),';;') WITHIN GROUP (ORDER BY hi.hora_ing_doc)
              FROM {$this->schemaPrefix}horario_docente_ingreso_1 hi
              WHERE hi.id_usuario = fr.id_usuario AND hi.id_fecha_registro = fr.id_fecha_registro";
            $salSub = "SELECT STRING_AGG(CONCAT(ISNULL(hf.hora_sl_doc,''),'|',ISNULL(hf.latitud,''),'|',ISNULL(hf.longitud,''),'|',ISNULL(hf.direccion,'')),';;') WITHIN GROUP (ORDER BY hf.hora_sl_doc)
              FROM {$this->schemaPrefix}horario_fin_docente_1 hf
              WHERE hf.id_usuario = fr.id_usuario AND hf.id_fecha_registro = fr.id_fecha_registro";
        } else {
            $ingSub = "SELECT GROUP_CONCAT(CONCAT(IFNULL(hi.hora_ing_doc,''''),'|',IFNULL(hi.latitud,''''),'|',IFNULL(hi.longitud,''''),'|',IFNULL(hi.direccion,'''')) ORDER BY hi.hora_ing_doc SEPARATOR ';;')
              FROM {$this->schemaPrefix}horario_docente_ingreso_1 hi
              WHERE hi.id_usuario = fr.id_usuario AND hi.id_fecha_registro = fr.id_fecha_registro";
            $salSub = "SELECT GROUP_CONCAT(CONCAT(IFNULL(hf.hora_sl_doc,''''),'|',IFNULL(hf.latitud,''''),'|',IFNULL(hf.longitud,''''),'|',IFNULL(hf.direccion,'''')) ORDER BY hf.hora_sl_doc SEPARATOR ';;')
              FROM {$this->schemaPrefix}horario_fin_docente_1 hf
              WHERE hf.id_usuario = fr.id_usuario AND hf.id_fecha_registro = fr.id_fecha_registro";
        }
        $sql = "
        SELECT
            fr.id_fecha_registro,
            fr.fecha_ingreso AS fecha,
            (
              {$ingSub}
            ) AS ingresos,
            (
              {$salSub}
            ) AS salidas
        FROM {$this->schemaPrefix}fecha_registro fr
        WHERE fr.id_usuario = :uid
          AND fr.fecha_ingreso BETWEEN :desde AND :hasta
        ORDER BY fr.fecha_ingreso DESC";
        $st = $this->db->prepare($sql);
        $st->bindValue(':uid', $uid, PDO::PARAM_INT);
        $st->bindValue(':desde', $desde, PDO::PARAM_STR);
        $st->bindValue(':hasta', $hasta, PDO::PARAM_STR);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
