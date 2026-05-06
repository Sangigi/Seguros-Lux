<?php
declare(strict_types=1);

/**
 * tests/bootstrap.php — Entorno de pruebas de Seguros Lux.
 *
 * Responsabilidades:
 *   1. Marcar el proceso como entorno de testing.
 *   2. Cargar el autoloader de Composer (PHPUnit y clases del proyecto).
 *   3. Registrar el autoloader propio del CRM (Core/Autoloader.php).
 *   4. Conectar la BD de testing (SQLite en memoria) e inyectarla en
 *      Database\Connection para que todos los modelos la usen.
 *   5. Crear el esquema mínimo necesario para los tests de integración.
 *
 * FILOSOFÍA:
 *   Los tests de integración usan SQLite (:memory:) para:
 *     - No depender de MySQL en CI (GitHub Actions no levanta un servidor).
 *     - Velocidad: las transacciones en memoria son ~100× más rápidas.
 *     - Aislamiento: cada ejecución parte de cero.
 *
 *   Los tests unitarios pasan mocks de PDO y no llegan a este setup.
 */

putenv('APP_ENV=testing');

// ── 1. Composer autoload ───────────────────────────────────────────────────────
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "ERROR: Ejecuta `composer install` antes de correr los tests.\n");
    exit(1);
}
require_once $autoload;

// ── 2. Autoloader propio del CRM ──────────────────────────────────────────────
$crmAutoloader = dirname(__DIR__) . '/Core/Autoloader.php';
if (is_file($crmAutoloader)) {
    require_once $crmAutoloader;
}

// ── 3. Helpers globales ───────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/Core/helpers.php';

// ── 4. BD en memoria (SQLite) ─────────────────────────────────────────────────
//
// Sobrescribimos Database\Connection con una subclase que devuelve
// siempre la misma instancia de SQLite en memoria.
// Así todos los modelos que llamen Connection::getConnection()
// reciben la BD de testing sin ningún cambio en el código de producción.

use Database\TestConnection;
use Database\Connection;

/**
 * Crea y devuelve una conexión PDO a SQLite en memoria con el esquema del CRM.
 * Se llama UNA sola vez; las llamadas siguientes devuelven la misma instancia.
 */
function createTestPdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

    // SQLite no tiene ENUM — se usa TEXT con CHECK para aproximar.
    $pdo->exec(/** @lang SQLite */ <<<'SQL'

        -- ── agentes ──────────────────────────────────────────────────────────
        CREATE TABLE agentes (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre      TEXT    NOT NULL,
            apellido    TEXT    NOT NULL DEFAULT '',
            correo      TEXT    NOT NULL UNIQUE,
            telefono    TEXT    NOT NULL DEFAULT '',
            contrasena  TEXT    NOT NULL,
            rol         TEXT    NOT NULL DEFAULT 'asesor'
                            CHECK(rol IN ('asesor','agente','coordinador','admin','superadmin')),
            activo      INTEGER NOT NULL DEFAULT 1
        );

        -- ── clientes ─────────────────────────────────────────────────────────
        CREATE TABLE clientes (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre            TEXT    NOT NULL,
            apellido_paterno  TEXT    NOT NULL DEFAULT '',
            apellido_materno  TEXT    NOT NULL DEFAULT '',
            correo            TEXT,
            telefono          TEXT,
            marca             TEXT,
            modelo            TEXT,
            anio_vehiculo     INTEGER,
            tipo_vehiculo     TEXT,
            ciudad            TEXT,
            estado_cliente    TEXT    DEFAULT 'activo',
            asegurado         TEXT,
            fecha_registro    TEXT    DEFAULT (datetime('now')),
            agente_id         INTEGER,
            ramo              TEXT
        );

        -- ── cotizaciones ─────────────────────────────────────────────────────
        CREATE TABLE cotizaciones (
            id                      INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id              INTEGER NOT NULL,
            agente_id               INTEGER NOT NULL,
            ramo                    TEXT,
            estado                  TEXT    DEFAULT 'pendiente',
            precio                  REAL    DEFAULT 0,
            tipo_cobertura          TEXT,
            emision_estado          TEXT    DEFAULT 'pendiente',
            emision_coordinador_id  INTEGER,
            fecha_creacion          TEXT    DEFAULT (datetime('now')),
            fecha_fin_vigencia      TEXT
        );

        -- ── polizas ──────────────────────────────────────────────────────────
        CREATE TABLE polizas (
            id                     INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id             INTEGER NOT NULL,
            agente_id              INTEGER NOT NULL,
            numero_poliza          TEXT,
            fecha_inicio           TEXT,
            fecha_fin              TEXT,
            renovacion_notificada  INTEGER DEFAULT 0,
            eliminado_en           TEXT    DEFAULT NULL
        );

        -- ── historial_cliente ─────────────────────────────────────────────────
        CREATE TABLE historial_cliente (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id  INTEGER NOT NULL,
            agente_id   INTEGER NOT NULL,
            tipo        TEXT,
            titulo      TEXT,
            descripcion TEXT,
            fecha       TEXT    DEFAULT (datetime('now'))
        );

        -- ── notificaciones ────────────────────────────────────────────────────
        CREATE TABLE notificaciones (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            agente_id   INTEGER NOT NULL,
            mensaje     TEXT,
            leida       INTEGER DEFAULT 0,
            creada_en   TEXT    DEFAULT (datetime('now'))
        );

        -- ── mail_queue ────────────────────────────────────────────────────────
        CREATE TABLE mail_queue (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            to_address     TEXT    NOT NULL,
            to_name        TEXT    NOT NULL DEFAULT '',
            reply_to       TEXT,
            subject        TEXT    NOT NULL,
            body           TEXT    NOT NULL,
            status         TEXT    NOT NULL DEFAULT 'pending',
            tries          INTEGER NOT NULL DEFAULT 0,
            max_tries      INTEGER NOT NULL DEFAULT 3,
            last_error     TEXT,
            created_at     TEXT    DEFAULT (datetime('now')),
            last_tried_at  TEXT,
            sent_at        TEXT
        );

    SQL);

    return $pdo;
}

// ── 5. Inyectar la conexión de testing en la clase Connection ─────────────────
//
// Si Database\Connection tiene un método setConnection() (lo agregamos abajo),
// lo usamos. Si no, parchamos la clase en runtime usando el autoloader.
// La solución más limpia: agregar setTestConnection() a Connection en producción.
// Ver Database/Connection.php — ya incluye este método en la versión mejorada.

if (method_exists(Database\Connection::class, 'setTestConnection')) {
    Database\Connection::setTestConnection(createTestPdo());
}