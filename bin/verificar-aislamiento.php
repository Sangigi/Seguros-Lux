#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/verificar-aislamiento.php — prueba que una empresa no puede ver datos
 * de otra.
 *
 * Esta es la comprobación que importa en un CRM multiempresa: no "¿funciona
 * la pantalla?", sino "¿puede la empresa A leer, modificar o borrar algo de
 * la empresa B?". Corre contra la base configurada en el entorno, crea dos
 * empresas con datos propios y ataca el aislamiento desde cada ángulo.
 *
 * Uso:  DB_NAME=lux_test php bin/verificar-aislamiento.php
 *
 * No deja basura: borra las empresas de prueba al terminar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI.\n");
}

$raiz = dirname(__DIR__);
require_once $raiz . '/Core/Autoloader.php';
require_once $raiz . '/Core/helpers.php';
if (is_file($raiz . '/.env.php')) {
    require_once $raiz . '/.env.php';
}

use App\Models\ClientModel;
use App\Security\Tenant;
use App\Services\QueryCache;
use Database\Connection;

$db = Connection::getConnection();

// ── Utilidades de reporte ────────────────────────────────────────────────────
$pasadas = 0;
$fallos  = [];

$check = function (string $nombre, bool $ok, string $detalle = '') use (&$pasadas, &$fallos): void {
    if ($ok) {
        $pasadas++;
        echo "  \033[32m✓\033[0m {$nombre}\n";
    } else {
        $fallos[] = $nombre . ($detalle !== '' ? " — {$detalle}" : '');
        echo "  \033[31m✗ {$nombre}\033[0m" . ($detalle !== '' ? " — {$detalle}" : '') . "\n";
    }
};

// ── Montaje: dos empresas con un cliente cada una ────────────────────────────
echo "\n\033[1mPreparando dos empresas de prueba\033[0m\n";

$db->exec("DELETE FROM empresas WHERE slug IN ('zz-test-a','zz-test-b')");

$ins = $db->prepare(
    'INSERT INTO empresas (nombre, slug, plan, max_usuarios, activo, creado_en)
     VALUES (?, ?, ?, ?, 1, NOW())'
);
$ins->execute(['ZZ Test A', 'zz-test-a', 'basico', 5]);
$empresaA = (int) $db->lastInsertId();
$ins->execute(['ZZ Test B', 'zz-test-b', 'basico', 5]);
$empresaB = (int) $db->lastInsertId();

echo "  Empresa A = {$empresaA}, Empresa B = {$empresaB}\n";

