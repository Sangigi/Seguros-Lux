<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{ClientModel, CitaModel, HistorialModel};

SessionManager::requireAuth('/login');

// CSRF en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionManager::validateCsrf($_POST['csrf_token'] ?? '')) {
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        http_response_code(403);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Recarga la pagina']); exit; }
        header('Location: /clientes?error=csrf'); exit;
    }
}

$agenteId      = SessionManager::getAgentId();
$ramo          = SessionManager::getRamo(); // null = todos los ramos
$clienteModel  = new ClientModel();
$citaModel     = new CitaModel();
$historialModel= new HistorialModel();

// ── Info de ramos ─────────────────────────────────────────────────────────────
$ramos_info = [
    'autos'          => ['emoji'=>'🚗','label'=>'Vehicular'],
    'vida'           => ['emoji'=>'❤️','label'=>'Vida'],
    'gastos_medicos' => ['emoji'=>'🏥','label'=>'GMM'],
    'empresas'       => ['emoji'=>'🏢','label'=>'Empresas'],
];

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'];

    if ($accion === 'nota') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $texto     = trim($_POST['texto'] ?? '');
        if (!$clienteId || !$texto) { echo json_encode(['ok'=>false,'msg'=>'Datos incompletos.']); exit; }
        $clienteModel->addNota($clienteId, $agenteId, $texto);
        $historialModel->registrar($clienteId, $agenteId, 'nota', 'Nota agregada', $texto);
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'cita') {
        $clienteId   = (int)($_POST['cliente_id'] ?? 0);
        $tipo        = trim($_POST['tipo']         ?? 'llamada');
        $fecha       = trim($_POST['fecha']        ?? '');
        $hora        = trim($_POST['hora']         ?? '');
        $comentarios = trim($_POST['comentarios']  ?? '');
        $ramoActual  = trim($_POST['ramo']         ?? $ramo ?? 'autos');
        if (!$clienteId || !$fecha || !$hora) { echo json_encode(['ok'=>false,'msg'=>'Faltan campos.']); exit; }
        // Límite: máx 20 citas por cliente (anti-spam)
        $db_citas = \Database\Connection::getConnection();
        $stCount = $db_citas->prepare('SELECT COUNT(*) FROM citas WHERE cliente_id = ?');
        $stCount->execute([$clienteId]);
        if ((int)$stCount->fetchColumn() >= 20) {
            echo json_encode(['ok'=>false,'msg'=>'Este cliente ya tiene 20 citas registradas (límite máximo).']); exit;
        }
        $citaModel->crear($clienteId, $agenteId, $tipo, $fecha, $hora, $comentarios, $ramoActual);
        $historialModel->registrar($clienteId, $agenteId, 'cita', 'Cita programada — '.ucfirst($tipo), "$fecha a las $hora");
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $ok = $clienteModel->delete($id);
        echo json_encode(['ok' => $ok]); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
}

