<?php
declare(strict_types=1);

/**
 * 005_multiempresa — convierte el CRM mono-empresa en multiempresa.
 *
 * Qué hace:
 *   1. Crea `empresas` y da de alta la empresa 1 con los datos actuales
 *      (todo lo que hoy existe en la base pertenece a ella).
 *   2. Crea `invitaciones` para el alta de usuarios por token.
 *   3. Añade `empresa_id` a cada tabla de negocio y la rellena derivándola
 *      del dueño real del registro (cotización → cliente → agente → empresa),
 *      no asignando 1 a ciegas: así la migración es correcta incluso si ya
 *      se cargaron datos de más de una operación.
 *   4. Añade el rol `propietario` (dueño de la plataforma, ve todas las
 *      empresas) por encima de la jerarquía existente.
 *   5. Crea índices compuestos para que las consultas filtradas por empresa
 *      sigan siendo rápidas, y llaves foráneas con borrado en cascada.
 *
 * Es idempotente: se puede correr varias veces sin romper nada.
 * `down()` revierte el cambio dejando los datos de negocio intactos.
 */

use Database\SchemaHelper;

/**
 * Tablas de negocio que pasan a estar aisladas por empresa.
 *
 * 'derivar' indica de dónde se hereda la empresa en el backfill:
 *   [columna_local, tabla_padre]  → SELECT empresa_id FROM padre WHERE padre.id = tabla.columna_local
 * Se evalúan en orden; el primero que resuelva gana. Lo que quede sin
 * resolver cae a la empresa por defecto.
 */
