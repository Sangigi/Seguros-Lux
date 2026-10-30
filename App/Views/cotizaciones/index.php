<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{CatalogoModel, CotizacionModel, ClientModel, HistorialModel};
use App\Services\{ArchivosCotizacion, CotizacionCorreo};
use Database\Connection;

SessionManager::requireAuth('/login');

$agenteId        = SessionManager::getAgentId();
$empresaId       = \App\Security\Tenant::requireId();
$cotizacionModel = new CotizacionModel();
$clienteModel    = new ClientModel();
$historialModel  = new HistorialModel();
$catalogo        = new CatalogoModel();

// Filtro de ramo único del CRM, elegido en el menú lateral; null = todos.
$ramosActivos = $catalogo->ramos();
$ramoFiltro   = \App\Security\FiltroRamo::desdePeticion();

// ── Helper: URL pre-firmada de iDrive/S3 ──────────────────────────────────────
/**
 * Enlace firmado para un objeto de iDrive, o null si iDrive no está
 * configurado, el objeto no existe o no es de la cotización indicada.
 *
 * La clave de los archivos de cotización la arma ArchivosCotizacion::claveIdrive(),
 * igual que el cron que los sube (clientes/{slug}/{folio}/{carpeta}/…). Como esa
 * clave no lleva la empresa, con $cotizacionId se comprueba antes la metadata
 * `cotizacion-id` que pone el cron, para no firmar el archivo de otra empresa
 * con un cliente homónimo y el mismo folio.
 */
