<?php
declare(strict_types=1);

/**
 * 007_catalogos — catálogos configurables por empresa.
 *
 * El problema que resuelve:
 *
 * Las listas que usa el CRM (ramos, aseguradoras, coberturas por ramo y
 * documentos que se piden para emitir) estaban escritas a mano en cada
 * pantalla, y no coincidían entre sí: la lista de aseguradoras de la
 * cotización no era la del detalle del cliente, y el checklist de emisión
 * pedía documentos distintos según desde dónde se mirara. En multiempresa,
 * además, cada empresa trabaja con sus propias aseguradoras, y no tiene
 * sentido que la lista de una la decida el código de todas.
 *
 * Una sola tabla con `tipo` en lugar de una por catálogo:
 *   - los cuatro catálogos tienen la misma forma (clave, etiqueta, activo,
 *     orden), y el mismo ciclo de vida;
 *   - la pantalla de administración y la API los tratan igual;
 *   - añadir un catálogo nuevo no obliga a tocar el esquema.
 *
 * `ramo` es '' (no NULL) en los ramos mismos: en MySQL un índice único deja
 * pasar NULLs repetidos, y entonces el índice no impediría duplicar un ramo.
 *
 * Los valores por defecto viven en CatalogoModel, no aquí: así la migración y
 * el alta de empresas siembran exactamente lo mismo, y lo que se siembra es lo
 * que las pantallas usaban hasta ahora.
 *
 * Idempotente. `down()` solo elimina la tabla: no toca datos de negocio.
 */

use App\Models\CatalogoModel;
use Database\SchemaHelper;

return [

    'up' => function (PDO $db, SchemaHelper $s): void {

        $esSqlite = $s->esSqlite();
        $ts       = $esSqlite ? "(datetime('now'))" : 'CURRENT_TIMESTAMP';
        $ai       = $esSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $suffix   = $esSqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        if (!$s->tablaExiste('catalogos')) {
            $db->exec("
                CREATE TABLE catalogos (
                    id              {$ai},
                    empresa_id      " . $s->tipoEntero() . " NOT NULL,
                    tipo            VARCHAR(20)  NOT NULL,
                    ramo            VARCHAR(30)  NOT NULL DEFAULT '',
                    clave           VARCHAR(60)  NOT NULL,
                    etiqueta        VARCHAR(120) NOT NULL,
                    activo          TINYINT      NOT NULL DEFAULT 1,
                    orden           INT          NOT NULL DEFAULT 0,
                    creado_en       DATETIME     NOT NULL DEFAULT {$ts},
                    actualizado_en  DATETIME     DEFAULT NULL
                ){$suffix}
            ");
        }

        // Único por empresa, tipo, ramo y clave: la clave es lo que el código
        // usa para reconocer un elemento, y dos con la misma clave harían que
        // activar o renombrar uno dependiera de cuál saliera primero.
        $s->crearIndice('catalogos', 'uq_catalogo_clave', ['empresa_id', 'tipo', 'ramo', 'clave'], true);

        // La consulta habitual es "lo activo de este tipo y ramo en mi empresa".
        $s->crearIndice('catalogos', 'idx_catalogo_lista', ['empresa_id', 'tipo', 'ramo', 'activo']);

        $s->agregarFk('catalogos', 'fk_catalogos_empresa', 'empresa_id', 'empresas', 'id', 'CASCADE');

        // Cada empresa existente arranca con lo que las pantallas usaban hasta
        // hoy, para que al desplegar nada cambie a la vista. La siembra solo
        // añade lo que falta, así que repetir la migración no duplica nada.
        $empresas = $db->query('SELECT id FROM empresas WHERE eliminado_en IS NULL ORDER BY id')
                       ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($empresas as $empresaId) {
            CatalogoModel::sembrarCon($db, (int) $empresaId);
        }
    },

    'down' => function (PDO $db, SchemaHelper $s): void {
        $s->quitarFk('catalogos', 'fk_catalogos_empresa');
        $db->exec('DROP TABLE IF EXISTS catalogos');
    },
];
