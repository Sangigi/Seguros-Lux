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

// ── Determinar modo vista ─────────────────────────────────────────────────────
$rol             = SessionManager::getRole();
$esCoordinador   = in_array($rol, ['coordinador', 'administrador', 'superadmin'], true);

// ── Lectura ───────────────────────────────────────────────────────────────────
$db = \Database\Connection::getConnection();

$ramoWhere = '';
$ramoParam = [];
if ($ramo) {
    $ramoWhere = 'AND c.ramo = ?';
    $ramoParam = [$ramo];
}

// Filtro de asesor (solo coordinadores pueden cambiar esto)
$filtroAsesorId = null;
if ($esCoordinador && isset($_GET['asesor_id']) && $_GET['asesor_id'] !== 'todos') {
    $filtroAsesorId = (int)$_GET['asesor_id'];
}

if ($esCoordinador) {
    // Coordinador: ve TODOS los clientes, con info del asesor asignado
    $agenteWhere = '';
    $agenteParam = [];
    if ($filtroAsesorId) {
        $agenteWhere = 'AND c.agente_id = ?';
        $agenteParam = [$filtroAsesorId];
    }

    $stmt = $db->prepare(
        "SELECT c.id, c.nombre, c.apellido_paterno, c.apellido_materno,
                c.correo, c.telefono, c.ciudad,
                c.ramo, c.agente_id,
                c.marca, c.modelo, c.anio_vehiculo, c.tipo_vehiculo,
                c.giro_empresa, c.num_empleados,
                c.asegurado, c.estado_cliente, c.fecha_registro,
                CONCAT(a.nombre,' ',COALESCE(a.apellido,'')) AS asesor_nombre
         FROM clientes c
         LEFT JOIN agentes a ON a.id = c.agente_id
         WHERE 1=1 {$ramoWhere} {$agenteWhere}
         ORDER BY c.fecha_registro DESC"
    );
    $stmt->execute(array_merge($ramoParam, $agenteParam));

    // Lista de asesores para filtro dropdown
    $stAsesores = $db->query(
        "SELECT id, CONCAT(nombre,' ',COALESCE(apellido,'')) AS nombre_completo
         FROM agentes WHERE rol IN ('asesor','agente') AND COALESCE(activo,1)=1
         ORDER BY nombre ASC"
    );
    $listaAsesores = $stAsesores->fetchAll(\PDO::FETCH_ASSOC);

} else {
    // Asesor: solo sus clientes (comportamiento original)
    $stmt = $db->prepare(
        "SELECT c.id, c.nombre, c.apellido_paterno, c.apellido_materno,
                c.correo, c.telefono, c.ciudad,
                c.ramo,
                c.marca, c.modelo, c.anio_vehiculo, c.tipo_vehiculo,
                c.giro_empresa, c.num_empleados,
                c.asegurado, c.estado_cliente, c.fecha_registro,
                '' AS asesor_nombre
         FROM clientes c
         WHERE c.agente_id = ? {$ramoWhere}
         ORDER BY c.nombre ASC, c.apellido_paterno ASC"
    );
    $stmt->execute(array_merge([$agenteId], $ramoParam));
    $listaAsesores = [];
}

$clientes = $stmt->fetchAll();