function generarUrlIdrive(string $s3Key, int $cotizacionId = 0, int $minutos = 30): ?string
{
    static $s3       = null;
    static $bucket   = null;
    static $resuelto = false;

    if (!$resuelto) {
        $resuelto  = true;
        $keyId     = getenv('IDRIVE_KEY_ID');
        $keySecret = getenv('IDRIVE_KEY_SECRET');
        $endpoint  = getenv('IDRIVE_ENDPOINT') ?: 's3.us-west-2.idrivee2.com';
        $region    = getenv('IDRIVE_REGION')   ?: 'us-west-2';
        $bucket    = getenv('IDRIVE_BUCKET')   ?: 'crmseguros';

        // Composer ya lo carga el front controller (public/index.php).
        if ($keyId && $keySecret && class_exists(\Aws\S3\S3Client::class)) {
            try {
                $s3 = new \Aws\S3\S3Client([
                    'version'                 => 'latest',
                    'region'                  => $region,
                    'endpoint'                => "https://{$endpoint}",
                    'use_path_style_endpoint' => true,
                    'credentials'             => ['key' => $keyId, 'secret' => $keySecret],
                    // Quien espera es una persona frente a la pantalla.
                    'http'                    => ['connect_timeout' => 5, 'timeout' => 20],
                ]);
            } catch (\Throwable $e) {
                error_log('iDrive: no se pudo inicializar: ' . $e->getMessage());
                $s3 = null;
            }
        }
    }

    if ($s3 === null || $s3Key === '') return null;

    try {
        if ($cotizacionId > 0) {
            $cabecera = $s3->headObject(['Bucket' => $bucket, 'Key' => $s3Key]);
            $deCot    = $cabecera['Metadata']['cotizacion-id'] ?? null;
            if ($deCot !== null && (int) $deCot !== $cotizacionId) {
                error_log("iDrive: el objeto pertenece a otra cotización (se esperaba #{$cotizacionId}); no se firma.");
                return null;
            }
        }
        $cmd = $s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $s3Key]);
        return (string) $s3->createPresignedRequest($cmd, "+{$minutos} minutes")->getUri();
    } catch (\Throwable $e) {
        error_log('iDrive presigned URL error: ' . $e->getMessage());
        return null;
    }
}

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    // Cambiar estado
    if ($_POST['accion'] === 'cambiar_estado') {
        $cotId   = (int)($_POST['cotizacion_id'] ?? 0);
        $estado  = trim($_POST['estado']         ?? '');
        $coment  = trim($_POST['comentarios']    ?? '');
        $validos = ['pendiente','enviada','aceptada','rechazada','pagado'];

        if (!$cotId || !in_array($estado, $validos, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit;
        }
        $cot = $cotizacionModel->getByIdYAgente($cotId, $agenteId);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'No encontrada.']); exit; }

        $cotizacionModel->updateEstado($cotId, $estado, $coment);

        if ($estado === 'aceptada') {
            $fIni = trim($_POST['fecha_inicio_vig'] ?? '') ?: null;
            $fFin = trim($_POST['fecha_fin_vig']    ?? '') ?: null;
            if ($fIni || $fFin) {
                Connection::getConnection()->prepare(
                    "UPDATE cotizaciones SET
                        fecha_inicio_vigencia = COALESCE(?,fecha_inicio_vigencia),
                        fecha_fin_vigencia    = COALESCE(?,fecha_fin_vigencia)
                     WHERE id = ? AND empresa_id = ?"
                )->execute([$fIni, $fFin, $cotId, $empresaId]);
            }
        }

        if (in_array($estado, ['aceptada','pagado'], true)) {
            $clienteModel->updateEstado((int)$cot['cliente_id'], $agenteId, 'cerrado');
        }
        $historialModel->registrar(
            (int)$cot['cliente_id'], $agenteId, 'cotizacion',
            "Estado cambiado a '{$estado}'",
            $coment ?: 'Sin comentarios.'
        );
        echo json_encode(['ok'=>true,'abrir_pago'=>$estado==='pagado']); exit;
    }

    // Registrar pago
    if ($_POST['accion'] === 'registrar_pago') {
        $cotId     = (int)($_POST['cotizacion_id'] ?? 0);
        $metodo    = trim($_POST['metodo_pago']    ?? '');
        $fechaPago = trim($_POST['fecha_pago']     ?? date('Y-m-d'));
        $monto     = (float)($_POST['monto']       ?? 0);
        $notas     = trim($_POST['notas']          ?? '');
        $validosMP = ['spei','contado','meses_sin_intereses'];

        if (!$cotId || !in_array($metodo, $validosMP, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Método de pago inválido.']); exit;
        }
        $cot = $cotizacionModel->getByIdYAgente($cotId, $agenteId);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'No encontrada.']); exit; }

        $rutaArchivo = $nombreArchivo = null;
        if (!empty($_FILES['comprobante']['name']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['comprobante'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','jpg','jpeg','png']) || $file['size'] > 10*1024*1024) {
                echo json_encode(['ok'=>false,'msg'=>'Archivo inválido (máx 10MB, PDF/imagen).']); exit;
            }
            $uploadDir     = dirname(__DIR__, 3) . "/uploads/pagos/{$cotId}/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $nombreArchivo = 'comprobante_pago_' . time() . '.' . $ext;
            $rutaArchivo   = "uploads/pagos/{$cotId}/{$nombreArchivo}";
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $nombreArchivo)) {
                echo json_encode(['ok'=>false,'msg'=>'Error al subir comprobante.']); exit;
            }
        }

        $db  = Connection::getConnection();
        $chk = $db->query("SHOW TABLES LIKE 'cotizacion_pagos'")->fetchColumn();
        if ($chk) {
            $db->prepare(
                'INSERT INTO cotizacion_pagos
                 (cotizacion_id,agente_id,metodo_pago,nombre_archivo,ruta,fecha_pago,monto,notas,empresa_id)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$cotId, $agenteId, $metodo, $nombreArchivo, $rutaArchivo, $fechaPago, $monto, $notas, $empresaId]);
        }

        $cotizacionModel->updateEstado($cotId, 'pagado', 'Pago registrado — ' . ucfirst(str_replace('_',' ',$metodo)));
        $cotizacionModel->updateMetodoPago($cotId, $agenteId, $metodo);
        $historialModel->registrar(
            (int)$cot['cliente_id'], $agenteId, 'venta',
            'Pago registrado — ' . ucfirst(str_replace('_',' ',$metodo)),
            $notas ?: ($nombreArchivo ? 'Comprobante adjunto.' : 'Sin comprobante.')
        );
        echo json_encode(['ok'=>true]); exit;
    }

    // Obtener documentos de una cotización (para modal de docs)
    if ($_POST['accion'] === 'get_documentos') {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        $cot   = $cotizacionModel->getByIdYAgente($cotId, $agenteId);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'No encontrada.']); exit; }

        $uploadsBase = dirname(__DIR__, 3) . '/uploads/';
        $folio       = $cot['folio'] ?? '';

        // Nombre del cliente tal como lo arma el cron que sube a iDrive:
        // CONCAT(nombre,' ',apellido_paterno), que es NULL si falta alguno.
        $cli           = $clienteModel->find((int)$cot['cliente_id'], ['nombre', 'apellido_paterno']);
        $clienteNombre = ($cli && $cli['nombre'] !== null && $cli['apellido_paterno'] !== null)
            ? $cli['nombre'] . ' ' . $cli['apellido_paterno']
            : '';

        $archivos  = $cotizacionModel->getArchivos($cotId);
        $tipoLabel = CotizacionModel::etiquetasDocumento((string)($cot['ramo'] ?? ''));
        $docs = array_map(function($a) use ($tipoLabel, $uploadsBase, $cotId, $folio, $clienteNombre) {
            $ext       = strtolower(pathinfo($a['nombre_archivo'], PATHINFO_EXTENSION));
            $rutaFis   = $uploadsBase . 'cotizaciones/' . $cotId . '/' . $a['nombre_archivo'];
            $dispLocal = ($a['idrive_borrado'] === null) && file_exists($rutaFis);
            $urlLocal  = $dispLocal ? '/archivo?file=cotizaciones/' . $cotId . '/' . $a['nombre_archivo'] : null;
            if (!$urlLocal && !empty($a['idrive_subido'])) {
                $urlLocal = generarUrlIdrive(
                    ArchivosCotizacion::claveIdrive($clienteNombre, $folio, (string)($a['tipo_documento'] ?? ''), $a['nombre_archivo']),
                    $cotId
                );
            }
            return [
                'id'         => (int)$a['id'],
                'tipo'       => $a['tipo_documento'],
                'tipo_label' => $tipoLabel[$a['tipo_documento']] ?? ucfirst(str_replace('_',' ',$a['tipo_documento'])),
                'nombre_orig'=> $a['nombre_original'],
                'nombre_arch'=> $a['nombre_archivo'],
                'url_local'  => $urlLocal,
                'en_idrive'  => !empty($a['idrive_subido']),
                'es_imagen'  => in_array($ext, ['jpg','jpeg','png','webp'], true),
                'subido_en'  => $a['subido_en'] ?? null,
            ];
        }, $archivos);

        $pagos = [];
        try {
            $db = Connection::getConnection();
            $stP = $db->prepare(
                'SELECT id, metodo_pago, nombre_archivo, ruta, fecha_pago, monto, notas, creado_en
                 FROM cotizacion_pagos WHERE cotizacion_id = ? AND empresa_id = ? ORDER BY creado_en ASC'
            );
            $stP->execute([$cotId, $empresaId]);
            foreach ($stP->fetchAll(\PDO::FETCH_ASSOC) as $p) {
                $urlPago = null;
                if (!empty($p['ruta'])) {
                    $rutaFisica = dirname(__DIR__, 3) . '/' . ltrim($p['ruta'], '/');
                    if (file_exists($rutaFisica)) {
                        $rutaPublica = preg_replace('#^uploads/#', '', ltrim($p['ruta'], '/'));
                        $urlPago     = '/archivo?file=' . $rutaPublica;
                    }
                }
                $ext = strtolower(pathinfo($p['nombre_archivo'] ?? '', PATHINFO_EXTENSION));
                $pagos[] = [
                    'id'          => (int)$p['id'],
                    'metodo'      => $p['metodo_pago'],
                    'metodo_label'=> ['spei'=>'SPEI','contado'=>'Contado','meses_sin_intereses'=>'MSI'][$p['metodo_pago']] ?? $p['metodo_pago'],
                    'nombre_arch' => $p['nombre_archivo'],
                    'url_local'   => $urlPago,
                    'es_imagen'   => in_array($ext, ['jpg','jpeg','png','webp'], true),
                    'fecha_pago'  => $p['fecha_pago'],
                    'monto'       => (float)($p['monto'] ?? 0),
                    'notas'       => $p['notas'] ?? '',
                ];
            }
        } catch (\Throwable $eP) {}

        $polizas = [];
        try {
            $db = $db ?? Connection::getConnection();
            $stPol = $db->prepare(
                'SELECT id, nombre_archivo, nombre_original, ruta, idrive_subido, idrive_key,
                        compania, numero_poliza, fecha_inicio, fecha_fin, estado, notas, subido_en
                 FROM polizas WHERE cotizacion_id = ? AND empresa_id = ? AND eliminado_en IS NULL ORDER BY subido_en ASC'
            );
            $stPol->execute([$cotId, $empresaId]);
            foreach ($stPol->fetchAll(\PDO::FETCH_ASSOC) as $pol) {
                $urlPol = null;
                if (!empty($pol['ruta'])) {
                    $rutaFisica = dirname(__DIR__, 3) . '/' . ltrim($pol['ruta'], '/');
                    if (file_exists($rutaFisica)) {
                        $rutaPublica = preg_replace('#^uploads/#', '', ltrim($pol['ruta'], '/'));
                        $urlPol      = '/archivo?file=' . $rutaPublica;
                    }
                }
                if (!$urlPol && !empty($pol['idrive_subido'])) {
                    // El cron guarda en idrive_key la clave con la que subió la
                    // póliza; solo las filas antiguas sin ella usan la clave calculada.
                    $urlPol = generarUrlIdrive(
                        !empty($pol['idrive_key'])
                            ? (string)$pol['idrive_key']
                            : ArchivosCotizacion::claveIdrive($clienteNombre, $folio, 'poliza', (string)$pol['nombre_archivo'])
                    );
                }
                $ext = strtolower(pathinfo($pol['nombre_archivo'], PATHINFO_EXTENSION));
                $polizas[] = [
                    'id'           => (int)$pol['id'],
                    'nombre_orig'  => $pol['nombre_original'],
                    'nombre_arch'  => $pol['nombre_archivo'],
                    'url_local'    => $urlPol,
                    'en_idrive'    => !empty($pol['idrive_subido']),
                    'es_imagen'    => in_array($ext, ['jpg','jpeg','png','webp'], true),
                    'compania'     => $pol['compania'] ?? '',
                    'numero_poliza'=> $pol['numero_poliza'] ?? '',
                    'fecha_inicio' => $pol['fecha_inicio'] ?? '',
                    'fecha_fin'    => $pol['fecha_fin'] ?? '',
                    'estado'       => $pol['estado'] ?? 'vigente',
                    'notas'        => $pol['notas'] ?? '',
                    'subido_en'    => $pol['subido_en'] ?? '',
                ];
            }
        } catch (\Throwable $ePol) {}

        echo json_encode(['ok'=>true,'docs'=>$docs,'pagos'=>$pagos,'polizas'=>$polizas,'folio'=>$cot['folio']??'']);
        exit;
    }

    // === NUEVA ACCIÓN: enviar_a_coordinador ===
    if ($_POST['accion'] === 'enviar_a_coordinador') {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
        $cot = $cotizacionModel->getByIdYAgente($cotId, $agenteId);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'No encontrada']); exit; }
        // Verificar que no esté ya enviada
        if ($cot['emision_estado'] === 'en_proceso' || $cot['emision_estado'] === 'emitida') {
            echo json_encode(['ok'=>false,'msg'=>'Ya fue enviada al coordinador']); exit;
        }
        $db = Connection::getConnection();
        // Actualizar emision_estado y estado
        $db->prepare("UPDATE cotizaciones SET emision_estado='en_proceso', estado='enviada' WHERE id=? AND empresa_id=?")->execute([$cotId, $empresaId]);
        // Notificar a los coordinadores y superadmins de la misma empresa
        $stCoords = $db->prepare("SELECT id FROM agentes WHERE rol IN ('coordinador','superadmin') AND activo=1 AND empresa_id=?");
        $stCoords->execute([$empresaId]);
        $coords = $stCoords->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($coords as $coordId) {
            $db->prepare(
                "INSERT INTO notificaciones (agente_id,tipo,titulo,mensaje,urgente,color,url_accion,leida,creada_en,empresa_id)
                 VALUES (?,?,?,?,1,'blue',?,0,NOW(),?)"
            )->execute([
                $coordId, 'emision',
                'Nueva cotización para emisión — ' . ($cot['folio'] ?? '#'.$cotId),
                'El asesor envió la cotización de ' . ($cot['cliente_nombre'] ?? '') . ' para gestión de cobro.',
                '/admin?tab=emision',
                $empresaId,
            ]);
        }
        // Historial
        $historialModel->registrar(
            (int)$cot['cliente_id'], $agenteId, 'cotizacion',
            'Cotización enviada al coordinador para cobro',
            'Folio: ' . ($cot['folio'] ?? '#'.$cotId)
        );
        echo json_encode(['ok'=>true]);
        exit;
    }

    // Enviar la cotización al cliente por correo. Solo pasa a 'enviada' si
    // el correo salió de verdad (antes solo se cambiaba el estado).
    if ($_POST['accion'] === 'enviar_cliente') {
        // Esta acción le escribe a alguien de fuera: no se acepta sin token.
        if (!SessionManager::validateCsrf((string)($_POST['csrf_token'] ?? ''))) {
            echo json_encode(['ok'=>false,'msg'=>'La sesión caducó. Recarga la página.']); exit;
        }
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

        // El servicio carga la cotización y su cliente de la empresa activa,
        // comprueba que sea del asesor (o que su rol vea todas), envía con el
        // documento adjunto y, si salió, la marca y lo anota en el historial.
        $envio = CotizacionCorreo::enviarAlCliente($cotId, $agenteId, SessionManager::getRole());

        echo json_encode([
            'ok'       => $envio['ok'],
            'msg'      => $envio['msg'],
            'estado'   => $envio['estado'],
            'adjuntos' => $envio['adjuntos'],
            'omitidos' => $envio['omitidos'],
        ]);
        exit;
    }

    // Eliminar archivo de cotización (soft-delete)
    if ($_POST['accion'] === 'eliminar_archivo') {
        $archivoId = (int)($_POST['archivo_id'] ?? 0);
        if (!$archivoId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        try {
            $db = Connection::getConnection();
            $stCheck = $db->prepare(
                'SELECT ca.id FROM cotizacion_archivos ca
                 JOIN cotizaciones co ON co.id = ca.cotizacion_id
                 WHERE ca.id = ? AND co.agente_id = ? AND co.empresa_id = ? AND ca.eliminado_en IS NULL'
            );
            $stCheck->execute([$archivoId, $agenteId, $empresaId]);
            if (!$stCheck->fetch()) { echo json_encode(['ok'=>false,'msg'=>'No autorizado.']); exit; }
            $db->prepare('UPDATE cotizacion_archivos SET eliminado_en = NOW() WHERE id = ? AND empresa_id = ?')
               ->execute([$archivoId, $empresaId]);
            echo json_encode(['ok'=>true]); exit;
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'msg'=>'Error al eliminar.']); exit;
        }
    }

    // Editar cotización
    if ($_POST['accion'] === 'editar') {
        $cotId      = (int)($_POST['cotizacion_id'] ?? 0);
        $cot        = $cotizacionModel->getByIdYAgente($cotId, $agenteId);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'No encontrada.']); exit; }

        $compania   = trim($_POST['compania']          ?? $cot['compania']);
        $cobertura  = trim($_POST['tipo_cobertura']    ?? $cot['tipo_cobertura']);
        $precio     = (float)str_replace(',','', $_POST['precio'] ?? $cot['precio']);
        $deducible  = (float)($_POST['deducible']      ?? $cot['deducible']);
        $vigencia   = trim($_POST['vigencia']          ?? $cot['vigencia']);
        $comentarios= trim($_POST['comentarios']       ?? $cot['comentarios']);
        $metodo     = trim($_POST['metodo_pago']       ?? '') ?: null;
        $fIni       = trim($_POST['fecha_inicio_vig']  ?? '') ?: null;
        $fFin       = trim($_POST['fecha_fin_vig']     ?? '') ?: null;
        $fRecuencia = trim($_POST['frecuencia_renov']  ?? '') ?: null;

        if (!$compania || !$cobertura || $precio <= 0) {
            echo json_encode(['ok'=>false,'msg'=>'Datos incompletos.']); exit;
        }

        $db = Connection::getConnection();
        $db->prepare(
            "UPDATE cotizaciones
             SET compania=?, tipo_cobertura=?, precio=?, deducible=?, vigencia=?,
                 comentarios=?, metodo_pago=COALESCE(NULLIF(?,''),metodo_pago),
                 fecha_inicio_vigencia=COALESCE(?,fecha_inicio_vigencia),
                 fecha_fin_vigencia=COALESCE(?,fecha_fin_vigencia),
                 frecuencia_renovacion=COALESCE(NULLIF(?,''),frecuencia_renovacion)
             WHERE id=? AND agente_id=? AND empresa_id=?"
        )->execute([
            $compania, $cobertura, $precio, $deducible, $vigencia,
            $comentarios, $metodo, $fIni, $fFin, $fRecuencia,
            $cotId, $agenteId, $empresaId,
        ]);

        $historialModel->registrar(
            (int)$cot['cliente_id'], $agenteId, 'cotizacion',
            "Cotización editada — {$compania} · {$cobertura}",
            '$' . number_format($precio, 2)
        );

        echo json_encode(['ok'=>true,'precio'=>$precio,'compania'=>$compania,'cobertura'=>$cobertura]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
}