// ── Guardar nuevo cliente (POST form) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['accion'])) {
    $ramoNuevo = in_array($_POST['ramo'] ?? '', ['autos','vida','gastos_medicos','empresas'], true)
        ? $_POST['ramo']
        : ($ramo ?? 'autos');

    $input = [
        'nombre'           => trim($_POST['nombre']            ?? ''),
        'apellido_paterno' => trim($_POST['apellido_paterno']  ?? ''),
        'apellido_materno' => trim($_POST['apellido_materno']  ?? ''),
        'correo'           => trim($_POST['correo']            ?? ''),
        'telefono'         => trim($_POST['telefono']          ?? ''),
        'ciudad'           => trim($_POST['ciudad']            ?? ''),
        'ramo'             => $ramoNuevo,
        'marca'            => trim($_POST['marca']             ?? ''),
        'modelo'           => trim($_POST['modelo']            ?? ''),
        'anio_vehiculo'    => (int)($_POST['anio_vehiculo']    ?? date('Y')),
        'tipo_vehiculo'    => trim($_POST['tipo_vehiculo']     ?? 'auto'),
        'submarca'         => trim($_POST['submarca']          ?? ''),
        'version'          => trim($_POST['version']           ?? ''),
        'tiene_adaptaciones'    => !empty($_POST['detalle_adaptaciones']) ? 1 : (int)($_POST['tiene_adaptaciones'] ?? 0),
        'detalle_adaptaciones'  => trim($_POST['detalle_adaptaciones']  ?? ''),
        'asegurado'        => trim($_POST['asegurado']         ?? 'no'),
        'tiempo_compra'    => trim($_POST['tiempo_compra']     ?? 'solo_cotizando'),
        'fecha_nacimiento' => $_POST['fecha_nacimiento']       ?? '2000-01-01',
        'sexo'             => $_POST['sexo']                   ?? 'masculino',
        'estado_civil'     => $_POST['estado_civil']           ?? 'soltero',
        'giro_empresa'     => trim($_POST['giro_empresa']      ?? ''),
        'num_empleados'    => (int)($_POST['num_empleados']    ?? 0) ?: null,
        'facturacion_anual'=> (float)($_POST['facturacion_anual'] ?? 0) ?: null,
        'calle'            => $_POST['calle']                  ?? '',
        'colonia'          => $_POST['colonia']                ?? '',
        'estado'           => $_POST['estado_rep']             ?? '',
        'codigo_postal'    => $_POST['codigo_postal']          ?? '',
        'fecha_cita'       => trim($_POST['fecha_cita']        ?? date('Y-m-d')),
        'hora_cita'        => trim($_POST['hora_cita']         ?? '10:00:00'),
        'agente_id'        => $agenteId,
        'estado_cliente'   => 'nuevo',
    ];

    if ($input['nombre'] && $input['apellido_paterno'] && $input['correo']) {
        $db = \Database\Connection::getConnection();
        $db->prepare(
            "INSERT INTO clientes
             (nombre, apellido_paterno, apellido_materno, correo, telefono, ciudad, ramo,
              marca, modelo, anio_vehiculo, tipo_vehiculo, submarca, version,
              tiene_adaptaciones, detalle_adaptaciones,
              asegurado, tiempo_compra,
              fecha_nacimiento, sexo, estado_civil,
              giro_empresa, num_empleados, facturacion_anual,
              calle, colonia, estado, codigo_postal,
              fecha_cita, hora_cita, agente_id, estado_cliente)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'nuevo')"
        )->execute([
            $input['nombre'],         $input['apellido_paterno'], $input['apellido_materno'],
            $input['correo'],         $input['telefono'],         $input['ciudad'],         $input['ramo'],
            $input['marca'],          $input['modelo'],           $input['anio_vehiculo'],
            $input['tipo_vehiculo'],  $input['submarca'],         $input['version'],
            $input['tiene_adaptaciones'], $input['detalle_adaptaciones'],
            $input['asegurado'],      $input['tiempo_compra'],
            $input['fecha_nacimiento'],$input['sexo'],            $input['estado_civil'],
            $input['giro_empresa'],   $input['num_empleados'],    $input['facturacion_anual'],
            $input['calle'],          $input['colonia'],          $input['estado'],
            $input['codigo_postal'],  $input['fecha_cita'],       $input['hora_cita'],
            $agenteId,
        ]);
        $nuevoId = (int) $db->lastInsertId();
        if ($nuevoId > 0) {
            $historialModel->registrar($nuevoId, $agenteId, 'registro', 'Cliente registrado',
                "{$input['nombre']} {$input['apellido_paterno']} — Ramo: ".($ramos_info[$ramoNuevo]['label'] ?? $ramoNuevo));
        }
        header('Location: /clientes?ok=1');
        exit;
    }
}

// ── Lectura ───────────────────────────────────────────────────────────────────
$db = \Database\Connection::getConnection();

$ramoWhere = '';
$ramoParam = [];
if ($ramo) {
    $ramoWhere = 'AND c.ramo = ?';
    $ramoParam = [$ramo];
}

$stmt = $db->prepare(
    "SELECT c.id, c.nombre, c.apellido_paterno, c.apellido_materno,
            c.correo, c.telefono, c.ciudad,
            c.ramo,
            c.marca, c.modelo, c.anio_vehiculo, c.tipo_vehiculo,
            c.giro_empresa, c.num_empleados,
            c.asegurado, c.estado_cliente, c.fecha_registro
     FROM clientes c
     WHERE c.agente_id = ? {$ramoWhere}
     ORDER BY c.nombre ASC, c.apellido_paterno ASC"
);
$stmt->execute(array_merge([$agenteId], $ramoParam));
$clientes = $stmt->fetchAll();

foreach ($clientes as &$c) {
    $c['asegurado'] = ($c['asegurado'] === 'si');
    $c['ramo_label'] = $ramos_info[$c['ramo'] ?? 'autos']['emoji'] ?? '📋';
}
unset($c);

$total_clientes = count($clientes);
$nuevos         = count(array_filter($clientes, fn($c) => $c['estado_cliente'] === 'nuevo'));
$contactados    = count(array_filter($clientes, fn($c) => $c['estado_cliente'] === 'contactado'));
$con_cotizacion = count(array_filter($clientes, fn($c) => $c['estado_cliente'] === 'con_cotizacion'));
$sin_contactar  = count(array_filter($clientes, fn($c) => $c['estado_cliente'] === 'sin_contactar'));
$ciudades       = array_values(array_unique(array_filter(array_column($clientes, 'ciudad'))));
sort($ciudades);