foreach ($clientes as &$c) {
    $c['asegurado']   = ($c['asegurado'] === 'si');
    $c['ramo_label']  = $ramos_info[$c['ramo'] ?? 'autos']['emoji'] ?? '📋';
    $c['asesor_nombre'] = trim($c['asesor_nombre'] ?? '—');
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

// Conteos por ramo (según modo)
if ($esCoordinador) {
    $ramoCountStmt = $db->query(
        "SELECT ramo, COUNT(*) AS total FROM clientes GROUP BY ramo"
    );
} else {
    $ramoCountStmt = $db->prepare(
        "SELECT ramo, COUNT(*) AS total FROM clientes WHERE agente_id = ? GROUP BY ramo"
    );
    $ramoCountStmt->execute([$agenteId]);
}
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
                        <?php echo $esCoordinador ? 'Todos los Clientes' : 'Gestión de Clientes'; ?>
                        <?php if ($ramo): ?>
                        <span style="font-size:14px;font-weight:400;color:var(--text-muted);margin-left:8px;">
                            — <?php echo ($ramos_info[$ramo]['emoji']??'').' '.($ramos_info[$ramo]['label']??$ramo); ?>
                        </span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">
                        <?php echo $esCoordinador
                            ? 'Vista global — todos los asesores y ramos'
                            : 'Administra y da seguimiento a todos tus clientes'; ?>
                    </p>
                </div>
                <div class="page-actions">
                    <?php if ($esCoordinador): ?>
                    <a href="/admin?tab=asesores" class="btn btn-outline">👥 Ver Asesores</a>
                    <?php endif; ?>
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
                   <?php if ($esCoordinador && !empty($listaAsesores)): ?>
<select class="filter-select" id="filterAsesor">
    <option value="todos" <?php echo !$filtroAsesorId ? 'selected' : ''; ?>>Todos los asesores</option>
    <?php foreach ($listaAsesores as $as): ?>
    <option value="<?php echo $as['id']; ?>" <?php echo $filtroAsesorId == $as['id'] ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars(trim($as['nombre_completo'])); ?>
    </option>
    <?php endforeach; ?>
</select>
<?php endif; ?>
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
                    <table class="data-table" id="clientesTable" data-pag="10">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;">#</th>
                                <th>Cliente</th>
                                <?php if ($esCoordinador): ?>
                                <th>Asesor</th>
                                <?php endif; ?>
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
                            <tr data-nombre="<?php echo htmlspecialchars(strtolower($nombreCompleto.' '.$cliente['correo'].' '.($cliente['marca']??'').' '.($cliente['modelo']??'').' '.($cliente['giro_empresa']??'').' '.($cliente['asesor_nombre']??''))); ?>"
                                data-estado="<?php echo $cliente['estado_cliente']; ?>"
                                data-ciudad="<?php echo htmlspecialchars(strtolower($cliente['ciudad'] ?? '')); ?>"
                                data-fecha="<?php echo $fechaISO; ?>"
                                data-asesor="<?php echo $cliente['agente_id']; ?>"
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
                                <?php if ($esCoordinador): ?>
                                <td style="font-size:12px;color:var(--text-secondary);">
                                    <?php echo htmlspecialchars($cliente['asesor_nombre'] ?: '—'); ?>
                                </td>
                                <?php endif; ?>
                                <td><span class="badge <?php echo $estadoInfo['class']; ?>"><?php echo $estadoInfo['label']; ?></span></td>
                                <td style="font-size:12px;color:var(--text-secondary);"><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/cliente/<?php echo $cliente['id']; ?>" class="btn btn-sm btn-outline" title="Ver detalle">👁</a>
                                        <button class="btn btn-sm btn-outline" data-action="nota" data-id="<?php echo $cliente['id']; ?>" title="Nota">📝</button>
                                        <a href="/crear-cotizacion?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-primary" title="Cotización">📋</a>
                                        <button class="btn btn-sm btn-success" data-action="cita" data-id="<?php echo $cliente['id']; ?>" title="Cita">📅</button>
                                        <?php if ($esCoordinador): ?>
                                        <button class="btn btn-sm btn-danger" data-action="eliminar" data-id="<?php echo $cliente['id']; ?>" title="Eliminar">🗑</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" id="paginationClientes" style="display:flex; gap:4px; justify-content:center; padding:16px 0; flex-wrap:wrap;"></div>
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
    <script nonce="<?= $cspNonce ?>">
document.addEventListener('DOMContentLoaded', () => {
    const ITEMS_POR_PAGINA = 15;
    let paginaActual = 1;

    const searchInput  = document.getElementById('searchInput');
    const filterEstado = document.getElementById('filterEstado');
    const filterCiudad = document.getElementById('filterCiudad');
    const filterAsesor = document.getElementById('filterAsesor');
    const tbody        = document.getElementById('clientesTableBody');
    const countLabel   = document.getElementById('clientesCount');
    const pagContainer = document.getElementById('paginationClientes');

    function actualizarVista() {
        if (!tbody) return;
        const txt = (searchInput?.value || '').toLowerCase();
        const est = filterEstado?.value || 'todos';
        const ciu = filterCiudad?.value || 'todos';
        const ase = filterAsesor?.value || 'todos';

        const todasLasFilas = Array.from(tbody.querySelectorAll('tr'));
        const filasCoincidentes = [];

        // 1. Filtrar coincidencias
        todasLasFilas.forEach(tr => {
            const matchTxt = !txt || (tr.dataset.nombre || '').includes(txt);
            const matchEst = est === 'todos' || tr.dataset.estado === est;
            const matchCiu = ciu === 'todos' || tr.dataset.ciudad === ciu;
            const matchAse = ase === 'todos' || String(tr.dataset.asesor) === String(ase);

            if (matchTxt && matchEst && matchCiu && matchAse) {
                filasCoincidentes.push(tr);
            } else {
                tr.style.display = 'none'; 
            }
        });

        if (countLabel) countLabel.textContent = filasCoincidentes.length + ' clientes';

        // 2. Calcular páginas
        const totalPaginas = Math.max(1, Math.ceil(filasCoincidentes.length / ITEMS_POR_PAGINA));
        if (paginaActual > totalPaginas) paginaActual = totalPaginas;

        const inicio = (paginaActual - 1) * ITEMS_POR_PAGINA;
        const fin    = inicio + ITEMS_POR_PAGINA;

        // 3. Mostrar solo el "trozo" de la página actual
        filasCoincidentes.forEach((tr, index) => {
            tr.style.display = (index >= inicio && index < fin) ? '' : 'none';
        });

        renderizarBotones(totalPaginas);
    }

    function renderizarBotones(totalPaginas) {
        if (!pagContainer) return;
        pagContainer.innerHTML = '';

        if (totalPaginas <= 1) return; // Si solo hay 1 página, ocultamos la barra

        const crearBoton = (texto, pagDestino, disabled = false, esActivo = false) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `page-btn ${esActivo ? 'active' : ''}`;
            btn.textContent = texto;
            
            if (disabled) {
                btn.disabled = true;
                btn.style.opacity = '0.35';
                btn.style.cursor = 'not-allowed';
            } else {
                btn.addEventListener('click', () => {
                    paginaActual = pagDestino;
                    actualizarVista();
                    document.getElementById('clientesTable')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
            return btn;
        };

        // Botón "Anterior"
        pagContainer.appendChild(crearBoton('‹', paginaActual - 1, paginaActual === 1));

        // Generación de números inteligente (con puntos suspensivos "...")
        for (let i = 1; i <= totalPaginas; i++) {
            if (i === 1 || i === totalPaginas || Math.abs(i - paginaActual) <= 1) {
                pagContainer.appendChild(crearBoton(i, i, false, i === paginaActual));
            } else if (Math.abs(i - paginaActual) === 2) {
                const dot = document.createElement('span');
                dot.textContent = '…';
                dot.style.cssText = 'align-self:center; color:var(--text-muted); padding:0 4px;';
                pagContainer.appendChild(dot);
            }
        }

        // Botón "Siguiente"
        pagContainer.appendChild(crearBoton('›', paginaActual + 1, paginaActual === totalPaginas));
    }

    // Al escribir o cambiar un filtro, regresamos obligatoriamente a la Página 1
    function alFiltrar() { paginaActual = 1; actualizarVista(); }

    searchInput?.addEventListener('input', alFiltrar);
    filterEstado?.addEventListener('change', alFiltrar);
    filterCiudad?.addEventListener('change', alFiltrar);
    filterAsesor?.addEventListener('change', alFiltrar);

    // Acción Eliminar Cliente (AJAX seguro)
    document.addEventListener('click', function(e) {
        const btnEliminar = e.target.closest('button[data-action="eliminar"]');
        if (!btnEliminar) return;

        e.preventDefault();
        e.stopPropagation();

        if (!confirm('¿Eliminar este cliente definitivamente?')) return;

        const fd = new FormData();
        fd.append('accion', 'eliminar');
        fd.append('id', btnEliminar.dataset.id);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf) fd.append('csrf_token', csrf);

        fetch('', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    btnEliminar.closest('tr').remove();
                    actualizarVista(); // Recalcula la página mágicamente
                } else alert(d.msg || 'Error');
            });
    }, true);

    actualizarVista(); // Arrancar la tabla en página 1 al abrir la web
});
        // Función para paginar y filtrar la lista de clientes