const LUX_TABLAS_TENANT = [
    'clientes' => [
        'derivar' => [['agente_id', 'agentes']],
        'indices' => [['idx_emp_agente', ['empresa_id', 'agente_id']], ['idx_emp_estado', ['empresa_id', 'estado_cliente']]],
    ],
    'citas' => [
        'derivar' => [['cliente_id', 'clientes'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_fecha', ['empresa_id', 'fecha']], ['idx_emp_agente', ['empresa_id', 'agente_id']]],
    ],
    'cotizaciones' => [
        'derivar' => [['cliente_id', 'clientes'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_agente', ['empresa_id', 'agente_id']], ['idx_emp_estado', ['empresa_id', 'estado']]],
    ],
    'polizas' => [
        'derivar' => [['cliente_id', 'clientes'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_estado', ['empresa_id', 'estado']], ['idx_emp_agente', ['empresa_id', 'agente_id']]],
    ],
    'cotizacion_archivos' => [
        'derivar' => [['cotizacion_id', 'cotizaciones'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_cot', ['empresa_id', 'cotizacion_id']]],
    ],
    'cotizacion_pagos' => [
        'derivar' => [['cotizacion_id', 'cotizaciones'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_cot', ['empresa_id', 'cotizacion_id']]],
    ],
    'cotizacion_comprobantes_pago' => [
        'derivar' => [['cotizacion_id', 'cotizaciones'], ['coordinador_id', 'agentes']],
        'indices' => [['idx_emp_cot', ['empresa_id', 'cotizacion_id']]],
    ],
    'cotizacion_detalles' => [
        'derivar' => [['cotizacion_id', 'cotizaciones']],
        'indices' => [['idx_emp_cot', ['empresa_id', 'cotizacion_id']]],
    ],
    'emi_checklist' => [
        'derivar' => [['cotizacion_id', 'cotizaciones']],
        'indices' => [['idx_emp_cot', ['empresa_id', 'cotizacion_id']]],
    ],
    'historial_cliente' => [
        'derivar' => [['cliente_id', 'clientes'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_cliente', ['empresa_id', 'cliente_id']]],
    ],
    'historial_coordinador' => [
        'derivar' => [['coordinador_id', 'agentes'], ['cliente_id', 'clientes']],
        'indices' => [['idx_emp_coord', ['empresa_id', 'coordinador_id']]],
    ],
    'notificaciones' => [
        'derivar' => [['agente_id', 'agentes']],
        'indices' => [['idx_emp_agente_leida', ['empresa_id', 'agente_id', 'leida']]],
    ],
    'notas' => [
        'derivar' => [['cliente_id', 'clientes'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_cliente', ['empresa_id', 'cliente_id']]],
    ],
    'mail_queue' => [
        'derivar' => [],
        'indices' => [['idx_emp_status', ['empresa_id', 'status']]],
    ],
    'renovacion_notificaciones_programadas' => [
        'derivar' => [['poliza_id', 'polizas'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_enviada', ['empresa_id', 'enviada']]],
    ],
    'meta_leads' => [
        'derivar' => [['cliente_id', 'clientes'], ['agente_id', 'agentes']],
        'indices' => [['idx_emp_form', ['empresa_id', 'form_id']]],
    ],
    'vida_solicitudes' => [
        'derivar' => [],
        'indices' => [['idx_emp_estado', ['empresa_id', 'estado']]],
    ],
];

return [

    // ─────────────────────────────────────────────────────────────────────────
    'up' => function (PDO $db, SchemaHelper $s): void {

        $esSqlite = $s->esSqlite();
        $ts       = $esSqlite ? "(datetime('now'))" : 'CURRENT_TIMESTAMP';
        $ai       = $esSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $suffix   = $esSqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // ── 1. Tabla de empresas ─────────────────────────────────────────────
        if (!$s->tablaExiste('empresas')) {
            $db->exec("
                CREATE TABLE empresas (
                    id                {$ai},
                    nombre            VARCHAR(150) NOT NULL,
                    slug              VARCHAR(80)  NOT NULL,
                    razon_social      VARCHAR(200) DEFAULT NULL,
                    rfc               VARCHAR(20)  DEFAULT NULL,
                    correo_contacto   VARCHAR(150) DEFAULT NULL,
                    telefono          VARCHAR(30)  DEFAULT NULL,
                    direccion         VARCHAR(255) DEFAULT NULL,
                    logo              VARCHAR(255) DEFAULT NULL,
                    color_primario    VARCHAR(9)   DEFAULT NULL,
                    plan              VARCHAR(30)  NOT NULL DEFAULT 'basico',
                    max_usuarios      INT          NOT NULL DEFAULT 10,
                    activo            TINYINT      NOT NULL DEFAULT 1,
                    notas             TEXT         DEFAULT NULL,
                    creada_por        INT          DEFAULT NULL,
                    creado_en         DATETIME     NOT NULL DEFAULT {$ts},
                    actualizado_en    DATETIME     DEFAULT NULL,
                    eliminado_en      DATETIME     DEFAULT NULL
                ){$suffix}
            ");
            $s->crearIndice('empresas', 'uq_empresa_slug', ['slug'], true);
            $s->crearIndice('empresas', 'idx_empresa_activo', ['activo']);
        }

        // Empresa por defecto: todo lo que ya existe pertenece a ella.
        $existe = (int) $db->query('SELECT COUNT(*) FROM empresas')->fetchColumn();
        if ($existe === 0) {
            $db->prepare(
                'INSERT INTO empresas (id, nombre, slug, razon_social, plan, max_usuarios, activo, creado_en)
                 VALUES (1, ?, ?, ?, ?, ?, 1, ' . ($s->esSqlite() ? "datetime('now')" : 'NOW()') . ')'
            )->execute(['Seguros Lux', 'seguros-lux', 'Seguros Lux', 'ilimitado', 999]);
        }

        $empresaDefault = (int) $db->query('SELECT MIN(id) FROM empresas')->fetchColumn() ?: 1;

        // ── 2. Agentes: pertenencia a empresa y metadatos de cuenta ──────────
        $s->agregarColumna('agentes', 'empresa_id', $s->tipoEntero() . ' DEFAULT NULL');
        $s->agregarColumna('agentes', 'creado_en', "DATETIME DEFAULT NULL");
        $s->agregarColumna('agentes', 'ultimo_acceso', 'DATETIME DEFAULT NULL');
        $s->agregarColumna('agentes', 'debe_cambiar_password', 'TINYINT NOT NULL DEFAULT 0');
        $s->agregarColumna('agentes', 'creado_por', $s->tipoEntero() . ' DEFAULT NULL');

        // Los agentes existentes son de la empresa por defecto.
        $db->prepare('UPDATE agentes SET empresa_id = ? WHERE empresa_id IS NULL')
           ->execute([$empresaDefault]);

        // El rol `propietario` se añade al ENUM (en MySQL hay que reescribirlo).
        if (!$esSqlite) {
            $tipo = (string) $s->tipoColumna('agentes', 'rol');
            if ($tipo !== '' && str_starts_with($tipo, 'enum') && !str_contains($tipo, "'propietario'")) {
                $db->exec(
                    "ALTER TABLE agentes MODIFY COLUMN rol
                     ENUM('asesor','agente','coordinador','administrador','superadmin','propietario')
                     NOT NULL DEFAULT 'asesor'"
                );
            }
        }

        $s->crearIndice('agentes', 'idx_agente_empresa', ['empresa_id']);
        $s->crearIndice('agentes', 'idx_agente_empresa_rol', ['empresa_id', 'rol']);

        // El correo deja de ser único global: dos empresas distintas pueden
        // tener al mismo correo como usuario. Pasa a ser único por empresa.
        if (!$esSqlite) {
            try {
                if ($s->indiceExiste('agentes', 'correo')) {
                    $db->exec('ALTER TABLE agentes DROP INDEX `correo`');
                }
            } catch (\PDOException $e) {
                // Si el índice tiene otro nombre o ya no está, seguimos.
            }
        }
        $s->crearIndice('agentes', 'uq_agente_empresa_correo', ['empresa_id', 'correo'], true);

        // ── 3. Invitaciones (alta de usuarios por token) ─────────────────────
        if (!$s->tablaExiste('invitaciones')) {
            $db->exec("
                CREATE TABLE invitaciones (
                    id             {$ai},
                    empresa_id     " . $s->tipoEntero() . " NOT NULL,
                    correo         VARCHAR(150) NOT NULL,
                    nombre         VARCHAR(100) NOT NULL DEFAULT '',
                    apellido       VARCHAR(100) NOT NULL DEFAULT '',
                    telefono       VARCHAR(30)  NOT NULL DEFAULT '',
                    rol            VARCHAR(30)  NOT NULL DEFAULT 'asesor',
                    puesto         VARCHAR(40)  NOT NULL DEFAULT 'asesor',
                    token_hash     CHAR(64)     NOT NULL,
                    estado         VARCHAR(20)  NOT NULL DEFAULT 'pendiente',
                    invitado_por   " . $s->tipoEntero() . " DEFAULT NULL,
                    agente_id      " . $s->tipoEntero() . " DEFAULT NULL,
                    expira_en      DATETIME     NOT NULL,
                    aceptada_en    DATETIME     DEFAULT NULL,
                    cancelada_en   DATETIME     DEFAULT NULL,
                    intentos       INT          NOT NULL DEFAULT 0,
                    creado_en      DATETIME     NOT NULL DEFAULT {$ts}
                ){$suffix}
            ");
            // El token se guarda hasheado: si se filtra la base, los enlaces
            // de invitación pendientes no sirven para nada.
            $s->crearIndice('invitaciones', 'uq_invitacion_token', ['token_hash'], true);
            $s->crearIndice('invitaciones', 'idx_invitacion_empresa', ['empresa_id', 'estado']);
            $s->crearIndice('invitaciones', 'idx_invitacion_correo', ['correo']);
        }

        // ── 4. empresa_id en cada tabla de negocio + backfill derivado ───────
        foreach (LUX_TABLAS_TENANT as $tabla => $cfg) {
            if (!$s->tablaExiste($tabla)) {
                continue; // tablas creadas en runtime que aún no existen
            }

            $s->agregarColumna($tabla, 'empresa_id', $s->tipoEntero() . ' DEFAULT NULL');

            // Hereda la empresa del dueño real del registro.
            foreach ($cfg['derivar'] as [$columnaLocal, $tablaPadre]) {
                if (!$s->columnaExiste($tabla, $columnaLocal) || !$s->tablaExiste($tablaPadre)) {
                    continue;
                }
                if (!$s->columnaExiste($tablaPadre, 'empresa_id')) {
                    continue;
                }

                // Subconsulta correlacionada: portable entre MySQL y SQLite.
                $db->exec(sprintf(
                    'UPDATE %1$s SET empresa_id = (
                         SELECT p.empresa_id FROM %2$s p WHERE p.id = %1$s.%3$s
                     )
                     WHERE empresa_id IS NULL
                       AND %3$s IS NOT NULL
                       AND EXISTS (SELECT 1 FROM %2$s p2 WHERE p2.id = %1$s.%3$s AND p2.empresa_id IS NOT NULL)',
                    $s->quoteIdent($tabla),
                    $s->quoteIdent($tablaPadre),
                    $s->quoteIdent($columnaLocal)
                ));
            }

            // Lo que no pudo derivarse (huérfanos, leads sin dueño) va a la
            // empresa por defecto en vez de quedar invisible para todos.
            $db->prepare(sprintf(
                'UPDATE %s SET empresa_id = ? WHERE empresa_id IS NULL',
                $s->quoteIdent($tabla)
            ))->execute([$empresaDefault]);

            foreach ($cfg['indices'] as [$nombre, $columnas]) {
                $s->crearIndice($tabla, $nombre, $columnas);
            }
            $s->crearIndice($tabla, 'idx_' . substr($tabla, 0, 20) . '_empresa', ['empresa_id']);
        }

        // ── 5. Configuración: por empresa, con 0 = global de plataforma ──────
        if ($s->tablaExiste('configuracion')) {
            $s->agregarColumna('configuracion', 'empresa_id', $s->tipoEntero() . ' NOT NULL DEFAULT 0');

            // Las claves de integración (Meta, WhatsApp) siguen siendo de
            // plataforma; el resto pasa a ser ajuste de la empresa por defecto.
            $db->prepare(
                "UPDATE configuracion SET empresa_id = ?
                 WHERE empresa_id = 0
                   AND clave NOT LIKE 'meta_%'
                   AND clave NOT LIKE 'wa_%'"
            )->execute([$empresaDefault]);

            // `clave` deja de ser única global: ahora es única por empresa.
            if (!$esSqlite) {
                foreach (['clave', 'uq_configuracion_clave'] as $idx) {
                    try {
                        if ($s->indiceExiste('configuracion', $idx)) {
                            $db->exec('ALTER TABLE configuracion DROP INDEX ' . $s->quoteIdent($idx));
                        }
                    } catch (\PDOException $e) {
                        // seguimos
                    }
                }
            }
            $s->crearIndice('configuracion', 'uq_config_empresa_clave', ['empresa_id', 'clave'], true);
        }

        // ── 6. Llaves foráneas ───────────────────────────────────────────────
        // Se añaden al final, cuando ya no quedan filas sin empresa.
        $s->agregarFk('agentes', 'fk_agentes_empresa', 'empresa_id', 'empresas', 'id', 'SET NULL');
        $s->agregarFk('invitaciones', 'fk_invitaciones_empresa', 'empresa_id', 'empresas', 'id', 'CASCADE');

        foreach (array_keys(LUX_TABLAS_TENANT) as $tabla) {
            if ($s->tablaExiste($tabla)) {
                $s->agregarFk($tabla, 'fk_' . $tabla . '_empresa', 'empresa_id', 'empresas', 'id', 'CASCADE');
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    'down' => function (PDO $db, SchemaHelper $s): void {

        // Se quitan primero las FKs, luego las columnas. Los datos de negocio
        // quedan intactos: solo desaparece la separación por empresa.
        foreach (array_keys(LUX_TABLAS_TENANT) as $tabla) {
            if (!$s->tablaExiste($tabla)) {
                continue;
            }
            $s->quitarFk($tabla, 'fk_' . $tabla . '_empresa');
            $s->quitarColumna($tabla, 'empresa_id');
        }

        $s->quitarFk('agentes', 'fk_agentes_empresa');
        $s->quitarIndice('agentes', 'uq_agente_empresa_correo');
        $s->quitarIndice('agentes', 'idx_agente_empresa_rol');
        $s->quitarIndice('agentes', 'idx_agente_empresa');

        foreach (['empresa_id', 'creado_en', 'ultimo_acceso', 'debe_cambiar_password', 'creado_por'] as $col) {
            $s->quitarColumna('agentes', $col);
        }

        if ($s->tablaExiste('configuracion')) {
            $s->quitarIndice('configuracion', 'uq_config_empresa_clave');
            $s->quitarColumna('configuracion', 'empresa_id');
        }

        $s->quitarFk('invitaciones', 'fk_invitaciones_empresa');
        $db->exec('DROP TABLE IF EXISTS invitaciones');
        $db->exec('DROP TABLE IF EXISTS empresas');
    },
];