// ── Datos para la vista ───────────────────────────────────────────────────────
// Filtramos por el ramo de la pestaña (?ramo=), si hay uno
$cotizaciones = $cotizacionModel->getAllConCliente($agenteId, $ramoFiltro);
foreach ($cotizaciones as &$c) {
    $c['id']        = (int)  $c['id'];
    $c['precio']    = (float)$c['precio'];
    $c['deducible'] = (float)$c['deducible'];
    $c['folio']     = $c['folio'] ?? '';
    $c['ramo']      = $c['ramo']  ?? 'autos';
    $c['fecha_fin_vigencia'] = $c['fecha_fin_vigencia'] ?? null;
}
unset($c);

// Parche: si el modelo no devuelve fecha_fin_vigencia, la obtenemos directamente
if (!empty($cotizaciones) && empty(array_filter(array_column($cotizaciones, 'fecha_fin_vigencia')))) {
    $_ids   = array_column($cotizaciones, 'id');
    $_ph    = implode(',', array_fill(0, count($_ids), '?'));
    $_db    = Connection::getConnection();
    $_stmt  = $_db->prepare(
        "SELECT id, fecha_fin_vigencia FROM cotizaciones WHERE id IN ($_ph) AND empresa_id = ?"
    );
    $_stmt->execute(array_merge($_ids, [$empresaId]));
    $_vigMap = array_column($_stmt->fetchAll(\PDO::FETCH_ASSOC), 'fecha_fin_vigencia', 'id');
    foreach ($cotizaciones as &$c) {
        if (empty($c['fecha_fin_vigencia'])) {
            $c['fecha_fin_vigencia'] = $_vigMap[$c['id']] ?? null;
        }
    }
    unset($c);
}

$total           = count($cotizaciones);
$pendientes      = count(array_filter($cotizaciones, fn($c) => $c['estado']==='pendiente'));
$enviadas        = count(array_filter($cotizaciones, fn($c) => $c['estado']==='enviada'));
$aceptadas       = count(array_filter($cotizaciones, fn($c) => $c['estado']==='aceptada'));
$valor_aceptadas = array_sum(array_map(fn($c) => $c['estado']==='aceptada' ? $c['precio'] : 0, $cotizaciones));

// KPIs por ramo (conteo global para las pestañas)
$ramoStats = $cotizacionModel->contarPorRamo($agenteId);

$estados_cot = [
    'pendiente' => ['label'=>'Pendiente', 'class'=>'badge-warning'],
    'enviada'   => ['label'=>'Enviada',   'class'=>'badge-info'],
    'aceptada'  => ['label'=>'Aceptada',  'class'=>'badge-success'],
    'rechazada' => ['label'=>'Rechazada', 'class'=>'badge-danger'],
    'pagado'    => ['label'=>'Pagado',    'class'=>'badge-success'],
];

// Nombre e ícono de cada ramo, del catálogo de la empresa. Incluye los ramos
// apagados para que sus cotizaciones se sigan viendo con su nombre; las
// pestañas, en cambio, solo muestran los activos ($ramosActivos).
$ramos_info  = CotizacionModel::infoRamos();
$ramos_icono = array_map(fn($r) => $r['icono'], $ramos_info);

// Filtro de compañía: las aseguradoras del catálogo (del ramo de la pestaña,
// o de todos) más las que ya están guardadas en las cotizaciones listadas,
// que conservan el nombre con el que se guardaron aunque el catálogo cambie.
$companias = CotizacionModel::opcionesConValores(
    $catalogo->etiquetas(CatalogoModel::ASEGURADORA, $ramoFiltro ?? ''),
    array_column($cotizaciones, 'compania')
);
$vigencias  = ['6 meses','12 meses','24 meses'];
$deducibles = [2,3,5,10,15,20];

