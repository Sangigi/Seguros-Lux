<?php
declare(strict_types=1);

/**
 * tests/bootstrap.php — Entorno de pruebas de Seguros Lux.
 *
 * CORRECCIONES respecto a la versión anterior:
 *   - Eliminado: `use Database\TestConnection` (la clase no existe — error fatal).
 *   - Eliminado: `use Database\Connection` en el bloque use (no es necesario,
 *     el FQCN se usa directamente abajo).
 *   - Simplificado: la inyección de SQLite ahora comprueba primero si
 *     `setTestConnection()` existe (ya está en Connection.php) y lo llama
 *     con el FQCN completo, sin imports que podían generar conflictos.
 *
 * Responsabilidades:
 *   1. Marcar el proceso como entorno de testing (APP_ENV=testing).
 *   2. Cargar Composer autoload y el autoloader propio del CRM.
 *   3. Crear una conexión PDO SQLite en memoria con el esquema completo.
 *   4. Inyectarla en Database\Connection para que todos los modelos la usen.
 */

putenv('APP_ENV=testing');

// ── 1. Composer autoload ──────────────────────────────────────────────────────
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

// ── 4. SQLite en memoria con esquema del CRM ──────────────────────────────────

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

    // SQLite no tiene NOW() ni funciones MySQL de fecha — las registramos
    // como funciones PHP para que el código de producción funcione sin cambios.
    $pdo->sqliteCreateFunction('NOW',      fn() => date('Y-m-d H:i:s'), 0);
    $pdo->sqliteCreateFunction('CURDATE',  fn() => date('Y-m-d'),       0);
    $pdo->sqliteCreateFunction('DATEDIFF', fn($a, $b) => (int)((strtotime($a) - strtotime($b)) / 86400), 2);
    $pdo->sqliteCreateFunction('INTERVAL', fn($n, $unit) => $n, 2); // stub — solo para parsing
    $pdo->sqliteCreateFunction('YEAR',     fn($d) => $d ? (int)date('Y', strtotime($d)) : null, 1);
    $pdo->sqliteCreateFunction('MONTH',    fn($d) => $d ? (int)date('n', strtotime($d)) : null, 1);
    $pdo->sqliteCreateFunction('CONCAT',   fn(...$args) => implode('', $args), -1);
    $pdo->sqliteCreateFunction('DATE_SUB', fn($d, $i) => date('Y-m-d H:i:s', strtotime($d . ' -' . $i . ' month')), 2);

    // SQLite no tiene ENUM — se usa TEXT con CHECK para aproximar.
    $pdo->exec(<<<'SQL'

        CREATE TABLE agentes (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre            TEXT    NOT NULL,
            apellido          TEXT    NOT NULL DEFAULT '',
            -- Sin UNIQUE global: en multiempresa el mismo correo puede ser
            -- usuario de dos empresas distintas. La unicidad pasa a ser
            -- (empresa_id, correo), y ese índice lo crea la migración 005.
            correo            TEXT    NOT NULL,
            telefono          TEXT    NOT NULL DEFAULT '',
            contrasena        TEXT    NOT NULL,
            rol               TEXT    NOT NULL DEFAULT 'asesor'
                                  CHECK(rol IN ('asesor','agente','coordinador','administrador','superadmin','propietario')),
            puesto            TEXT    DEFAULT NULL,
            activo            INTEGER NOT NULL DEFAULT 1,
            telegram_chat_id  TEXT    DEFAULT NULL
        );

        CREATE TABLE clientes (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre            TEXT    NOT NULL,
            apellido_paterno  TEXT    NOT NULL DEFAULT '',
            apellido_materno  TEXT    NOT NULL DEFAULT '',
            fecha_nacimiento  TEXT,
            correo            TEXT,
            telefono          TEXT,
            sexo              TEXT,
            estado_civil      TEXT,
            calle             TEXT,
            colonia           TEXT,
            ciudad            TEXT,
            estado            TEXT,
            codigo_postal     TEXT,
            marca             TEXT,
            modelo            TEXT,
            anio_vehiculo     INTEGER,
            tipo_vehiculo     TEXT,
            recien_comprado   TEXT,
            asegurado         TEXT,
            tiempo_compra     TEXT,
            fecha_cita        TEXT,
            hora_cita         TEXT,
            estado_cliente    TEXT    DEFAULT 'nuevo',
            fecha_registro    TEXT    DEFAULT (datetime('now')),
            agente_id         INTEGER,
            ramo              TEXT    NOT NULL DEFAULT 'autos',
            giro_empresa      TEXT,
            num_empleados     INTEGER,
            facturacion_anual REAL,
            telegram_chat_id  TEXT    DEFAULT NULL,
            whatsapp_chat_id  TEXT    DEFAULT NULL,
            origen            TEXT    DEFAULT NULL
        );

        CREATE TABLE citas (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id  INTEGER NOT NULL,
            agente_id   INTEGER,
            tipo        TEXT    NOT NULL DEFAULT 'llamada',
            fecha       TEXT    NOT NULL,
            hora        TEXT    NOT NULL,
            comentarios TEXT,
            estado      TEXT    NOT NULL DEFAULT 'programada',
            ramo        TEXT    NOT NULL DEFAULT 'autos'
        );

        CREATE TABLE cotizaciones (
            id                      INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id              INTEGER NOT NULL,
            agente_id               INTEGER NOT NULL,
            folio                   TEXT,
            ramo                    TEXT,
            estado                  TEXT    DEFAULT 'pendiente',
            precio                  REAL    DEFAULT 0,
            tipo_cobertura          TEXT,
            compania                TEXT,
            vigencia                TEXT,
            deducible               TEXT,
            metodo_pago             TEXT,
            fecha                   TEXT    DEFAULT (datetime('now')),
            emision_estado          TEXT    DEFAULT 'pendiente',
            emision_coordinador_id  INTEGER,
            fecha_creacion          TEXT    DEFAULT (datetime('now')),
            fecha_fin_vigencia      TEXT
        );

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

        CREATE TABLE historial_cliente (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id  INTEGER NOT NULL,
            agente_id   INTEGER NOT NULL,
            tipo        TEXT,
            titulo      TEXT,
            descripcion TEXT,
            fecha       TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE notificaciones (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            agente_id   INTEGER NOT NULL,
            tipo        TEXT    NOT NULL DEFAULT 'sistema',
            titulo      TEXT    NOT NULL DEFAULT '',
            mensaje     TEXT    NOT NULL DEFAULT '',
            referencia  TEXT    DEFAULT NULL,
            leida       INTEGER NOT NULL DEFAULT 0,
            creada_en   TEXT    DEFAULT (datetime('now'))
        );

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

        CREATE TABLE cotizacion_archivos (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            cotizacion_id    INTEGER NOT NULL,
            tipo_documento   TEXT,
            ruta             TEXT,
            nombre_archivo   TEXT,
            eliminado_en     TEXT    DEFAULT NULL
        );

        CREATE TABLE cotizacion_pagos (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            cotizacion_id    INTEGER NOT NULL,
            ruta             TEXT,
            nombre_archivo   TEXT,
            created_at       TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE configuracion (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            clave       TEXT NOT NULL,
            valor       TEXT,
            descripcion TEXT
        );

    SQL);

    // ── Migraciones reales sobre el esquema base ─────────────────────────────
    //
    // Las pruebas corren las mismas migraciones que producción en lugar de
    // una copia a mano del esquema multiempresa. Si se escribieran aparte,
    // acabarían divergiendo, y las pruebas pasarían sobre un esquema que no
    // es el que se despliega —que es la clase de fallo que no avisa.
    foreach (['005_multiempresa.php', '006_canales_empresa.php', '007_catalogos.php'] as $archivo) {
        $migracion = require dirname(__DIR__) . '/Database/migrations/' . $archivo;
        ($migracion['up'])($pdo, new \Database\SchemaHelper($pdo));
    }

    // ── Empresa por defecto en los fixtures ──────────────────────────────────
    //
    // Muchas pruebas insertan filas con SQL directo y sin `empresa_id`, porque
    // se escribieron cuando no existía. Esas filas quedarían invisibles —que
    // es el comportamiento correcto— y harían fallar pruebas que no van de
    // multiempresa.
    //
    // En vez de tocar veinte ficheros de fixtures, en el esquema de PRUEBAS lo
    // que se inserta sin empresa cae en la empresa 1. Esto no existe en
    // producción, donde BaseModel sella la empresa activa al insertar.
    //
    // Las pruebas de aislamiento ponen `empresa_id` a mano, así que el
    // disparador no las toca y siguen demostrando lo que deben demostrar.
    $conEmpresa = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($conEmpresa as $tabla) {
        $cols = $pdo->query('PRAGMA table_info(' . $tabla . ')')->fetchAll(PDO::FETCH_COLUMN, 1);

        if (!in_array('empresa_id', $cols, true)) {
            continue;
        }

        $pdo->exec("
            CREATE TRIGGER tg_{$tabla}_empresa_default
            AFTER INSERT ON {$tabla}
            FOR EACH ROW WHEN NEW.empresa_id IS NULL
            BEGIN
                UPDATE {$tabla} SET empresa_id = 1 WHERE rowid = NEW.rowid;
            END;
        ");
    }

    return $pdo;
}

// ── 5. Inyectar en Connection ─────────────────────────────────────────────────
// Usamos FQCN directamente — sin `use`, evita conflictos de importación.
\Database\Connection::setTestConnection(createTestPdo());

// ── 6. Empresa activa por defecto ─────────────────────────────────────────────
// El aislamiento es fail-closed: sin empresa en contexto, toda consulta de
// negocio lanza. Las pruebas que no van de multiempresa trabajan dentro de la
// empresa 1 (la que crea la migración 005). Las de aislamiento montan su
// propia empresa y cambian de contexto con Tenant::comoEmpresa().
\App\Security\Tenant::set(1);