$estados_cliente = [
    'nuevo'          => ['label'=>'Nuevo',          'class'=>'badge-info'],
    'contactado'     => ['label'=>'Contactado',      'class'=>'badge-warning'],
    'con_cotizacion' => ['label'=>'Con Cotización',  'class'=>'badge-success'],
    'sin_contactar'  => ['label'=>'Sin Contactar',   'class'=>'badge-danger'],
    'cerrado'        => ['label'=>'Cerrado',          'class'=>'badge-success'],
    'perdido'        => ['label'=>'Perdido',          'class'=>'badge-neutral'],
];

$ramoCountStmt = $db->prepare(
    "SELECT ramo, COUNT(*) AS total FROM clientes WHERE agente_id = ? GROUP BY ramo"
);
$ramoCountStmt->execute([$agenteId]);
$ramoConteos = array_column($ramoCountStmt->fetchAll(\PDO::FETCH_ASSOC), 'total', 'ramo');

$pageTitle = 'Clientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(SessionManager::getCsrfToken(), ENT_QUOTES) ?>">
    <style nonce="<?= $cspNonce ?>">
        .ramo-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;}
        .ramo-tab{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:8px;
                  border:1.5px solid var(--border-color);background:var(--bg-primary);cursor:pointer;
                  font-size:12px;font-weight:600;color:var(--text-secondary);text-decoration:none;
                  transition:all .18s;}
        .ramo-tab:hover{border-color:var(--accent-color);color:var(--accent-color);}
        .ramo-tab.active{border-color:var(--accent-color);background:var(--info-light);color:var(--accent-color);}
        .ramo-tab .tab-count{font-size:10px;background:var(--bg-secondary);color:var(--text-muted);padding:1px 7px;border-radius:99px;}
        .ramo-tab.active .tab-count{background:var(--accent-color);color:#fff;}
    </style>
</head>
<body>
<div class="crm-wrapper">
    <?php require __DIR__ . '/../partials/_navbar.php'; ?>
    <main class="main-content">

        <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            <span class="alert-icon">✅</span>
            <div class="alert-content"><div class="alert-title">Cliente registrado correctamente.</div></div>
        </div>
        <?php endif; ?>

        <header class="page-header">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </span>
                        Gestión de Clientes
                        <?php if ($ramo): ?>
                        <span style="font-size:14px;font-weight:400;color:var(--text-muted);margin-left:8px;">
                            — <?php echo ($ramos_info[$ramo]['emoji']??'').' '.($ramos_info[$ramo]['label']??$ramo); ?>
                        </span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">Administra y da seguimiento a todos tus clientes</p>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" data-open-modal="modalNuevoCliente">+ Nuevo Cliente</button>
                </div>
            </div>
        </header>

        <!-- ── Pestañas de ramo ── -->
        <div class="ramo-tabs">
            <a href="/set-ramo?ramo=todos&redirect=/clientes" class="ramo-tab <?php echo !$ramo ? 'active' : ''; ?>">
                👥 Todos
                <span class="tab-count"><?php echo array_sum($ramoConteos); ?></span>
            </a>
            <?php foreach ($ramos_info as $rk => $rv): ?>
            <a href="/set-ramo?ramo=<?php echo $rk; ?>&redirect=/clientes" class="ramo-tab <?php echo $ramo === $rk ? 'active' : ''; ?>">
                <?php echo $rv['emoji'].' '.$rv['label']; ?>
                <span class="tab-count"><?php echo $ramoConteos[$rk] ?? 0; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Stats -->
        <section class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
            <?php foreach ([
                [$total_clientes,'Total',''],
                [$nuevos,        'Nuevos',        'info'],
                [$contactados,   'Contactados',   'warning'],
                [$con_cotizacion,'Con Cotización','success'],
                [$sin_contactar, 'Sin Contactar', 'danger'],
            ] as [$val,$lbl,$cls]): ?>
            <div class="stat-card <?php echo $cls; ?>">
                <div class="stat-card-value"><?php echo $val; ?></div>
                <div class="stat-card-label"><?php echo $lbl; ?></div>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Filtros -->
        <section class="card mb-24">
            <div class="card-body">
                <div class="filters-bar">
                    <div class="search-bar">
                        <span class="search-bar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                        <input type="text" id="searchInput" class="search-bar-input" placeholder="Buscar por nombre, correo, empresa…">
                    </div>
                    <select class="filter-select" id="filterEstado">
                        <option value="todos">Todos los estados</option>
                        <?php foreach ($estados_cliente as $k => $v): ?>
                        <option value="<?php echo $k; ?>"><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="filter-select" id="filterCiudad">
                        <option value="todos">Todas las ciudades</option>
                        <?php foreach ($ciudades as $ciudad): ?>
                        <option value="<?php echo htmlspecialchars(strtolower($ciudad)); ?>"><?php echo htmlspecialchars($ciudad); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" class="filter-select" id="filterFecha" title="Filtrar por fecha de registro" style="cursor:pointer;">
                    <button class="btn btn-outline" id="btnLimpiarFecha" style="display:none;white-space:nowrap;">✕ Fecha</button>
                </div>
            </div>
        </section>

        <!-- Tabla -->
        <section class="card responsive" style="margin:auto;">
            <div class="card-header">
                <h3 class="card-title">Lista de Clientes
                    <span class="badge badge-neutral" id="clientesCount"><?php echo $total_clientes; ?> clientes</span>
                </h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($clientes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <div class="empty-state-title">Sin clientes<?php echo $ramo ? ' en este ramo' : ''; ?></div>
                    <button class="btn btn-primary" data-open-modal="modalNuevoCliente">+ Nuevo Cliente</button>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="data-table" id="clientesTable" data-pag="15">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;">#</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="clientesTableBody">
                            <?php foreach ($clientes as $i => $cliente):
                                $nombreCompleto = $cliente['nombre'].' '.$cliente['apellido_paterno'].' '.$cliente['apellido_materno'];
                                $estadoInfo     = $estados_cliente[$cliente['estado_cliente']] ?? ['label'=>'—','class'=>'badge-neutral'];
                                $iniciales      = strtoupper(substr($cliente['nombre'],0,1).substr($cliente['apellido_paterno'],0,1));
                                $fechaISO       = substr($cliente['fecha_registro'], 0, 10);
                                $ramoCliente    = $cliente['ramo'] ?? 'autos';
                                $ramoEmoji      = $ramos_info[$ramoCliente]['emoji'] ?? '📋';
                                $ramoLbl        = $ramos_info[$ramoCliente]['label'] ?? $ramoCliente;

                                if ($ramoCliente === 'empresas') {
                                    $detalle = trim(($cliente['giro_empresa'] ?? '').($cliente['num_empleados'] ? ' · '.$cliente['num_empleados'].' emp.' : ''));
                                } else {
                                    $detalle = trim(ucfirst($cliente['marca'] ?? '').' '.($cliente['modelo'] ?? '').(isset($cliente['anio_vehiculo']) && $cliente['anio_vehiculo'] ? ' · '.$cliente['anio_vehiculo'] : ''));
                                }
                            ?>
                            <tr data-nombre="<?php echo htmlspecialchars(strtolower($nombreCompleto.' '.$cliente['correo'].' '.($cliente['marca']??'').' '.($cliente['modelo']??'').' '.($cliente['giro_empresa']??''))); ?>"
                                data-estado="<?php echo $cliente['estado_cliente']; ?>"
                                data-ciudad="<?php echo htmlspecialchars(strtolower($cliente['ciudad'] ?? '')); ?>"
                                data-fecha="<?php echo $fechaISO; ?>"
                                data-id="<?php echo $cliente['id']; ?>">
                                <td style="text-align:center;font-size:12px;color:var(--text-muted);font-weight:600;"><?php echo $i+1; ?></td>
                                <td>
                                    <div class="table-user">
                                        <div class="table-user-avatar"><?php echo $iniciales; ?></div>
                                        <div class="table-user-info">
                                            <div class="table-user-name"><?php echo htmlspecialchars($nombreCompleto); ?></div>
                                            <div class="table-user-email"><?php echo htmlspecialchars($cliente['correo']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge <?php echo $estadoInfo['class']; ?>"><?php echo $estadoInfo['label']; ?></span></td>
                                <td style="font-size:12px;color:var(--text-secondary);"><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/cliente/<?php echo $cliente['id']; ?>" class="btn btn-sm btn-outline" title="Ver detalle">👁</a>
                                        <button class="btn btn-sm btn-outline" data-action="nota" data-id="<?php echo $cliente['id']; ?>" title="Nota">📝</button>
                                        <a href="/crear-cotizacion?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-primary" title="Cotización">📋</a>
                                        <button class="btn btn-sm btn-success" data-action="cita" data-id="<?php echo $cliente['id']; ?>" title="Cita">📅</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<?php
$csrfToken = SessionManager::getCsrfToken();
require __DIR__ . '/../partials/_modales_clientes.php';
?>

<div class="toast-container" id="toastContainer"></div>
<script src="/js/clientes.js"   nonce="<?= $cspNonce ?>"></script>
<script nonce="<?= $cspNonce ?>">
    window.ramoActivo = <?php echo json_encode($ramo ?? 'autos'); ?>;
</script>
<script src="/js/crm-events.js" nonce="<?= $cspNonce ?>"></script>
<script src="/js/modales.js"    nonce="<?= $cspNonce ?>"></script>
</body>
</html>