$cotizacionesJSON = json_encode($cotizaciones);
$ramosInfoJSON    = json_encode($ramos_info, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$urlNuevaCot      = '/crear-cotizacion' . ($ramoFiltro ? '?ramo=' . urlencode($ramoFiltro) : '');
$pageTitle = 'Cotizaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style>
        .folio-tag{font-family:'Courier New',monospace;font-size:10px;font-weight:700;color:var(--accent-color);background:var(--info-light);padding:2px 6px;border-radius:4px;display:inline-block;}
        .folio-tag.empty{color:var(--text-muted);background:var(--bg-secondary);font-style:italic;font-family:inherit;font-weight:400;}
        #cotizacionesTableBody tr{cursor:pointer;}
        #cotizacionesTableBody tr:hover td{background:var(--bg-secondary);}

        /* ── Pestañas de ramo ── */
        .ramo-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;}
        .ramo-tab{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:8px;
                  border:1.5px solid var(--border-color);background:var(--bg-primary);cursor:pointer;
                  font-size:12px;font-weight:600;color:var(--text-secondary);text-decoration:none;
                  transition:all .18s;}
        .ramo-tab:hover{border-color:var(--accent-color);color:var(--accent-color);}
        .ramo-tab.active{border-color:var(--accent-color);background:var(--info-light);color:var(--accent-color);}
        .ramo-tab .tab-count{font-size:10px;background:var(--bg-secondary);color:var(--text-muted);
                             padding:1px 7px;border-radius:99px;}
        .ramo-tab.active .tab-count{background:var(--accent-color);color:#fff;}
    </style>
</head>
<body>
<div class="crm-wrapper">
    <?php require __DIR__ . '/../partials/_navbar.php'; ?>
    <main class="main-content">

        <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            <span class="alert-icon"><?= icono('check-circle-fill') ?></span>
            <div class="alert-content"><div class="alert-title">
                <?php echo $_GET['ok']==='editada' ? 'Cotización actualizada.' : 'Cotización creada correctamente.'; ?>
            </div></div>
        </div>
        <?php endif; ?>

        <header class="page-header">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </span>
                        Cotizaciones
                        <?php if ($ramoFiltro): ?>
                        <span style="font-size:14px;font-weight:400;color:var(--text-muted);margin-left:8px;">
                            — <?php echo icono($ramos_info[$ramoFiltro]['icono'] ?? 'clipboard'); ?> <?php echo htmlspecialchars($ramos_info[$ramoFiltro]['label'] ?? $ramoFiltro); ?>
                        </span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">Gestiona todas tus cotizaciones de seguros</p>
                </div>
                <div class="page-actions">
                    <a href="<?php echo htmlspecialchars($urlNuevaCot); ?>" class="btn btn-primary">+ Nueva Cotización</a>
                </div>
            </div>
        </header>

        <!-- Stats -->
        <section class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
            <?php foreach([
                [$total,           'Total',      ''],
                [$pendientes,      'Pendientes', 'warning'],
                [$enviadas,        'Enviadas',   'info'],
                [$aceptadas,       'Aceptadas',  'success'],
                ['$'.number_format((float)$valor_aceptadas,0), 'Ingresos', 'success'],
            ] as [$v,$l,$c]): ?>
            <div class="stat-card <?php echo $c; ?>">
                <div class="stat-card-value"><?php echo $v; ?></div>
                <div class="stat-card-label"><?php echo $l; ?></div>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Filtros -->
        <section class="card mb-24">
            <div class="card-body">
                <div class="filters-bar">
                    <div class="search-bar">
                        <span class="search-bar-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                        <input type="text" id="searchInput" class="search-bar-input" placeholder="Buscar por cliente, folio, vehículo...">
                    </div>
                    <select class="filter-select" id="filterEstado">
                        <option value="todos">Todos los estados</option>
                        <?php foreach($estados_cot as $k=>$v): ?>
                        <option value="<?php echo $k; ?>"><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php /* El ramo se filtra con las pestañas de arriba; aquí no se repite. */ ?>
                    <select class="filter-select" id="filterCompania">
                        <option value="todos">Todas las compañías</option>
                        <?php foreach($companias as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="btnSortVence" class="btn btn-outline"
                            title="Ordenar por próximos a vencer"
                            id="btnSortVence"
                            style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                        <i class="bi bi-clock-history" style="font-size:14px;"></i>
                        Próximos a vencer
                    </button>
                </div>
            </div>
        </section>

        <!-- Tabla -->
        <section class="card responsive" style="margin:auto;">
            <div class="card-header">
                <h3 class="card-title">Lista de Cotizaciones
                    <span class="badge badge-neutral" id="cotizacionesCount"><?php echo $total; ?> cotizaciones</span>
                </h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0;">Doble clic en una fila para ver detalle y documentos</p>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($cotizaciones)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><?= icono('clipboard') ?></div>
                    <div class="empty-state-title">Sin cotizaciones<?php echo $ramoFiltro ? ' en este ramo' : ''; ?></div>
                    <a href="<?php echo htmlspecialchars($urlNuevaCot); ?>" class="btn btn-primary">+ Nueva Cotización</a>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="data-table" id="cotizacionesTable">
                        <thead>
                            <tr>
                                <th>Folio</th><th>Cliente</th><th>Compañía / Cobertura</th>
                                <th>Prima</th><th>Estado</th><th>Fecha</th><th>Vence</th><th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cotizacionesTableBody">
                            <?php foreach ($cotizaciones as $cot):
                                $estInfo   = $estados_cot[$cot['estado']] ?? ['label'=>'—','class'=>'badge-neutral'];
                                $partes    = explode(' ', $cot['cliente_nombre']);
                                $iniciales = count($partes)>=2
                                    ? strtoupper(substr($partes[0],0,1).substr($partes[1],0,1))
                                    : strtoupper(substr($cot['cliente_nombre'],0,2));
                                $ramoIcono = $ramos_icono[$cot['ramo']] ?? 'clipboard';
                            ?>
                            <tr data-id="<?php echo $cot['id']; ?>"
                                data-estado="<?php echo $cot['estado']; ?>"
                                data-compania="<?php echo htmlspecialchars($cot['compania']); ?>"
                                data-ramo="<?php echo $cot['ramo']; ?>"
                                data-search="<?php echo htmlspecialchars(strtolower($cot['cliente_nombre'].' '.$cot['folio'].' '.($cot['vehiculo']??''))); ?>"
                                data-vence="<?php echo htmlspecialchars($cot['fecha_fin_vigencia'] ?? ''); ?>"
                                data-cotizacion='<?php echo htmlspecialchars(json_encode($cot), ENT_QUOTES); ?>'>
                                <td>
                                    <?php if (!empty($cot['folio'])): ?>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <span><?php echo icono($ramoIcono); ?></span>
                                        <span class="folio-tag"><?php echo htmlspecialchars($cot['folio']); ?></span>
                                    </div>
                                    <?php else: ?>
                                    <span class="folio-tag empty">Sin folio</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-color);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;">
                                            <?php echo htmlspecialchars($iniciales); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($cot['cliente_nombre']); ?></div>
                                            <?php if (!empty($cot['vehiculo'])): ?>
                                            <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($cot['vehiculo']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($cot['compania']); ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($cot['tipo_cobertura']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:700;color:var(--success-color);">$<?php echo number_format((float)$cot['precio'],2); ?></div>
                                    <?php if (!empty($cot['deducible'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted);">Ded: <?php echo $cot['deducible']; ?>%</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $estInfo['class']; ?>"><?php echo $estInfo['label']; ?></span>
                                </td>
                                <td style="font-size:12px;color:var(--text-secondary);">
                                    <?php echo $cot['fecha'] ? date('d/m/Y', strtotime($cot['fecha'])) : '—'; ?>
                                </td>
                                <td style="font-size:12px;">
                                    <?php if (!empty($cot['fecha_fin_vigencia'])): ?>
                                    <?php $diasRestantes = (int)ceil((strtotime($cot['fecha_fin_vigencia']) - time()) / 86400); ?>
                                    <div><?php echo date('d/m/Y', strtotime($cot['fecha_fin_vigencia'])); ?></div>
                                    <?php if ($diasRestantes <= 0): ?>
                                    <div style="font-size:10px;color:#dc2626;font-weight:700;">VENCIDA</div>
                                    <?php elseif ($diasRestantes <= 30): ?>
                                    <div style="font-size:10px;color:#f97316;font-weight:600;"><?php echo $diasRestantes; ?> días</div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span style="font-size:11px;color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-outline" data-action="ver" data-id="<?php echo $cot['id']; ?>" title="Ver" aria-label="Ver"><?= icono('eye') ?></button>
                                        <button class="btn btn-sm btn-outline" data-action="estado" data-id="<?php echo $cot['id']; ?>" title="Cambiar estado" aria-label="Cambiar estado"><?= icono('pencil') ?></button>
                                        <button class="btn btn-sm btn-outline" data-action="editar" data-id="<?php echo $cot['id']; ?>" title="Editar" aria-label="Editar"><?= icono('pencil-square') ?></button>
                                        <?php if ($cot['estado']==='pendiente'): ?>
                                        <button class="btn btn-sm btn-primary" data-action="enviar" data-id="<?php echo $cot['id']; ?>" title="Enviar al cliente" aria-label="Enviar al cliente"><?= icono('send') ?></button>
                                        <?php endif; ?>
                                        <?php
                                        $emisEst = $cot['emision_estado'] ?? '';
                                        if ($emisEst === '' || $emisEst === 'pendiente'): ?>
                                        <button class="btn btn-sm btn-outline" data-action="enviarCoordinador" data-id="<?php echo $cot['id']; ?>" title="Enviar al coordinador para cobro" style="font-size:11px;"><?= icono('clipboard') ?> Coord.</button>
                                        <?php elseif ($emisEst === 'en_proceso'): ?>
                                        <span class="badge badge-info" title="En proceso de cobro" style="font-size:10px;"><?= icono('hourglass-split') ?> Cobro</span>
                                        <?php elseif ($emisEst === 'emitida'): ?>
                                        <span class="badge badge-success" title="Póliza emitida" style="font-size:10px;"><?= icono('check-circle-fill') ?> Emitida</span>
                                        <?php endif; ?>
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

<!-- ══════════════════════════════════════════════════════════
     MODAL: VER COTIZACIÓN
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalVerCotizacion">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Detalle de Cotización</h3>
            <button class="modal-close" data-close="modalVerCotizacion">&times;</button>
        </div>
        <div class="modal-body" id="modalCotizacionContent"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalVerCotizacion">Cerrar</button>
            <button class="btn btn-ghost" id="btnPrint"><?= icono('printer') ?> Imprimir</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: CAMBIAR ESTADO (legacy — redirige a flujo)
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalCambiarEstado">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Cambiar Estado</h3>
            <button class="modal-close" data-close="modalCambiarEstado">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="estadoCotizacionId">
            <div class="form-group">
                <label class="form-label required">Nuevo Estado</label>
                <select class="form-select" id="nuevoEstado">
                    <?php foreach($estados_cot as $k=>$v): ?>
                    <option value="<?php echo $k; ?>"><?php echo $v['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Comentarios</label>
                <textarea class="form-textarea" id="estadoComentarios" rows="3" placeholder="Notas sobre el cambio de estado..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalCambiarEstado">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarEstado">Guardar</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: REGISTRAR PAGO
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalPago">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><?= icono('cash-coin') ?> Registrar Pago</h3>
            <button class="modal-close" data-close="modalPago">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pagoCotId">
            <div class="form-group">
                <label class="form-label required">Método de Pago</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                    <?php foreach(['spei'=>['bank','SPEI','Transferencia'],'contado'=>['cash','Contado','Efectivo'],'meses_sin_intereses'=>['credit-card','MSI','Meses sin intereses']] as $k=>[$ico,$tit,$sub]): ?>
                    <div id="mp_<?php echo $k; ?>" data-action="selMP"
                         style="border:2px solid var(--border-color);border-radius:8px;padding:12px;cursor:pointer;text-align:center;transition:all .2s;">
                        <div style="font-size:20px;"><?php echo icono($ico); ?></div>
                        <div style="font-size:12px;font-weight:600;"><?php echo $tit; ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?php echo $sub; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="pagoMetodo">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Fecha de Pago</label>
                    <input type="date" class="form-input" id="pagoFecha" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Monto ($)</label>
                    <input type="number" class="form-input" id="pagoMonto" placeholder="0.00" step="0.01" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Comprobante <span style="font-size:11px;color:var(--text-muted);">(PDF/imagen — opcional)</span></label>
                <div id="pagoZone"
                     style="border:2px dashed var(--border-color);border-radius:8px;padding:20px;text-align:center;cursor:pointer;background:var(--bg-secondary);">
                    <div style="font-size:28px;"><?= icono('paperclip') ?></div>
                    <div style="font-size:13px;font-weight:600;">Haz clic para adjuntar</div>
                    <input type="file" id="pagoFile" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" id="pagoFile" data-handler="pagoFile">
                </div>
                <div id="pagoFileInfo" style="display:none;margin-top:8px;padding:8px 12px;background:var(--bg-secondary);border-radius:6px;font-size:12px;align-items:center;gap:8px;">
                    <?= icono('paperclip') ?><span id="pagoFileName" style="flex:1;"></span>
                    <button type="button" id="btnQuitarPago" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:16px;">×</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notas</label>
                <textarea class="form-textarea" id="pagoNotas" placeholder="Referencia, número de operación..." rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalPago">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarPago"><?= icono('floppy') ?> Registrar Pago</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DOCUMENTOS DE COTIZACIÓN
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalDocumentos">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title"><?= icono('paperclip') ?> Documentos —<span id="modalDocsFolio" style="font-family:'Courier New',monospace;color:var(--accent-color);"></span></h3>
            <button class="modal-close" data-close="modalDocumentos">&times;</button>
        </div>
        <div class="modal-body" id="modalDocsContent" style="min-height:160px;">
            <div style="text-align:center;padding:32px;color:var(--text-muted);">Cargando...</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalDocumentos">Cerrar</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: FLUJO DE ESTADO (contextual)
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEstadoFlujo">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title" id="flujoModalTitle">Actualizar Estado</h3>
            <button class="modal-close" data-close="modalEstadoFlujo">&times;</button>
        </div>
        <div class="modal-body" id="flujoModalBody"></div>
        <div class="modal-footer" id="flujoModalFooter"></div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script src="/js/cotizaciones.js" nonce="<?= $cspNonce ?>"></script>

<script nonce="<?= $cspNonce ?>">
// ─────────────────────────────────────────────────────────────────────────────
// DATOS (inyectados desde PHP)
// ─────────────────────────────────────────────────────────────────────────────
const cotizacionesData    = <?php echo $cotizacionesJSON; ?>;
const RAMO_ACTIVO         = <?php echo json_encode($ramoFiltro); ?>; // ramo de la pestaña (?ramo=), null = todos
const CSRF_TOKEN          = <?php echo json_encode(SessionManager::getCsrfToken()); ?>;

// Nombre e ícono de cada ramo, del catálogo de la empresa (incluye los apagados).
const RAMOS_INFO = <?php echo $ramosInfoJSON; ?>;

// ─────────────────────────────────────────────────────────────────────────────
// FILTROS Y BÚSQUEDA (el ramo lo filtran las pestañas, en el servidor)
// ─────────────────────────────────────────────────────────────────────────────
function filtrarTabla() {
    const q        = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const estado   = document.getElementById('filterEstado')?.value  || 'todos';
    const compania = (document.getElementById('filterCompania')?.value || 'todos').toLowerCase();
    let visible    = 0;
    document.querySelectorAll('#cotizacionesTableBody tr').forEach(tr => {
        const show = (!q       || (tr.dataset.search   || '').includes(q))
                  && (estado   === 'todos' || tr.dataset.estado   === estado)
                  && (compania === 'todos' || (tr.dataset.compania || '').toLowerCase() === compania);
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const cnt = document.getElementById('cotizacionesCount');
    if (cnt) cnt.textContent = visible + ' cotizacion' + (visible !== 1 ? 'es' : '');
}
document.getElementById('searchInput')?.addEventListener('input', filtrarTabla);
document.getElementById('filterEstado')?.addEventListener('change', filtrarTabla);
document.getElementById('filterCompania')?.addEventListener('change', filtrarTabla);

// ─────────────────────────────────────────────────────────────────────────────
// ORDENAR POR VENCIMIENTO
// ─────────────────────────────────────────────────────────────────────────────
let _sortVenceActivo = false;
let _ordenOriginal   = null;

function toggleSortVence() {
    const tbody = document.getElementById('cotizacionesTableBody');
    const btn   = document.getElementById('btnSortVence');
    if (!tbody || !btn) return;

    if (!_sortVenceActivo) {
        _ordenOriginal = [...tbody.querySelectorAll('tr')].map(tr => tr.dataset.id);

        const rows     = [...tbody.querySelectorAll('tr')];
        const conVence = rows.filter(tr => tr.dataset.vence && tr.dataset.vence !== '');
        const sinVence = rows.filter(tr => !tr.dataset.vence || tr.dataset.vence === '');
        const hoy      = new Date(); hoy.setHours(0,0,0,0);

        conVence.sort((a, b) =>
            new Date(a.dataset.vence + 'T00:00:00') - new Date(b.dataset.vence + 'T00:00:00')
        );
        [...conVence, ...sinVence].forEach(tr => tbody.appendChild(tr));

        rows.forEach(tr => {
            if (!tr.dataset.vence || tr.dataset.vence === '') return;
            const dias = Math.ceil((new Date(tr.dataset.vence + 'T00:00:00') - hoy) / 86400000);
            if      (dias <= 0)  { tr.style.outline = '2px solid #ef4444'; tr.style.outlineOffset = '-2px'; }
            else if (dias <= 7)  { tr.style.outline = '2px solid #f97316'; tr.style.outlineOffset = '-2px'; }
            else if (dias <= 30) { tr.style.outline = '2px solid #f59e0b'; tr.style.outlineOffset = '-2px'; }
        });

        btn.style.background  = 'var(--warning-light, #fef3c7)';
        btn.style.borderColor = '#f59e0b';
        btn.style.color       = '#92400e';
        btn.innerHTML = '<i class="bi bi-clock-history" style="font-size:14px;"></i> Ordenado por vencimiento <span style="font-size:10px;font-weight:700;background:#f59e0b;color:#fff;padding:1px 5px;border-radius:4px;margin-left:4px;">ACTIVO</span>';
        _sortVenceActivo = true;

    } else {
        if (_ordenOriginal) {
            _ordenOriginal.forEach(id => {
                const tr = tbody.querySelector(`tr[data-id="${id}"]`);
                if (tr) tbody.appendChild(tr);
            });
        }
        tbody.querySelectorAll('tr').forEach(tr => { tr.style.outline = ''; tr.style.outlineOffset = ''; });
        btn.style.background  = '';
        btn.style.borderColor = '';
        btn.style.color       = '';
        btn.innerHTML = '<i class="bi bi-clock-history" style="font-size:14px;"></i> Próximos a vencer';
        _sortVenceActivo = false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DOBLE CLIC → VER DETALLE
// ─────────────────────────────────────────────────────────────────────────────
document.getElementById('cotizacionesTableBody')?.addEventListener('dblclick', e => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    const id = parseInt(tr.dataset.id);
    if (id) verCotizacion(id);
});

// ─────────────────────────────────────────────────────────────────────────────
// VER DETALLE DE COTIZACIÓN — con detalles de ramo dinámicos
// ─────────────────────────────────────────────────────────────────────────────
function verCotizacion(id) {
    const cot = cotizacionesData.find(c => c.id === id);
    if (!cot) return;
    const eBadge = {pendiente:'badge-warning',enviada:'badge-info',aceptada:'badge-success',rechazada:'badge-danger',pagado:'badge-success'};
    const eLabel = {pendiente:'Pendiente',enviada:'Enviada',aceptada:'Aceptada',rechazada:'Rechazada',pagado:'Pagado'};
    const ramos  = Object.fromEntries(Object.entries(RAMOS_INFO).map(([k, r]) => [k, iconoSvg(r.icono)]));
    const ramoLabel = escapeHtml(RAMOS_INFO[cot.ramo]?.label || cot.ramo || '');
    const fechaFmt = cot.fecha ? new Date(cot.fecha+'T12:00').toLocaleDateString('es-MX',{day:'2-digit',month:'long',year:'numeric'}) : '—';

    // Construir sección de detalles por ramo
    let detallesRamoHtml = '';
    if (cot.detalles_ramo && Object.keys(cot.detalles_ramo).length > 0) {
        const labelMap = {
            // Autos
            tipo_vehiculo:'Tipo', marca:'Marca', modelo:'Modelo', anio:'Año',
            uso:'Uso', quemacocos:'Quemacocos', rines_lujo:'Rines de lujo', gps:'GPS',
            // Vida
            edad_asegurado:'Edad', sexo:'Sexo', suma_asegurada:'Suma asegurada',
            plazo_anos:'Plazo', beneficiarios:'Beneficiarios',
            // GMM
            edad_titular:'Edad titular', cobertura_maternidad:'Maternidad',
            nivel_hospitalario:'Nivel', deducible_tipo:'Tipo deducible', coaseguro_pct:'Coaseguro',
            // Empresas
            giro:'Giro', num_empleados:'Empleados', facturacion_anual:'Facturación anual',
            tipo_cobertura_emp:'Tipo cobertura', num_asegurados:'Asegurados',
            incluye_dependientes:'Incluye dependientes',
        };
        const items = Object.entries(cot.detalles_ramo)
            .filter(([,v]) => v !== '' && v !== null && v !== undefined)
            .map(([k,v]) => `<div style="background:var(--bg-secondary);border-radius:7px;padding:10px 12px;">
                <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;font-weight:700;margin-bottom:3px;">${labelMap[k]||k}</div>
                <div style="font-size:13px;font-weight:600;">${escapeHtml(String(v))}</div>
            </div>`).join('');
        if (items) {
            detallesRamoHtml = `
            <div>
                <div style="font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
                    ${ramos[cot.ramo]||iconoSvg('clipboard')} Detalles de ${ramoLabel}
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;">${items}</div>
            </div>`;
        }
    }

    document.getElementById('modalCotizacionContent').innerHTML = `
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div style="background:linear-gradient(135deg,#0f2940,#1a3a5c);border-radius:10px;padding:20px 24px;color:#fff;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div>
                        <div style="font-size:11px;opacity:.65;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Folio</div>
                        <div style="font-family:'Courier New',monospace;font-size:18px;font-weight:800;letter-spacing:2px;">${escapeHtml(cot.folio||'Sin folio')}</div>
                    </div>
                    <span class="badge ${eBadge[cot.estado]||'badge-neutral'}" style="font-size:13px;padding:6px 14px;">${eLabel[cot.estado]||cot.estado}</span>
                </div>
                <div style="margin-top:12px;font-size:14px;opacity:.85;">${ramos[cot.ramo]||iconoSvg('clipboard')} ${escapeHtml(cot.compania)} — ${escapeHtml(cot.tipo_cobertura)}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="background:var(--bg-secondary);border-radius:8px;padding:14px;">
                    <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;">Cliente</div>
                    <div style="font-weight:600;font-size:14px;">${escapeHtml(cot.cliente_nombre)}</div>
                    ${cot.vehiculo ? `<div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">${ramos[cot.ramo]||iconoSvg('clipboard')} ${escapeHtml(cot.vehiculo)}</div>` : ''}
                </div>
                <div style="background:var(--bg-secondary);border-radius:8px;padding:14px;">
                    <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;">Prima</div>
                    <div style="font-weight:800;font-size:22px;color:var(--success-color);">$${parseFloat(cot.precio).toLocaleString('es-MX',{minimumFractionDigits:2})}</div>
                    <div style="font-size:11px;color:var(--text-secondary);">Deducible: ${cot.deducible}% · ${escapeHtml(cot.vigencia)}</div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                <div style="background:var(--bg-secondary);border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;">Fecha</div>
                    <div style="font-weight:600;margin-top:4px;font-size:13px;">${fechaFmt}</div>
                </div>
                <div style="background:var(--bg-secondary);border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;">Compañía</div>
                    <div style="font-weight:600;margin-top:4px;font-size:13px;">${escapeHtml(cot.compania)}</div>
                </div>
                <div style="background:var(--bg-secondary);border-radius:8px;padding:12px;text-align:center;">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;">Ramo</div>
                    <div style="font-weight:600;margin-top:4px;font-size:13px;">${ramos[cot.ramo]||''} ${ramoLabel}</div>
                </div>
            </div>
            ${detallesRamoHtml}
            ${cot.comentarios ? `
            <div style="background:var(--bg-secondary);border-left:3px solid var(--accent-color);border-radius:0 8px 8px 0;padding:14px 16px;">
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Comentarios</div>
                <div style="font-size:13px;line-height:1.6;color:var(--text-primary);">${escapeHtml(cot.comentarios)}</div>
            </div>` : ''}
            <div style="padding-top:12px;border-top:1px solid var(--border-color);display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                <button class="btn btn-sm btn-outline" data-action="docs" data-id="${cot.id}">${iconoSvg('paperclip')} Ver documentos</button>
                <button class="btn btn-sm btn-outline" data-action="estado" data-id="${cot.id}">${iconoSvg('pencil')} Cambiar estado</button>
                ${cot.estado==='pendiente'?`<button class="btn btn-sm btn-primary" data-action="enviar" data-id="${cot.id}">${iconoSvg('send')} Enviar</button>`:''}
            </div>
        </div>`;
    openModal('modalVerCotizacion');
}

// ─────────────────────────────────────────────────────────────────────────────
// FLUJO DE ESTADO CONTEXTUAL
// ─────────────────────────────────────────────────────────────────────────────
function cambiarEstadoFlujo(id) {
    const cot = cotizacionesData.find(c => c.id === id);
    if (!cot) return;

    const labels = {pendiente:'Pendiente',enviada:'Enviada',aceptada:'Aceptada',rechazada:'Rechazada',pagado:'Pagado'};
    const iconos = {pendiente:iconoSvg('hourglass-split'),enviada:iconoSvg('send'),aceptada:iconoSvg('check-circle-fill'),rechazada:iconoSvg('x-circle-fill'),pagado:iconoSvg('cash-coin')};
    const colores= {pendiente:'var(--warning-color)',enviada:'var(--info-color)',aceptada:'var(--success-color)',rechazada:'var(--danger-color)',pagado:'var(--success-color)'};

    const flujos = {
        pendiente: ['enviada','aceptada','rechazada'],
        enviada:   ['aceptada','rechazada','pendiente'],
        aceptada:  ['pagado','rechazada'],
        rechazada: ['pendiente'],
        pagado:    [],
    };
    const opciones = flujos[cot.estado] || [];

    document.getElementById('flujoModalTitle').textContent = `Actualizar estado — ${cot.folio || '#' + id}`;

    const botonesOpciones = opciones.map(e => `
        <button class="btn btn-outline" style="text-align:left;justify-content:flex-start;gap:8px;padding:12px 16px;"
                data-action="flujo" data-id="${id}" data-estado="${e}">
            <span>${iconos[e]||''}</span>
            <span style="font-weight:600;">${labels[e]}</span>
        </button>`).join('');

    document.getElementById('flujoModalBody').innerHTML = `
        <p style="margin-bottom:16px;font-size:13px;color:var(--text-muted);">
            Estado actual: <strong style="color:${colores[cot.estado]||'inherit'};">${iconos[cot.estado]||''} ${labels[cot.estado]||cot.estado}</strong>
        </p>
        ${opciones.length
            ? `<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">${botonesOpciones}</div>
               <div class="form-group">
                   <label class="form-label">Comentarios <span style="font-size:10px;color:var(--text-muted);">opcional</span></label>
                   <textarea class="form-textarea" id="flujoComentarios" rows="2" placeholder="Notas sobre el cambio..."></textarea>
               </div>`
            : `<p style="color:var(--text-muted);font-size:13px;">No hay transiciones disponibles desde el estado actual.</p>`
        }`;

    document.getElementById('flujoModalFooter').innerHTML =
        `<button class="btn btn-outline" data-close="modalEstadoFlujo">Cerrar</button>`;

    openModal('modalEstadoFlujo');
}

function aplicarEstadoFlujo(id, estado) {
    const coment = document.getElementById('flujoComentarios')?.value?.trim() || '';
    const fd = new FormData();
    fd.append('accion','cambiar_estado');
    fd.append('cotizacion_id', id);
    fd.append('estado', estado);
    fd.append('comentarios', coment);

    fetch('/cotizaciones', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const cot = cotizacionesData.find(c => c.id === id);
                if (cot) { cot.estado = estado; if (coment) cot.comentarios = coment; }
                actualizarBadgeEnTabla(id, estado);
                closeModal('modalEstadoFlujo');
                if (d.abrir_pago) {
                    document.getElementById('pagoCotId').value = id;
                    document.getElementById('pagoMetodo').value = '';
                    document.getElementById('pagoNotas').value  = '';
                    document.getElementById('pagoFile').value   = '';
                    document.getElementById('pagoFileInfo').style.display = 'none';
                    document.getElementById('pagoZone').style.display     = '';
                    document.querySelectorAll('[id^="mp_"]').forEach(el => { el.style.borderColor='var(--border-color)'; el.style.background=''; });
                    openModal('modalPago');
                } else {
                    showToast('Estado actualizado', 'success');
                }
            } else {
                showToast(d.msg || 'Error al actualizar estado', 'error');
            }
        })
        .catch(() => showToast('Error de conexión', 'error'));
}

function cambiarEstado(id) { cambiarEstadoFlujo(id); }

function guardarEstado() {
    const id     = document.getElementById('estadoCotizacionId').value;
    const estado = document.getElementById('nuevoEstado').value;
    const coment = document.getElementById('estadoComentarios').value.trim();
    const fd     = new FormData();
    fd.append('accion','cambiar_estado');
    fd.append('cotizacion_id', id);
    fd.append('estado', estado);
    fd.append('comentarios', coment);
    fetch('/cotizaciones', {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.ok) {
            const cot = cotizacionesData.find(c => c.id === parseInt(id));
            if (cot) { cot.estado = estado; cot.comentarios = coment; }
            actualizarBadgeEnTabla(parseInt(id), estado);
            closeModal('modalCambiarEstado');
            if (d.abrir_pago) {
                document.getElementById('pagoCotId').value = id;
                document.getElementById('pagoMetodo').value = '';
                document.getElementById('pagoNotas').value  = '';
                document.getElementById('pagoFile').value   = '';
                document.getElementById('pagoFileInfo').style.display = 'none';
                document.getElementById('pagoZone').style.display     = '';
                document.querySelectorAll('[id^="mp_"]').forEach(el => { el.style.borderColor='var(--border-color)'; el.style.background=''; });
                openModal('modalPago');
            } else {
                showToast('Estado actualizado', 'success');
            }
        } else {
            showToast(d.msg || 'Error al actualizar estado', 'error');
        }
    }).catch(() => showToast('Error de conexión', 'error'));
}

function actualizarBadgeEnTabla(id, estado) {
    const labels  = {pendiente:'Pendiente',enviada:'Enviada',aceptada:'Aceptada',rechazada:'Rechazada',pagado:'Pagado'};
    const classes = {pendiente:'badge-warning',enviada:'badge-info',aceptada:'badge-success',rechazada:'badge-danger',pagado:'badge-success'};
    document.querySelectorAll('#cotizacionesTableBody tr').forEach(tr => {
        if (parseInt(tr.dataset.id) === id) {
            const badge = tr.querySelector('.badge');
            if (badge) { badge.className='badge '+(classes[estado]||'badge-neutral'); badge.textContent=labels[estado]||estado; }
            tr.dataset.estado = estado;
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// ENVIAR COTIZACIÓN
// ─────────────────────────────────────────────────────────────────────────────
// Envía la cotización al cliente por correo (con el documento adjunto). El
// estado solo cambia si el servidor confirma que el correo salió.
function enviarCotizacion(id) {
    const cot = cotizacionesData.find(c => c.id === id);
    if (!cot) return;
    const correo = (cot.cliente_correo || '').trim();
    if (!correo) {
        showToast('El cliente no tiene un correo válido. Agrégalo en su ficha antes de enviar la cotización.', 'error');
        return;
    }
    if (!confirm(`¿Enviar la cotización por correo al cliente?\nCliente: ${cot.cliente_nombre}\nCorreo: ${correo}`)) return;

    // Puede haber dos botones (fila y modal de detalle): se bloquean ambos mientras se envía.
    const botones = document.querySelectorAll(`[data-action="enviar"][data-id="${id}"]`);
    botones.forEach(b => { b.disabled = true; });

    const fd = new FormData();
    fd.append('accion', 'enviar_cliente');
    fd.append('cotizacion_id', id);
    fd.append('csrf_token', CSRF_TOKEN);
    fetch('/cotizaciones', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                if (d.estado && d.estado !== cot.estado) {
                    cot.estado = d.estado;
                    actualizarBadgeEnTabla(id, d.estado);
                }
                // Salió, pero sin algún adjunto: aviso en vez de éxito, para que se note.
                const tipo = (d.omitidos && d.omitidos.length) ? 'warning' : 'success';
                showToast(escapeHtml(d.msg || 'Cotización enviada por correo.'), tipo);
            } else {
                showToast(escapeHtml(d.msg || 'No se pudo enviar la cotización.'), 'error');
            }
        })
        .catch(() => showToast('Error de conexión', 'error'))
        .finally(() => botones.forEach(b => { b.disabled = false; }));
}

// ─────────────────────────────────────────────────────────────────────────────
// ENVIAR AL COORDINADOR PARA COBRO
// ─────────────────────────────────────────────────────────────────────────────
function enviarCoordinador(id) {
    const cot = cotizacionesData.find(c => c.id === id);
    if (!cot || !confirm(`¿Enviar al coordinador para gestión de cobro?\nCliente: ${cot.cliente_nombre || ''}`)) return;
    const fd = new FormData();
    fd.append('accion', 'enviar_a_coordinador');
    fd.append('cotizacion_id', id);
    fetch('/cotizaciones', {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.ok) { showToast(iconoSvg('check-circle-fill') + ' Cotización enviada al coordinador', 'success'); setTimeout(() => location.reload(), 1200); }
        else showToast(d.msg || 'Error', 'error');
    }).catch(() => showToast('Error de conexión', 'error'));
}

// ─────────────────────────────────────────────────────────────────────────────
// EDITAR COTIZACIÓN — redirige al formulario con modo edición
// ─────────────────────────────────────────────────────────────────────────────
function editarCotizacion(id) {
    window.location.href = '/crear-cotizacion?cotizacion_id=' + id;
}

// ─────────────────────────────────────────────────────────────────────────────
// MODAL DOCUMENTOS
// ─────────────────────────────────────────────────────────────────────────────
function _fileCard(nombre_orig, nombre_arch, url_local, en_idrive, es_imagen, extraInfo, archivoId) {
    const badge = en_idrive
        ? `<span style="font-size:10px;background:#dcfce7;color:#16a34a;padding:2px 7px;border-radius:99px;font-weight:600;">${iconoSvg('cloud')} iDrive</span>`
        : `<span style="font-size:10px;background:var(--bg-secondary);color:var(--text-muted);padding:2px 7px;border-radius:99px;">Local</span>`;
    const thumb = (es_imagen && url_local)
        ? `<div style="height:90px;border-radius:6px;overflow:hidden;margin-bottom:8px;cursor:pointer;" data-action="openFile" data-url="${url_local}"><img src="${url_local}" style="width:100%;height:100%;object-fit:cover;" loading="lazy"></div>`
        : `<div style="height:90px;background:var(--bg-secondary);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:36px;margin-bottom:8px;">${url_local?iconoSvg('file-earmark-text'):iconoSvg('paperclip')}</div>`;
    const btn = url_local
        ? `<a href="${url_local}" target="_blank" class="btn btn-sm btn-outline" style="flex:1;text-align:center;font-size:11px;">${iconoSvg('eye')} Ver</a>`
        : `<span style="font-size:11px;color:var(--text-muted);flex:1;text-align:center;padding:5px 0;">${en_idrive?iconoSvg('cloud')+' Solo en iDrive':'Sin archivo'}</span>`;
    const delBtn = archivoId
        ? `<button class="btn btn-sm" style="padding:3px 7px;font-size:10px;color:#ef4444;background:none;border:1px solid #ef4444;border-radius:4px;" data-action="eliminarArchivo" data-archivo-id="${archivoId}" title="Eliminar" aria-label="Eliminar">${iconoSvg('trash')}</button>`
        : '';
    return `<div style="background:var(--bg-secondary);border-radius:9px;padding:12px;display:flex;flex-direction:column;" ${archivoId?`data-archivo-id="${archivoId}"`:''}>${thumb}<div style="font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;font-weight:600;" title="${escapeHtml(nombre_orig||'')}">${escapeHtml((nombre_orig||'').substring(0,28))}</div>${extraInfo?`<div style="font-size:10px;color:var(--text-muted);margin-bottom:6px;">${escapeHtml(extraInfo)}</div>`:''}<div style="display:flex;gap:6px;align-items:center;">${badge}${btn}${delBtn}</div></div>`;
}

function _sectionTitle(icono, title, count) {
    return `<div style="display:flex;align-items:center;gap:8px;margin:18px 0 10px;border-bottom:2px solid var(--accent-color);padding-bottom:5px;"><span style="font-size:16px;">${iconoSvg(icono)}</span><span style="font-size:13px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.5px;">${title}</span><span style="font-size:11px;background:var(--bg-secondary);color:var(--text-muted);padding:2px 8px;border-radius:99px;">${count}</span></div>`;
}

function abrirModalDocumentos(cotId) {
    document.getElementById('modalDocsFolio').textContent = '...';
    document.getElementById('modalDocsContent').innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);">' + iconoSvg('hourglass-split') + ' Cargando documentos...</div>';
    openModal('modalDocumentos');

    const fd = new FormData();
    fd.append('accion','get_documentos');
    fd.append('cotizacion_id', cotId);

    fetch('/cotizaciones', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (!d.ok) {
                document.getElementById('modalDocsContent').innerHTML = '<div style="text-align:center;padding:40px;color:var(--danger-color);">Error al cargar los documentos.</div>';
                return;
            }
            document.getElementById('modalDocsFolio').textContent = d.folio || '#' + cotId;

            let html = '';

            if (d.docs && d.docs.length > 0) {
                html += _sectionTitle('paperclip', 'Documentos',d.docs.length);
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:12px;">';
                d.docs.forEach(doc => {
                    html += _fileCard(doc.nombre_orig, doc.nombre_arch, doc.url_local, doc.en_idrive, doc.es_imagen, doc.tipo_label, doc.id);
                });
                html += '</div>';
            }

            html += _sectionTitle('cash-coin', 'Comprobantes de Pago',d.pagos?.length || 0);
            if (d.pagos && d.pagos.length > 0) {
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:12px;">';
                d.pagos.forEach(p => {
                    const info = p.metodo_label + (p.monto > 0 ? ` · $${p.monto.toLocaleString('es-MX',{minimumFractionDigits:2})}` : '') + (p.fecha_pago ? ` · ${p.fecha_pago}` : '');
                    html += _fileCard(p.nombre_arch || 'Comprobante', p.nombre_arch, p.url_local, false, p.es_imagen, info, null);
                });
                html += '</div>';
            } else {
                html += '<p style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Sin comprobantes registrados.</p>';
            }

            html += _sectionTitle('clipboard', 'Pólizas',d.polizas?.length || 0);
            if (d.polizas && d.polizas.length > 0) {
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:12px;">';
                d.polizas.forEach(p => {
                    const info = (p.compania||'') + (p.numero_poliza ? ` · ${p.numero_poliza}` : '') + (p.fecha_fin ? ` · Vence: ${p.fecha_fin}` : '');
                    html += _fileCard(p.nombre_orig, p.nombre_arch, p.url_local, p.en_idrive, p.es_imagen, info, null);
                });
                html += '</div>';
            } else {
                html += '<p style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Sin pólizas vinculadas.</p>';
            }

            if (!html) {
                html = '<div style="text-align:center;padding:48px;color:var(--text-muted);"><div style="font-size:48px;margin-bottom:12px;">' + iconoSvg('inbox') + '</div><div style="font-size:15px;font-weight:600;">Sin archivos</div></div>';
            }
            document.getElementById('modalDocsContent').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('modalDocsContent').innerHTML = '<div style="text-align:center;padding:40px;color:var(--danger-color);">Error de conexión.</div>';
        });
}

function eliminarArchivo(archivoId, btn) {
    if (!confirm('¿Eliminar este archivo? No se puede deshacer.')) return;
    btn.disabled = true;
    const fd = new FormData();
    fd.append('accion', 'eliminar_archivo');
    fd.append('archivo_id', archivoId);
    fetch('/cotizaciones', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                showToast('Archivo eliminado', 'success');
                const card = btn.closest('[data-archivo-id]');
                if (card) card.remove();
            } else {
                btn.disabled = false;
                showToast(d.msg || 'Error al eliminar', 'error');
            }
        })
        .catch(() => { btn.disabled = false; showToast('Error de conexión', 'error'); });
}

// ─────────────────────────────────────────────────────────────────────────────
// PAGO
// ─────────────────────────────────────────────────────────────────────────────
function selMP(m) {
    document.querySelectorAll('[id^="mp_"]').forEach(el => { el.style.borderColor='var(--border-color)'; el.style.background=''; });
    const card = document.getElementById('mp_' + m);
    if (card) { card.style.borderColor='var(--accent-color)'; card.style.background='var(--info-light)'; }
    document.getElementById('pagoMetodo').value = m;
}
function onPagoFile(inp) {
    if (!inp.files[0]) return;
    document.getElementById('pagoFileName').textContent = inp.files[0].name;
    document.getElementById('pagoFileInfo').style.display = 'flex';
    document.getElementById('pagoZone').style.display = 'none';
}
function quitarPago() {
    document.getElementById('pagoFile').value = '';
    document.getElementById('pagoFileInfo').style.display = 'none';
    document.getElementById('pagoZone').style.display = '';
}
function guardarPago() {
    const id     = document.getElementById('pagoCotId').value;
    const metodo = document.getElementById('pagoMetodo').value;
    if (!metodo) { showToast('Selecciona el método de pago', 'error'); return; }
    const fd = new FormData();
    fd.append('accion','registrar_pago'); fd.append('cotizacion_id', id);
    fd.append('metodo_pago', metodo);     fd.append('fecha_pago', document.getElementById('pagoFecha').value);
    fd.append('monto', document.getElementById('pagoMonto').value);
    fd.append('notas', document.getElementById('pagoNotas').value);
    const file = document.getElementById('pagoFile').files[0];
    if (file) fd.append('comprobante', file);
    fetch('/cotizaciones', {method:'POST', body:fd}).then(r=>r.json()).then(d => {
        if (d.ok) {
            actualizarBadgeEnTabla(parseInt(id), 'pagado');
            showToast(iconoSvg('cash-coin') + ' Pago registrado', 'success');
            closeModal('modalPago');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(d.msg || 'Error', 'error');
        }
    }).catch(() => showToast('Error de conexión', 'error'));
}
</script>


<script src="/js/crm-events.js" nonce="<?= $cspNonce ?>"></script>
<script src="/js/modales.js"    nonce="<?= $cspNonce ?>"></script>
</body>
</html>