function initPaginacionClientes() {
    const list = document.getElementById('clientesList');
    const pgContainer = document.getElementById('pgClientes');
    const searchInput = document.getElementById('srchCl');

    // Si no estamos en la pestaña de asesores con un cliente seleccionado, salimos
    if (!list || !pgContainer) return;

    // Obtener todos los elementos (filas de clientes)
    const allItems = Array.from(list.getElementsByClassName('cb-row'));
    let filteredItems = [...allItems];
    
    // Configuración: Cuántos clientes quieres ver por página
    const itemsPerPage = 10; 
    let currentPage = 1;

    // Renderiza los clientes que corresponden a la página actual
    function renderPage(page) {
        currentPage = page;
        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        // Ocultar todos los elementos de la lista primero
        allItems.forEach(item => {
            item.style.display = 'none';
        });

        // Mostrar únicamente los elementos filtrados que tocan en esta página
        filteredItems.slice(start, end).forEach(item => {
            item.style.display = 'flex'; // Se usa flex porque tu clase .cb-row lo requiere
        });

        renderButtons();
    }

    // Genera los botones de la numeración
    function renderButtons() {
        const pageCount = Math.ceil(filteredItems.length / itemsPerPage);
        pgContainer.innerHTML = ''; // Limpiar botones anteriores

        // Si hay una página o menos, no mostramos botones
        if (pageCount <= 1) return;

        for (let i = 1; i <= pageCount; i++) {
            const btn = document.createElement('button');
            // Usando las clases CSS que ya tienes en tu <style>
            btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
            btn.textContent = i;
            btn.onclick = (e) => {
                e.preventDefault();
                renderPage(i); // Cambiar a la página seleccionada
            };
            pgContainer.appendChild(btn);
        }
    }

    // Activar la búsqueda en vivo y recalcular páginas
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            
            // Filtrar usando el atributo data-n que ya pusiste en PHP
            filteredItems = allItems.filter(item => {
                const text = item.getAttribute('data-n') || '';
                return text.includes(term);
            });
            
            // Al buscar, regresar siempre a la página 1
            renderPage(1); 
        });
    }

    // Iniciar mostrando la primera página
    renderPage(1);
}

// Ejecutar cuando el DOM termine de cargar
document.addEventListener('DOMContentLoaded', initPaginacionClientes);
</script>
</body>
</html>
