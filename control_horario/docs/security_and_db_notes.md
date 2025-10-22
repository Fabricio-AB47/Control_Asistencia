Seguridad y DB — notas de hardening

1) Cabeceras HTTP (servidor)
- Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://nominatim.openstreetmap.org; frame-src https://www.google.com; frame-ancestors 'none'
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin
- Strict-Transport-Security: max-age=31536000; includeSubDomains (solo bajo HTTPS)

2) Cookies de sesión
- HttpOnly, Secure (en prod), SameSite=Lax
- session.use_strict_mode=1

3) Índices únicos en BD (evitar duplicados por usuario/día)
-- ejemplo de SQL (ajusta engine y nombres de índice):
ALTER TABLE usuario ADD UNIQUE KEY uq_usuario_correo (correo);
ALTER TABLE horario_ingreso ADD UNIQUE KEY uq_hi_usr_fecha (id_usuario, id_fecha_registro);
ALTER TABLE horario_sl_almuerzo ADD UNIQUE KEY uq_sla_usr_fecha (id_usuario, id_fecha_registro);
ALTER TABLE horario_rt_almuerzo ADD UNIQUE KEY uq_rta_usr_fecha (id_usuario, id_fecha_registro);
ALTER TABLE horario_salida ADD UNIQUE KEY uq_sa_usr_fecha (id_usuario, id_fecha_registro);