$insCliente = $db->prepare(
    'INSERT INTO clientes (nombre, apellido_paterno, correo, ramo, estado_cliente, empresa_id)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$insCliente->execute(['SecretoDeA', 'Uno', 'a1@zz.mx', 'autos', 'nuevo', $empresaA]);
$clienteA = (int) $db->lastInsertId();
$insCliente->execute(['SecretoDeB', 'Uno', 'b1@zz.mx', 'autos', 'nuevo', $empresaB]);
$clienteB = (int) $db->lastInsertId();

echo "  Cliente de A = {$clienteA}, Cliente de B = {$clienteB}\n";

$limpiar = function () use ($db, $empresaA, $empresaB): void {
    foreach (['clientes'] as $t) {
        $db->prepare("DELETE FROM {$t} WHERE empresa_id IN (?,?)")->execute([$empresaA, $empresaB]);
    }
    $db->prepare('DELETE FROM empresas WHERE id IN (?,?)')->execute([$empresaA, $empresaB]);
};

try {

    // ── 1. find() no cruza empresas ──────────────────────────────────────────
    echo "\n\033[1m1. Lectura directa por id (el vector de fuga más común)\033[0m\n";

    Tenant::reset();
    Tenant::set($empresaA);
    QueryCache::flush();

    $modelo = new ClientModel();

    $propio = $modelo->find($clienteA, ['id', 'nombre']);
    $check('A encuentra su propio cliente', $propio !== null && (int) $propio['id'] === $clienteA);

    $ajeno = $modelo->find($clienteB, ['id', 'nombre']);
    $check(
        'A NO encuentra el cliente de B adivinando su id',
        $ajeno === null,
        $ajeno !== null ? 'devolvió ' . json_encode($ajeno) : ''
    );

    // ── 2. Listados ──────────────────────────────────────────────────────────
    echo "\n\033[1m2. Listados y paginación\033[0m\n";

    QueryCache::flush();
    $todos  = $modelo->all(['id', 'nombre']);
    $idsA   = array_column($todos, 'id');
    $check(
        'all() de A no incluye registros de B',
        !in_array((string) $clienteB, array_map('strval', $idsA), true)
    );

    QueryCache::flush();
    $pag = $modelo->paginate(1, 200, 'id', 'DESC', ['id', 'nombre']);
    $idsPag = array_map('strval', array_column($pag['data'], 'id'));
    $check(
        'paginate() de A no incluye registros de B',
        !in_array((string) $clienteB, $idsPag, true)
    );

    $totalReal = (int) $db->query("SELECT COUNT(*) FROM clientes WHERE empresa_id = {$empresaA}")->fetchColumn();
    $check(
        'el total de paginate() cuenta solo la empresa activa',
        (int) $pag['total'] === $totalReal,
        "paginate dice {$pag['total']}, la base dice {$totalReal}"
    );

    // ── 3. Escritura ─────────────────────────────────────────────────────────
    echo "\n\033[1m3. Escritura\033[0m\n";

    QueryCache::flush();
    $nuevoId = $modelo->insert([
        'nombre'           => 'NuevoDeA',
        'apellido_paterno' => 'Test',
        'correo'           => 'nuevo-a@zz.mx',
        'ramo'             => 'autos',
    ]);
    $empresaDelNuevo = (int) $db->query("SELECT empresa_id FROM clientes WHERE id = {$nuevoId}")->fetchColumn();
    $check(
        'insert() sella la empresa activa sin que el modelo la declare',
        $empresaDelNuevo === $empresaA,
        "quedó en empresa {$empresaDelNuevo}"
    );

    // Intento de falsificar la empresa desde los datos de entrada
    QueryCache::flush();
    $colado = $modelo->insert([
        'nombre'           => 'Colado',
        'apellido_paterno' => 'Test',
        'correo'           => 'colado@zz.mx',
        'ramo'             => 'autos',
        'empresa_id'       => $empresaB,   // ← intento de escribir en B desde A
    ]);
    $empresaDelColado = (int) $db->query("SELECT empresa_id FROM clientes WHERE id = {$colado}")->fetchColumn();
    $check(
        'insert() ignora un empresa_id ajeno enviado en los datos',
        $empresaDelColado !== $empresaB,
        "se escribió en la empresa {$empresaDelColado} (B={$empresaB})"
    );

    // ── 4. update() y delete() no cruzan ─────────────────────────────────────
    echo "\n\033[1m4. Modificación y borrado de datos ajenos\033[0m\n";

    QueryCache::flush();
    $modelo->update($clienteB, ['nombre' => 'HACKEADO']);
    $nombreB = (string) $db->query("SELECT nombre FROM clientes WHERE id = {$clienteB}")->fetchColumn();
    $check(
        'A NO puede modificar un cliente de B',
        $nombreB === 'SecretoDeB',
        "el nombre quedó como '{$nombreB}'"
    );

    QueryCache::flush();
    $modelo->delete($clienteB);
    $sigueVivo = (int) $db->query("SELECT COUNT(*) FROM clientes WHERE id = {$clienteB}")->fetchColumn();
    $check('A NO puede borrar un cliente de B', $sigueVivo === 1);

    // ── 5. La caché no filtra entre empresas ─────────────────────────────────
    echo "\n\033[1m5. Caché compartida entre empresas\033[0m\n";

    // A consulta y llena la caché
    Tenant::reset();
    Tenant::set($empresaA);
    QueryCache::flush();
    $vistoPorA = $modelo->all(['id', 'nombre']);

    // B hace exactamente la misma consulta, sin limpiar la caché L1
    Tenant::reset();
    Tenant::set($empresaB);
    $modeloB   = new ClientModel();
    $vistoPorB = $modeloB->all(['id', 'nombre']);

    $nombresB = array_column($vistoPorB, 'nombre');
    $check(
        'B no recibe el resultado cacheado de A',
        !in_array('SecretoDeA', $nombresB, true),
        'B vio: ' . implode(', ', $nombresB)
    );
    $check(
        'B sí ve lo suyo',
        in_array('SecretoDeB', $nombresB, true)
    );

    // ── 6. Sin contexto, no se devuelve nada (fail-closed) ───────────────────
    echo "\n\033[1m6. Comportamiento sin empresa en contexto\033[0m\n";

    Tenant::reset();
    QueryCache::flush();
    $modeloSinCtx = new ClientModel();

    $lanzo = false;
    try {
        $modeloSinCtx->all(['id', 'nombre']);
    } catch (\RuntimeException $e) {
        $lanzo = true;
    }
    $check(
        'sin empresa en contexto la consulta falla en vez de devolver todo',
        $lanzo,
        'devolvió datos sin contexto de empresa'
    );

    // ── 7. Modo plataforma explícito ─────────────────────────────────────────
    echo "\n\033[1m7. Acceso de plataforma (propietario / crons)\033[0m\n";

    QueryCache::flush();
    $global = Tenant::sinRestriccion(fn() => (new ClientModel())->all(['id', 'nombre']));
    $nombresG = array_column($global, 'nombre');
    $check(
        'sinRestriccion() sí ve las dos empresas',
        in_array('SecretoDeA', $nombresG, true) && in_array('SecretoDeB', $nombresG, true)
    );
    $check(
        'el modo global se restaura al salir del bloque',
        !Tenant::esGlobal()
    );

    $restaurado = true;
    try {
        Tenant::sinRestriccion(function () {
            throw new \RuntimeException('fallo dentro del bloque');
        });
    } catch (\RuntimeException $e) {
        $restaurado = !Tenant::esGlobal();
    }
    $check('el modo global se restaura aunque el bloque lance una excepción', $restaurado);

} finally {
    Tenant::reset();
    $limpiar();
}

// ── Resultado ────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 70) . "\n";

if ($fallos === []) {
    echo "\033[32m\033[1m{$pasadas} comprobaciones pasadas. El aislamiento entre empresas se sostiene.\033[0m\n\n";
    exit(0);
}

echo "\033[31m\033[1m" . count($fallos) . " FUGA(S) DETECTADA(S):\033[0m\n";
foreach ($fallos as $f) {
    echo "  · {$f}\n";
}
echo "\n";
exit(1);
