<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{ClientModel, CitaModel, CotizacionModel, HistorialModel};

SessionManager::requireAuth('/login');

// Capturar excepciones PHP y devolverlas como JSON en AJAX o HTML visible en GET
set_exception_handler(function(\Throwable $e) {
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    if ($isPost) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode([
            'ok'   => false,
            'msg'  => '[PHP] ' . $e->getMessage(),
            'file' => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    } else {
        echo '<div style="background:#fee2e2;color:#7f1d1d;padding:20px;font-family:monospace;border-radius:8px;margin:20px;">
              <b>Error PHP:</b> ' . htmlspecialchars($e->getMessage()) . '<br>
              <small>' . htmlspecialchars(basename($e->getFile()) . ':' . $e->getLine()) . '</small></div>';
    }
    exit;
});

$agenteId               = SessionManager::getAgentId();
$agenteData             = SessionManager::getAgentData();
$rolAgente              = $agenteData['rol'] ?? 'asesor';

// Le damos el poder al asesor (y a todos) de manejar las pólizas
$puedeGestionarPolizas  = true; 

$clienteId       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$clienteId) { header('Location: /clientes'); exit; }

$clienteModel    = new ClientModel();
$citaModel       = new CitaModel();
$cotizacionModel = new CotizacionModel();
$historialModel  = new HistorialModel();

$MARCAS_VEHICULO = [
    'ACURA','ALFA ROMEO','AUDI','BMW','BUICK','CADILLAC','CHEVROLET','CHRYSLER',
    'CUPRA','DACIA','DODGE','DS','FERRARI','FIAT','FORD','GMC','HONDA','HYUNDAI',
    'INFINITI','JAGUAR','JEEP','KIA','LAMBORGHINI','LAND ROVER','LEXUS','LINCOLN',
    'LOTUS','MASERATI','MAZDA','MERCEDES-BENZ','MINI','MITSUBISHI','NISSAN','OPEL',
    'PEUGEOT','PORSCHE','RAM','RENAULT','ROLLS-ROYCE','SEAT','SKODA','SMART',
    'SUBARU','SUZUKI','TESLA','TOYOTA','VOLKSWAGEN','VOLVO',
    // Marcas populares en México
    'BAIC','BYD','CHANGAN','CHIREY','GREAT WALL','HAVAL','JAC','JETOUR','MG',
    'OTRA',
];

// Cargar modelos de pólizas y notificaciones (si existen)
$polizaModel = null;
try {
    if (class_exists('App\Models\PolizaModel')) {
        $polizaModel = new \App\Models\PolizaModel();
    }
} catch (\Throwable $e) { $polizaModel = null; }

// Helper iDrive (stub; implementar con AWS SDK cuando esté configurado)
if (!function_exists('idriveUpload')) {
    function idriveUpload(string $local, string $key): ?string { return null; }
}

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    if ($_POST['accion'] === 'nota') {
        $texto = trim($_POST['texto'] ?? '');
        if (!$texto) { echo json_encode(['ok'=>false,'msg'=>'Texto vacío.']); exit; }
        $clienteModel->addNota($clienteId, $agenteId, $texto);
        $historialModel->registrar($clienteId, $agenteId, 'nota', 'Nota agregada', $texto);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($_POST['accion'] === 'cita') {
        $tipo=$_POST['tipo']??'llamada'; $fecha=$_POST['fecha']??''; $hora=$_POST['hora']??'';
        $com=$_POST['comentarios']??''; $enlace=$_POST['enlace_meet']??'';
        if (!$fecha||!$hora) { echo json_encode(['ok'=>false,'msg'=>'Indica fecha y hora.']); exit; }
        // Límite: máx 20 citas por cliente (anti-spam)
        $dbCitaChk = \Database\Connection::getConnection();
        $stCitaCount = $dbCitaChk->prepare('SELECT COUNT(*) FROM citas WHERE cliente_id = ?');
        $stCitaCount->execute([$clienteId]);
        if ((int)$stCitaCount->fetchColumn() >= 20) {
            echo json_encode(['ok'=>false,'msg'=>'Este cliente ya tiene 20 citas registradas (límite máximo).']); exit;
        }
        if ($tipo==='videollamada'&&$enlace) $com=($com?$com."\n":'').'🎥 Enlace: '.$enlace;
        $citaModel->crear($clienteId,$agenteId,$tipo,$fecha,$hora,$com,SessionManager::getRamo());
        $historialModel->registrar($clienteId,$agenteId,'cita','Cita programada — '.ucfirst($tipo),"$fecha a las $hora");
        echo json_encode(['ok'=>true]); exit;
    }

    if ($_POST['accion'] === 'solicitar_renovacion') {
        $polizaId   = (int)($_POST['poliza_id'] ?? 0);
        $comentarios = trim($_POST['comentarios'] ?? '');
        if (!$polizaId) { echo json_encode(['ok'=>false,'msg'=>'Póliza no indicada.']); exit; }
        $db = \Database\Connection::getConnection();
        // Verificar que la póliza pertenece al cliente
        $stPol = $db->prepare('SELECT id, numero_poliza, compania, fecha_fin FROM polizas WHERE id=? AND cliente_id=?');
        $stPol->execute([$polizaId, $clienteId]);
        $pol = $stPol->fetch(\PDO::FETCH_ASSOC);
        if (!$pol) { echo json_encode(['ok'=>false,'msg'=>'Póliza no encontrada.']); exit; }
        // Anti-spam: bloquear si ya hay solicitud pendiente para esta póliza (últimos 30 días)
        $stDup = $db->prepare(
            "SELECT COUNT(*) FROM historial_cliente
             WHERE cliente_id = ? AND titulo = '🔄 Solicitud de renovación'
               AND descripcion LIKE ?
               AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stDup->execute([$clienteId, "%#{$pol['numero_poliza']}%"]);
        if ((int)$stDup->fetchColumn() > 0) {
            echo json_encode(['ok'=>false,'msg'=>'Ya hay una solicitud de renovación pendiente para esta póliza. El coordinador la gestionará pronto.']); exit;
        }
        // Registrar solicitud de renovación como nota en historial
        $detalle = "Renovación solicitada — Póliza #{$pol['numero_poliza']} ({$pol['compania']})";
        if ($comentarios) $detalle .= " | Nota: {$comentarios}";
        $historialModel->registrar($clienteId, $agenteId, 'nota', '🔄 Solicitud de renovación', $detalle);
        // Marcar póliza como por_vencer para que aparezca en alertas del coordinador
        $fechaAhora = date('Y-m-d H:i');
        $notaRenovacion = "\n[{$fechaAhora}] Renovación solicitada por asesor.";
        $db->prepare(
            "UPDATE polizas SET estado='por_vencer', notas=CONCAT(IFNULL(notas,''), ?) WHERE id=?"
        )->execute([$notaRenovacion, $polizaId]);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($_POST['accion'] === 'cambiar_estado') {
        $nuevo=trim($_POST['estado']??'');
        $validos=['nuevo','sin_contactar','contactado','con_cotizacion','cerrado','perdido'];
        if (!in_array($nuevo,$validos,true)) { echo json_encode(['ok'=>false,'msg'=>'Estado inválido.']); exit; }
        $clienteModel->updateEstado($clienteId,$agenteId,$nuevo);
        $historialModel->registrar($clienteId,$agenteId,'cambio_estado',"Estado cambiado a '{$nuevo}'",'');
        echo json_encode(['ok'=>true]); exit;
    }

    if ($_POST['accion'] === 'subir_poliza' && $polizaModel) {
        // Regla de negocio: el asesor NO puede subir póliza final.
        // Solo coordinador/admin/superadmin pueden cerrar el caso subiendo la póliza.
        // SessionManager::requireRole('coordinador', '/login');

        if (empty($_FILES['archivo_poliza']['name'])||$_FILES['archivo_poliza']['error']!==UPLOAD_ERR_OK) {
            echo json_encode(['ok'=>false,'msg'=>'Archivo no recibido.']); exit;
        }
        $file=$_FILES['archivo_poliza'];
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['pdf','jpg','jpeg','png'],true)||$file['size']>20*1024*1024) {
            echo json_encode(['ok'=>false,'msg'=>'Formato inválido o mayor a 20MB.']); exit;
        }
        $uploadDir=dirname(__DIR__,3)."/uploads/polizas/{$clienteId}/";
        if (!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
        $nombreArch='poliza_'.time().'.'.$ext;
        $rutaRel="uploads/polizas/{$clienteId}/{$nombreArch}";
        if (!move_uploaded_file($file['tmp_name'],$uploadDir.$nombreArch)) {
            echo json_encode(['ok'=>false,'msg'=>'Error al guardar.']); exit;
        }
        $cotId=(int)($_POST['cotizacion_id']??0)?:null;
        $polizaId=$polizaModel->crear([
            'cliente_id'=>$clienteId,'cotizacion_id'=>$cotId,'agente_id'=>$agenteId,
            'nombre_archivo'=>$nombreArch,'nombre_original'=>$file['name'],'ruta'=>$rutaRel,
            'compania'=>trim($_POST['compania']??''),'numero_poliza'=>trim($_POST['numero_poliza']??''),
            'fecha_inicio'=>trim($_POST['fecha_inicio']??'')?:null,
            'fecha_fin'=>trim($_POST['fecha_fin']??'')?:null,
            'frecuencia_renovacion'=>trim($_POST['frecuencia_renovacion']??'anual'),
            'notas'=>trim($_POST['notas']??''),
        ]);
        $iKey=null;
        try { $iKey=idriveUpload($uploadDir.$nombreArch,"clientes/{$clienteId}/polizas/{$nombreArch}");
              if ($iKey) $polizaModel->marcarIdrive($polizaId,$iKey); } catch(\Throwable $e){}
        $historialModel->registrar($clienteId,$agenteId,'nota','Póliza subida',$file['name']);
        
        // --- INICIO DE FUSIÓN: ENVIAR AUTOMÁTICAMENTE AL COORDINADOR ---
        if ($cotId) {
            $db = \Database\Connection::getConnection();
            // Marcar cotización como "en_proceso" para que aparezca en el panel del coordinador
            $db->prepare("UPDATE cotizaciones SET emision_estado = 'en_proceso' WHERE id = ?")->execute([$cotId]);
            
            // Buscar datos para notificar a los coordinadores
            $stC = $db->prepare('SELECT c.folio, c.compania, cl.nombre, cl.apellido_paterno FROM cotizaciones c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
            $stC->execute([$cotId]);
            if ($datCot = $stC->fetch(\PDO::FETCH_ASSOC)) {
                $coords = $db->query("SELECT id FROM agentes WHERE rol IN ('coordinador','superadmin') AND activo = 1")->fetchAll(\PDO::FETCH_COLUMN);
                $folio = $datCot['folio'] ?? '#'.$cotId;
                $clienteNombre = trim(($datCot['nombre'] ?? '') . ' ' . ($datCot['apellido_paterno'] ?? ''));
                $stmt = $db->prepare("INSERT INTO notificaciones (agente_id, tipo, titulo, mensaje, urgente, color, url_accion, leida, creada_en) VALUES (?,?,?,?,1,'blue',?,0,NOW())");
                
                foreach ($coords as $cid) {
                    $stmt->execute([
                        $cid, 'emision', 
                        "📤 Expediente listo y Póliza subida — {$folio}", 
                        "El asesor completó el expediente de {$clienteNombre} ({$datCot['compania']}) y adjuntó la póliza. Pendiente de pago.", 
                        "/admin?tab=emision"
                    ]);
                }
                
                // Actualizar el historial del cliente
                $historialModel->registrar($clienteId, $agenteId, 'cotizacion', 
                    "📤 Expediente enviado a revisión — {$folio}", 
                    "Póliza subida. Pendiente confirmación de pago por el coordinador."
                );
            }
        }
        // --- FIN DE FUSIÓN ---

        echo json_encode(['ok'=>true,'id'=>$polizaId,'en_idrive'=>(bool)$iKey]); exit;
    }

    if ($_POST['accion'] === 'editar_poliza' && $polizaModel) {
        $pid=(int)($_POST['poliza_id']??0);
        if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        $ok=$polizaModel->actualizar($pid,$agenteId,[
            'compania'=>trim($_POST['compania']??''),
            'numero_poliza'=>trim($_POST['numero_poliza']??''),
            'fecha_inicio'=>trim($_POST['fecha_inicio']??'')?:null,
            'fecha_fin'=>trim($_POST['fecha_fin']??'')?:null,
            'frecuencia_renovacion'=>trim($_POST['frecuencia_renovacion']??'anual'),
            'estado'=>trim($_POST['estado']??'vigente'),
            'notas'=>trim($_POST['notas']??''),
        ]);
        echo json_encode(['ok'=>$ok]); exit;
    }

    if ($_POST['accion'] === 'eliminar_poliza' && $polizaModel) {
        $pid=(int)($_POST['poliza_id']??0);
        echo json_encode(['ok'=>$pid?$polizaModel->eliminar($pid,$agenteId):false]); exit;
    }

    if ($_POST['accion'] === 'eliminar_archivo_cot') {
        $aid=(int)($_POST['archivo_id']??0);
        if (!$aid) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        try {
            $db=\Database\Connection::getConnection();
            $db->prepare('UPDATE cotizacion_archivos SET eliminado_en=NOW() WHERE id=? AND agente_id=?')
               ->execute([$aid,$agenteId]);
            echo json_encode(['ok'=>true]); exit;
        } catch(\Throwable $e){ echo json_encode(['ok'=>false,'msg'=>'Error.']); exit; }
    }

    // ── Confirmar pago de cotización ──────────────────────────────────────────
    // if ($_POST['accion'] === 'confirmar_pago') {
    //     $cotId  = (int)($_POST['cotizacion_id'] ?? 0);
    //     $metodo = trim($_POST['metodo_pago'] ?? '');
    //     $notas  = trim($_POST['notas_pago'] ?? '');
    //     $metodosValidos = ['spei','contado','meses_sin_intereses'];

    //     if (!$cotId || !in_array($metodo, $metodosValidos, true)) {
    //         echo json_encode(['ok'=>false,'msg'=>'Datos incompletos.']); exit;
    //     }

    //     // Verificar que la cotización pertenece al agente
    //     $db = \Database\Connection::getConnection();
    //     $stCot = $db->prepare('SELECT id, estado, precio, folio FROM cotizaciones WHERE id=? AND agente_id=?');
    //     $stCot->execute([$cotId, $agenteId]);
    //     $cot = $stCot->fetch(\PDO::FETCH_ASSOC);
    //     if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'Cotización no encontrada.']); exit; }
    //     if ($cot['estado'] === 'pagado') { echo json_encode(['ok'=>false,'msg'=>'Esta cotización ya está marcada como pagada.']); exit; }

    //     // Si el método requiere comprobante y no lo subieron
    //     $requiereComprobante = in_array($metodo, ['spei','meses_sin_intereses'], true);
    //     $rutaPago = null;
    //     $nombreArch = null;
    //     $nombreOrig = null;

    //     if ($requiereComprobante) {
    //         if (empty($_FILES['comprobante_pago']['name']) || $_FILES['comprobante_pago']['error'] !== UPLOAD_ERR_OK) {
    //             echo json_encode(['ok'=>false,'msg'=>'El comprobante de pago es obligatorio para transferencia o MSI.']); exit;
    //         }
    //         $file = $_FILES['comprobante_pago'];
    //         $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    //         if (!in_array($ext, ['pdf','jpg','jpeg','png'], true) || $file['size'] > 15*1024*1024) {
    //             echo json_encode(['ok'=>false,'msg'=>'Formato inválido o mayor a 15MB.']); exit;
    //         }
    //         $uploadDir = dirname(__DIR__,3) . "/uploads/cotizaciones/{$cotId}/";
    //         if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    //         $nombreArch = 'comprobante_pago_' . time() . '.' . $ext;
    //         $rutaPago   = "uploads/cotizaciones/{$cotId}/{$nombreArch}";
    //         $nombreOrig = $file['name'];
    //         if (!move_uploaded_file($file['tmp_name'], $uploadDir . $nombreArch)) {
    //             echo json_encode(['ok'=>false,'msg'=>'Error al guardar el comprobante.']); exit;
    //         }
    //     }

    //     try {
    //         $db->beginTransaction();
    //         // Actualizar estado de cotización a pagado
    //         $db->prepare('UPDATE cotizaciones SET estado=?, metodo_pago=? WHERE id=?')
    //            ->execute(['pagado', $metodo, $cotId]);

    //         if ($nombreArch !== null && $rutaPago !== null && $requiereComprobante) {
    //             $cotizacionModel->adjuntarArchivo(
    //                 $cotId,
    //                 $agenteId,
    //                 'comprobante_pago',
    //                 $nombreOrig ?: $nombreArch,
    //                 $nombreArch,
    //                 $rutaPago
    //             );
    //         }

    //         // Insertar en cotizacion_pagos
    //         $db->prepare(
    //             'INSERT INTO cotizacion_pagos (cotizacion_id, agente_id, metodo_pago, notas, ruta, nombre_archivo, nombre_original, fecha_pago, creado_en)
    //              VALUES (?,?,?,?,?,?,?,CURDATE(),NOW())'
    //         )->execute([$cotId, $agenteId, $metodo, $notas, $rutaPago, $nombreArch, $nombreOrig]);
    //         // Historial
    //         $historialModel->registrar($clienteId, $agenteId, 'venta',
    //             'Pago confirmado — Folio: ' . ($cot['folio'] ?? '#'.$cotId),
    //             ucfirst(str_replace('_',' ',$metodo)) . ($notas ? " · $notas" : '')
    //         );
    //         // Notificación importante al asesor confirmando el pago registrado
    //         try {
    //             $db->prepare(
    //                 "INSERT INTO notificaciones (agente_id, tipo, titulo, mensaje, urgente, color, url_accion, leida, creada_en)
    //                  VALUES (?,?,?,?,1,'green',?,0,NOW())"
    //             )->execute([
    //                 $agenteId,
    //                 'pago',
    //                 '✅ Pago confirmado — ' . ($cot['folio'] ?? '#'.$cotId),
    //                 'El pago de la cotización ' . ($cot['folio'] ?? '#'.$cotId) . ' ha sido registrado correctamente. Método: ' . ucfirst(str_replace('_',' ',$metodo)) . '. Recuerda contactar al cliente para gestionar su renovación cuando corresponda.',
    //                 '/cliente/' . $clienteId,
    //             ]);
    //         } catch (\Throwable $ne) { error_log('[notif_pago] ' . $ne->getMessage()); }
    //         $db->commit();
    //         echo json_encode(['ok'=>true]); exit;
    //     } catch(\Throwable $e) {
    //         $db->rollBack();
    //         error_log('confirmar_pago error: ' . $e->getMessage());
    //         echo json_encode(['ok'=>false,'msg'=>'Error al registrar el pago.']); exit;
    //     }
    // }

    // ── Editar datos del cliente ───────────────────────────────────────────────
    if ($_POST['accion'] === 'editar_cliente') {
        $soloVehiculo = !empty($_POST['_solo_vehiculo']);

        if ($soloVehiculo) {
            // ── Guardar solo campos de vehículo ───────────────────────────────
            $ramo = $cliente['ramo'] ?? 'autos';
            if ($ramo !== 'autos') {
                echo json_encode(['ok'=>false,'msg'=>'Solo disponible para ramo autos.']); exit;
            }
            $campos = [];
            // Campos directos en tabla clientes (autos)
            if (($_POST['marca']         ?? '') !== '') $campos['marca']         = trim($_POST['marca']);
            if (($_POST['modelo']        ?? '') !== '') $campos['modelo']        = trim($_POST['modelo']); // submarca->modelo
            if (($_POST['anio_vehiculo'] ?? '') !== '') $campos['anio_vehiculo'] = (int)$_POST['anio_vehiculo'] ?: null;
            if (($_POST['tipo_vehiculo'] ?? '') !== '') $campos['tipo_vehiculo'] = trim($_POST['tipo_vehiculo']);
            try {
                $db = \Database\Connection::getConnection();
                // Leer extra actual (color, placas, vin, rines, gps, uso) guardado en detalle_adaptaciones como JSON
                $stAct = $db->prepare('SELECT detalle_adaptaciones FROM clientes WHERE id = ? AND agente_id = ?');
                $stAct->execute([$clienteId, $agenteId]);
                $rowAct = $stAct->fetch(\PDO::FETCH_ASSOC);
                $prevExtra = [];
                if (!empty($rowAct['detalle_adaptaciones'])) {
                    $decoded = @json_decode($rowAct['detalle_adaptaciones'], true);
                    if (is_array($decoded)) $prevExtra = $decoded;
                }
                $extra = [];
                foreach (['color','placas','vin','rines_lujo','gps','uso_auto'] as $key) {
                    if (($_POST[$key] ?? '') !== '') $extra[$key] = trim($_POST[$key]);
                }
                if (!empty($extra)) {
                    $merged = array_merge($prevExtra, $extra);
                    $campos['detalle_adaptaciones'] = json_encode($merged, JSON_UNESCAPED_UNICODE);
                    $campos['tiene_adaptaciones']   = !empty($merged) ? 1 : 0;
                }
                if (empty($campos)) { echo json_encode(['ok'=>true]); exit; }
                $sets = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($campos)));
                $vals = array_values($campos);
                $vals[] = $clienteId;
                $vals[] = $agenteId;
                $db->prepare("UPDATE clientes SET {$sets} WHERE id = ? AND agente_id = ?")
                   ->execute($vals);
                echo json_encode(['ok'=>true]); exit;
            } catch(\Throwable $e) {
                echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]); exit;
            }
        }

        $campos = [
            'nombre'           => trim($_POST['nombre']           ?? ''),
            'apellido_paterno' => trim($_POST['apellido_paterno'] ?? ''),
            'apellido_materno' => trim($_POST['apellido_materno'] ?? ''),
            'correo'           => trim($_POST['correo']           ?? ''),
            'telefono'         => trim($_POST['telefono']         ?? ''),
            'fecha_nacimiento' => trim($_POST['fecha_nacimiento'] ?? ''),
            'sexo'             => trim($_POST['sexo']             ?? ''),
            'estado_civil'     => trim($_POST['estado_civil']     ?? ''),
            'calle'            => trim($_POST['calle']            ?? ''),
            'colonia'          => trim($_POST['colonia']          ?? ''),
            'ciudad'           => trim($_POST['ciudad']           ?? ''),
            'estado'           => trim($_POST['estado']           ?? ''),
            'codigo_postal'    => trim($_POST['codigo_postal']    ?? ''),
        ];

        if (!$campos['nombre'] || !$campos['apellido_paterno'] || !$campos['correo']) {
            echo json_encode(['ok'=>false,'msg'=>'Nombre, apellido y correo son obligatorios.']); exit;
        }
        if (!filter_var($campos['correo'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok'=>false,'msg'=>'Correo inválido.']); exit;
        }

        // Campos extra según ramo
        $ramo = $cliente['ramo'] ?? 'autos';
        if ($ramo === 'autos') {
            $campos['marca']         = trim($_POST['marca']        ?? '');
            $campos['modelo']        = trim($_POST['modelo']       ?? '');
            $campos['anio_vehiculo'] = (int)($_POST['anio_vehiculo'] ?? 0) ?: null;
            $campos['tipo_vehiculo'] = trim($_POST['tipo_vehiculo'] ?? '');
            $campos['asegurado']     = trim($_POST['asegurado']    ?? 'no');
            $campos['tiempo_compra'] = trim($_POST['tiempo_compra'] ?? '');
        } elseif (in_array($ramo, ['vida','gastos_medicos'], true)) {
            $campos['sexo']            = trim($_POST['sexo'] ?? '');
            $campos['fecha_nacimiento']= trim($_POST['fecha_nacimiento'] ?? '');
            $campos['asegurado']       = trim($_POST['asegurado'] ?? 'no');
        } elseif ($ramo === 'empresas') {
            $campos['giro_empresa']      = trim($_POST['giro_empresa']     ?? '');
            $campos['num_empleados']     = (int)($_POST['num_empleados']   ?? 0) ?: null;
            $campos['facturacion_anual'] = (float)($_POST['facturacion_anual'] ?? 0) ?: null;
        }

        try {
            $db = \Database\Connection::getConnection();
            $sets = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($campos)));
            $vals = array_values($campos);
            $vals[] = $clienteId;
            $vals[] = $agenteId;
            $db->prepare("UPDATE clientes SET {$sets} WHERE id = ? AND agente_id = ?")
               ->execute($vals);
            $historialModel->registrar($clienteId, $agenteId, 'nota', 'Datos del cliente actualizados', '');
            echo json_encode(['ok'=>true]); exit;
        } catch(\Throwable $e) {
            error_log('editar_cliente error: ' . $e->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'Error al guardar.']); exit;
        }
    }

    // ── Notificar al coordinador que el expediente está listo ─────────────────
    if ($_POST['accion'] === 'enviar_coordinador') {
        $cotId = (int)($_POST['cot_id'] ?? 0);
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'Cotización inválida.']); exit; }
        try {
            $db = \Database\Connection::getConnection();
            // Verificar que la cot pertenece al asesor
            $stC = $db->prepare('SELECT c.id, c.folio, c.compania, c.cliente_id, cl.nombre, cl.apellido_paterno
                                  FROM cotizaciones c JOIN clientes cl ON cl.id = c.cliente_id
                                  WHERE c.id = ? AND c.agente_id = ?');
            $stC->execute([$cotId, $agenteId]);
            $datCot = $stC->fetch(\PDO::FETCH_ASSOC);
            if (!$datCot) { echo json_encode(['ok'=>false,'msg'=>'Cotización no encontrada.']); exit; }
            // Cambiar estado a 'en_proceso' para que le aparezca al coordinador en su panel
            $db->prepare("UPDATE cotizaciones SET estado='en_proceso' WHERE id=?")->execute([$cotId]);
            // Notificar a todos los coordinadores/admins
            $coords = $db->query("SELECT id FROM agentes WHERE rol IN ('coordinador','superadmin') AND activo = 1")->fetchAll(\PDO::FETCH_COLUMN);
            $folio = $datCot['folio'] ?? '#'.$cotId;
            $clienteNombre = trim(($datCot['nombre'] ?? '') . ' ' . ($datCot['apellido_paterno'] ?? ''));
            $stmt = $db->prepare("INSERT INTO notificaciones (agente_id, tipo, titulo, mensaje, urgente, color, url_accion, leida, creada_en)
                                   VALUES (?,?,?,?,1,'blue',?,0,NOW())");
            foreach ($coords as $cid) {
                $stmt->execute([
                    $cid, 'emision',
                    "📤 Expediente listo — {$folio}",
                    "El asesor envió el expediente completo de {$clienteNombre} ({$datCot['compania']}) para emisión y cobro.",
                    "/admin?tab=emision",
                ]);
            }
            // Registrar en historial del cliente
            $historialModel->registrar($clienteId, $agenteId, 'cotizacion',
                "📤 Expediente enviado al coordinador — {$folio}",
                "Todos los documentos requeridos están completos. Pendiente de cobro por coordinador."
            );
            echo json_encode(['ok'=>true,'msg'=>'Expediente enviado al coordinador. Se notificará cuando el pago sea confirmado.']); exit;
        } catch(\Throwable $e) {
            echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage()]); exit;
        }
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
}

// ── Datos ─────────────────────────────────────────────────────────────────────
$cliente=$clienteModel->getCompleto($clienteId,$agenteId);
if (!$cliente) { header('Location: /clientes'); exit; }

$cliente['asegurado']       =($cliente['asegurado']==='si');
$cliente['recien_comprado'] =($cliente['recien_comprado']==='si');

// ── Vehículo (tabla vehiculos no tiene cliente_id; usar campos del cliente) ──
$vehiculo = [];
try {
    $dbVeh = \Database\Connection::getConnection();
    // Intentar tabla vehiculos si existe con cliente_id
    $stVeh = $dbVeh->prepare('SELECT * FROM vehiculos WHERE id = ? LIMIT 1');
    $stVeh->execute([0]); // stub — tabla sin FK a clientes
    $vehiculo = [];
} catch (\Throwable $e) { $vehiculo = []; }
// Mapear desde $cliente (campos autos guardados en clientes)
if (($cliente['ramo'] ?? 'autos') === 'autos') {
    // Leer campos extra (color, placas, vin, rines_lujo, gps, uso_auto) desde detalle_adaptaciones JSON
    $_extra = [];
    if (!empty($cliente['detalle_adaptaciones'])) {
        $_decoded = @json_decode($cliente['detalle_adaptaciones'], true);
        if (is_array($_decoded)) $_extra = $_decoded;
    }
    $vehiculo = [
        'id'                  => 0,
        'marca'               => $cliente['marca']         ?? '',
        'submarca'            => $cliente['modelo']        ?? '',    // modelo en BD = submarca UI
        'version'             => $_extra['version']        ?? '',
        'modelo'              => $cliente['anio_vehiculo'] ?? '',    // anio_vehiculo = Modelo(año)
        'color'               => $_extra['color']          ?? '',
        'placas'              => $_extra['placas']         ?? '',
        'vin'                 => $_extra['vin']            ?? '',
        'tipo'                => $cliente['tipo_vehiculo'] ?? '',
        'rines_lujo'          => $_extra['rines_lujo']     ?? '',
        'gps'                 => $_extra['gps']            ?? '',
        'uso_auto'            => $_extra['uso_auto']       ?? '',
        'tiene_adaptaciones'  => (int)($cliente['tiene_adaptaciones'] ?? 0),
        'detalle_adaptaciones'=> is_array($_extra) ? (implode(', ', array_filter([
            !empty($_extra['color'])      ? 'Color: '.$_extra['color']      : '',
            !empty($_extra['placas'])     ? 'Placas: '.$_extra['placas']    : '',
            !empty($_extra['vin'])        ? 'VIN: '.$_extra['vin']          : '',
            !empty($_extra['rines_lujo']) ? 'Rines lujo: '.$_extra['rines_lujo'] : '',
            !empty($_extra['gps'])        ? 'GPS: '.$_extra['gps']          : '',
            !empty($_extra['uso_auto'])   ? 'Uso: '.$_extra['uso_auto']     : '',
        ]))) : '',
        'info_adicional'      => $_extra['info_adicional'] ?? '',
    ];
}

$historial    =$historialModel->getByClienteIdConAgente($clienteId);
$notas        =$clienteModel->getNotas($clienteId);
$cotizaciones =$cotizacionModel->getByClienteId($clienteId);
// Enriquecer cotizaciones con detalles_ramo (datos de vehículo/producto de cada cotización)
foreach ($cotizaciones as &$_cotDet) {
    $_detRow = $cotizacionModel->getDetalles((int)$_cotDet['id']);
    $_cotDet['detalles_ramo'] = $_detRow;
}
unset($_cotDet);
$polizas      =$polizaModel ? $polizaModel->listarPorCliente($clienteId,$agenteId) : [];
// Auto-actualizar estado de pólizas según fecha_fin
if ($polizaModel && !empty($polizas)) {
    $polizaModel->actualizarEstadosVencidos();
    // Refresh para obtener estados actualizados
    $polizas = $polizaModel->listarPorCliente($clienteId,$agenteId);
    // Calcular días y actualizar estado en memoria (por_vencer si falta ≤30 días)
    foreach ($polizas as &$_p) {
        if (!empty($_p['fecha_fin']) && !in_array($_p['estado'],['vencida','cancelada'],true)) {
            $_dias=(int)ceil((strtotime($_p['fecha_fin'])-time())/86400);
            if ($_dias<=0) { $_p['estado']='vencida'; $polizaModel->actualizar((int)$_p['id'],$agenteId,['estado'=>'vencida']); }
            elseif ($_dias<=30 && $_p['estado']!=='por_vencer') { $_p['estado']='por_vencer'; $polizaModel->actualizar((int)$_p['id'],$agenteId,['estado'=>'por_vencer']); }
            elseif ($_dias>30 && $_p['estado']!=='vigente') { $_p['estado']='vigente'; $polizaModel->actualizar((int)$_p['id'],$agenteId,['estado'=>'vigente']); }
        }
    }
    unset($_p);
}

$archivosPorCot=[];
foreach ($cotizaciones as $cot) {
    $arch=$cotizacionModel->getArchivos((int)$cot['id']);
    if (!empty($arch)) $archivosPorCot[$cot['id']]=$arch;
}

// ── Checklist del asesor por cotización (documentos subidos vs requeridos) ──
$checklistReqs = [
    'autos'          => ['doc_cotizacion','ine','comprobante_dom','tarjeta_circulacion','foto_coche_1','foto_coche_2', 'poliza'],
    'vida'           => ['doc_cotizacion','ine','comprobante_dom', 'poliza'],
    'gastos_medicos' => ['doc_cotizacion','ine','comprobante_dom', 'poliza'],
];
$checklistLabelMap = [
    'doc_cotizacion'     => 'Cotización PDF','ine'=>'INE',
    'comprobante_dom'    => 'Comp. domicilio','tarjeta_circulacion'=>'Tarjeta circulación',
    'foto_coche_1'       => 'Foto 1','foto_coche_2'=>'Foto 2','foto_coche_3'=>'Foto 3',
    'foto_coche_4'       => 'Foto 4','foto_coche_5'=>'Foto 5',
    'factura_auto'       => 'Factura auto','constancia_fiscal'=>'Constancia fiscal',
    'comprobante_pago'   => 'Comprobante de pago',
    'poliza'             => 'Póliza',
];
$checklistPorCot = [];
foreach ($cotizaciones as $cot) {
    $ramo = $cot['ramo'] ?? 'autos';
    $reqs = $checklistReqs[$ramo] ?? $checklistReqs['autos'];
    $docsTipos = array_column($archivosPorCot[$cot['id']] ?? [], 'tipo_documento');
    $items = [];
    foreach ($reqs as $tipo) {
        $items[$tipo] = [
            'label'    => $checklistLabelMap[$tipo] ?? $tipo,
            'subido'   => in_array($tipo, $docsTipos, true),
            'required' => true,
        ];
    }
    $total    = count($reqs);
    $completos = count(array_filter($items, fn($i) => $i['subido']));
    $checklistPorCot[(int)$cot['id']] = [
        'items'     => $items,
        'total'     => $total,
        'completos' => $completos,
        'listo'     => $completos >= $total,
    ];
}

$pagosPorCot=[];
try {
    $db=\Database\Connection::getConnection();
    foreach ($cotizaciones as $cot) {
        // Leemos de la tabla correcta donde el coordinador registra el cobro
        $st=$db->prepare('SELECT metodo_pago, monto, comentarios AS notas, nombre_archivo, nombre_original, creado_en AS fecha_pago, NULL AS ruta FROM cotizacion_comprobantes_pago WHERE cotizacion_id=? AND eliminado_en IS NULL ORDER BY creado_en DESC');
        $st->execute([(int)$cot['id']]);
        $p=$st->fetchAll(\PDO::FETCH_ASSOC);
        if ($p) $pagosPorCot[(int)$cot['id']]=$p;
    }
} catch(\Throwable $e){}

// Documentos de póliza subidos por el coordinador en el flujo de emisión
$docPolizaPorCot = [];
try {
    $db = \Database\Connection::getConnection();
    foreach ($cotizaciones as $cot) {
        $st = $db->prepare(
            "SELECT ca.*, ca.nombre_original, ca.nombre_archivo, ca.ruta
             FROM cotizacion_archivos ca
             WHERE ca.cotizacion_id = ? AND ca.tipo_documento = 'poliza' AND ca.eliminado_en IS NULL
             ORDER BY ca.subido_en DESC LIMIT 5"
        );
        $st->execute([(int)$cot['id']]);
        $docs = $st->fetchAll(\PDO::FETCH_ASSOC);
        if ($docs) $docPolizaPorCot[(int)$cot['id']] = $docs;
    }
} catch(\Throwable $e) {}

// Datos extra del vehículo desde mini-form (en comentarios de cita)
// Busca en DB directamente para no depender de CitaModel::getByClienteId
$datosExtrasVehiculo=[];
try {
    $dbTmp=\Database\Connection::getConnection();
    $stCit=$dbTmp->prepare(
        'SELECT comentarios FROM citas WHERE cliente_id=? AND comentarios IS NOT NULL AND comentarios!="" ORDER BY id DESC'
    );
    $stCit->execute([$clienteId]);
    foreach ($stCit->fetchAll(\PDO::FETCH_COLUMN) as $comentario) {
        if (empty($comentario)) continue;
        // Extraer bloque de datos adicionales (puede venir después de texto con \n)
        $bloque = $comentario;
        $posBloque = strpos($comentario, '[Datos adicionales');
        if ($posBloque !== false) {
            $bloque = substr($comentario, $posBloque);
        } elseif (strpos($comentario, '|') === false) {
            continue;
        }
        $lineas = explode(' | ', $bloque);
        foreach ($lineas as $p) {
            $p = trim($p);
            if (empty($p) || strpos($p, '[Datos adicionales') !== false) continue;
            if (strpos($p, ': ') !== false) {
                [$lbl, $val] = explode(': ', $p, 2);
                $datosExtrasVehiculo[trim($lbl)] = trim($val);
            }
        }
        if (!empty($datosExtrasVehiculo)) break;
    }
} catch(\Throwable $e){}

if (empty($historial)) {
    $historial=[['tipo'=>'registro','titulo'=>'Cliente registrado','descripcion'=>'Registro inicial.',
                 'fecha'=>$cliente['fecha_registro'],'agente_nombre'=>'Sistema']];
}

$estadosCliente=['nuevo'=>['label'=>'Nuevo','class'=>'badge-info'],'contactado'=>['label'=>'Contactado','class'=>'badge-warning'],
    'con_cotizacion'=>['label'=>'Con Cotización','class'=>'badge-success'],'sin_contactar'=>['label'=>'Sin Contactar','class'=>'badge-danger'],
    'cerrado'=>['label'=>'Cerrado','class'=>'badge-success'],'perdido'=>['label'=>'Perdido','class'=>'badge-neutral']];
$estadosCot=['pendiente'=>['label'=>'Pendiente','class'=>'badge-warning'],'enviada'=>['label'=>'Enviada','class'=>'badge-info'],
    'aceptada'=>['label'=>'Aceptada','class'=>'badge-success'],'rechazada'=>['label'=>'Rechazada','class'=>'badge-danger'],
    'pagado'=>['label'=>'Pagado','class'=>'badge-success']];
$estadosPoliza=['vigente'=>['label'=>'Vigente','class'=>'badge-success','emoji'=>'✅'],
    'por_vencer'=>['label'=>'Por vencer','class'=>'badge-warning','emoji'=>'⚠️'],
    'vencida'=>['label'=>'Vencida','class'=>'badge-danger','emoji'=>'❌'],
    'cancelada'=>['label'=>'Cancelada','class'=>'badge-neutral','emoji'=>'🚫']];
$iconosH=['registro'=>'👤','llamada'=>'📞','cotizacion'=>'📋','cita'=>'📅','nota'=>'📝','venta'=>'✅','cambio_estado'=>'🔄'];

$estadoInfo    =$estadosCliente[$cliente['estado_cliente']]??['label'=>'—','class'=>'badge-neutral'];
$nombreCompleto=$cliente['nombre'].' '.$cliente['apellido_paterno'].' '.$cliente['apellido_materno'];
$iniciales     =strtoupper(substr($cliente['nombre'],0,1).substr($cliente['apellido_paterno'],0,1));
$edad          =strtotime($cliente['fecha_nacimiento'])!==false
    ?date_diff(date_create($cliente['fecha_nacimiento']),date_create('today'))->y:'—';
$pageTitle=$nombreCompleto;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style>
        .client-header{background:linear-gradient(135deg,var(--primary-light,#0ea5e9),var(--primary-color,#0284c7));border-radius:12px;padding:28px 32px;margin-bottom:24px;color:#fff;}
        .client-header-content{display:flex;align-items:center;gap:24px;flex-wrap:wrap;}
        .client-avatar-large{width:88px;height:88px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;border:3px solid rgba(255,255,255,.35);flex-shrink:0;}
        .client-header-info{flex:1;}
        .client-header-name{font-size:24px;font-weight:700;margin-bottom:8px;}
        .client-header-meta{display:flex;gap:18px;flex-wrap:wrap;opacity:.9;font-size:13px;}
        .client-header-meta span{display:flex;align-items:center;gap:5px;}
        .content-grid{display:grid;grid-template-columns:2fr 1fr;gap:24px;}
        @media(max-width:1024px){.content-grid{grid-template-columns:1fr;}}
        .quick-action-btn{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:8px;background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e5e7eb);color:var(--text-primary,#111);text-decoration:none;font-size:14px;transition:all .15s;cursor:pointer;width:100%;margin-bottom:6px;text-align:left;}
        .quick-action-btn:hover{background:var(--info-light,#eff6ff);border-color:var(--accent-color,#0ea5e9);}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 24px;}
        .info-item{display:flex;flex-direction:column;gap:3px;}
        .info-label{font-size:11px;color:var(--text-muted,#9ca3af);text-transform:uppercase;letter-spacing:.4px;}
        .info-value{font-size:14px;color:var(--text-primary,#111);font-weight:500;}
        .timeline-item{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-color,#e5e7eb);}
        .timeline-item:last-child{border-bottom:none;}
        .timeline-marker{width:32px;height:32px;border-radius:50%;background:var(--bg-secondary,#f8fafc);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
        .timeline-meta{font-size:11px;color:var(--text-muted,#9ca3af);margin-top:3px;display:flex;gap:8px;}
        .section-subtitle{font-size:11px;font-weight:700;color:var(--text-secondary,#6b7280);text-transform:uppercase;letter-spacing:.5px;margin:18px 0 10px;padding-top:14px;border-top:1px solid var(--border-color,#e5e7eb);}
        .poliza-card{border:1.5px solid var(--border-color,#e5e7eb);border-radius:10px;padding:14px 16px;margin-bottom:10px;}
        .poliza-card.por_vencer{border-color:#f59e0b;background:#fffbeb;}
        .poliza-card.vencida{border-color:#ef4444;background:#fef2f2;}
        .poliza-dias{font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;display:inline-block;}
        .dias-ok{background:#dcfce7;color:#16a34a;}
        .dias-warn{background:#fef9c3;color:#a16207;}
        .dias-danger{background:#fee2e2;color:#dc2626;}
        .pago-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-color,#e5e7eb);font-size:13px;}
        .pago-row:last-child{border-bottom:none;}
        /* Checklist del asesor */
        .checklist-wrap{background:var(--bg-secondary,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:10px;padding:12px 14px;margin-bottom:10px;}
        .checklist-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
        .checklist-progress{height:6px;background:var(--border-color,#e5e7eb);border-radius:99px;overflow:hidden;margin-bottom:10px;}
        .checklist-progress-bar{height:100%;border-radius:99px;transition:width .3s;}
        .checklist-items{display:grid;grid-template-columns:1fr 1fr;gap:4px;}
        .checklist-item{display:flex;align-items:center;gap:6px;font-size:12px;padding:4px 6px;border-radius:5px;}
        .checklist-item.ok{color:var(--success-color,#22c55e);}
        .checklist-item.pending{color:var(--text-muted,#9ca3af);}
        .checklist-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
        .checklist-dot.ok{background:var(--success-color,#22c55e);}
        .checklist-dot.pending{background:var(--border-color,#e5e7eb);}
        .badge-pagado{background:#dcfce7;color:#15803d;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:700;}
        .badge-pendiente-pago{background:#fef9c3;color:#a16207;font-size:11px;padding:2px 8px;border-radius:99px;font-weight:700;}
    </style>
</head>
<body>
<div class="crm-wrapper">
    <?php require __DIR__ . '/../partials/_navbar.php'; ?>
    <main class="main-content">

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-size:13px;">
            <a href="/clientes" class="btn btn-ghost btn-sm">← Clientes</a>
            <span style="color:var(--text-muted);">›</span>
            <span style="color:var(--text-secondary);"><?php echo htmlspecialchars($nombreCompleto); ?></span>
        </div>

        <div class="client-header">
            <div class="client-header-content">
                <div class="client-avatar-large"><?php echo $iniciales; ?></div>
                <div class="client-header-info">
                    <h1 class="client-header-name"><?php echo htmlspecialchars($nombreCompleto); ?></h1>
                    <div class="client-header-meta">
                        <span>✉️ <?php echo htmlspecialchars($cliente['correo']); ?></span>
                        <?php if (!empty($cliente['telefono'])): ?><span>📞 <?php echo htmlspecialchars($cliente['telefono']); ?></span><?php endif; ?>
                        <?php if (!empty($cliente['ciudad'])): ?><span>📍 <?php echo htmlspecialchars($cliente['ciudad']); ?></span><?php endif; ?>
                    </div>
                    <div style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <span class="badge" style="background:rgba(255,255,255,.25);color:#fff;"><?php echo $estadoInfo['label']; ?></span>
                        <select id="selectEstadoCliente" style="background:#fff;color:#111;border:1px solid rgba(255,255,255,.5);border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;">
                            <option value="">Cambiar estado...</option>
                            <?php foreach ($estadosCliente as $k=>$v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <a href="/crear-cotizacion?cliente_id=<?php echo $cliente['id']; ?>"
                   class="btn" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);white-space:nowrap;">📋 Nueva Cotización</a>
            </div>
        </div>

        <div class="content-grid">
            <!-- COLUMNA IZQUIERDA -->
            <div>
                <!-- Información personal -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h3 class="card-title">Información Personal</h3>
                        <button class="btn btn-sm btn-outline" data-action="det-open" data-modal="modalEditarCliente">✏️ Editar</button>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item"><span class="info-label">Nombre</span><span class="info-value"><?php echo htmlspecialchars($cliente['nombre']); ?></span></div>
                            <div class="info-item"><span class="info-label">Ap. Paterno</span><span class="info-value"><?php echo htmlspecialchars($cliente['apellido_paterno']); ?></span></div>
                            <div class="info-item"><span class="info-label">Ap. Materno</span><span class="info-value"><?php echo htmlspecialchars($cliente['apellido_materno']?:'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Correo</span><span class="info-value"><?php echo htmlspecialchars($cliente['correo']); ?></span></div>
                            <div class="info-item"><span class="info-label">Teléfono</span><span class="info-value"><?php echo htmlspecialchars($cliente['telefono']?:'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Sexo</span><span class="info-value"><?php echo ucfirst($cliente['sexo']?:'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Estado Civil</span><span class="info-value"><?php echo ucwords(str_replace('_',' ',$cliente['estado_civil']?:'—')); ?></span></div>
                            <div class="info-item"><span class="info-label">Fecha Nacimiento</span>
                                <span class="info-value"><?php echo strtotime($cliente['fecha_nacimiento'])!==false?date('d/m/Y',strtotime($cliente['fecha_nacimiento'])).' ('.$edad.' años)':'—'; ?></span></div>
                        </div>

                        <div class="section-subtitle">Dirección</div>
                        <div class="info-grid">
                            <div class="info-item"><span class="info-label">Calle</span><span class="info-value"><?php echo htmlspecialchars($cliente['calle']?:'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Colonia</span><span class="info-value"><?php echo htmlspecialchars($cliente['colonia']?:'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Ciudad</span><span class="info-value"><?php echo htmlspecialchars($cliente['ciudad']?:'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Estado</span><span class="info-value"><?php echo ucwords(str_replace('_',' ',$cliente['estado']?:'—')); ?></span></div>
                            <div class="info-item"><span class="info-label">C.P.</span><span class="info-value"><?php echo htmlspecialchars($cliente['codigo_postal']?:'—'); ?></span></div>
                        </div>

                        <?php $ramoCliente = $cliente['ramo'] ?? 'autos'; ?>

                        <?php if ($ramoCliente === 'autos'): ?>
                        <!-- ── Vehículo del cliente ──────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3 class="card-title">🚗 Vehículo</h3>
        <button type="button" class="btn btn-outline btn-sm" id="btnEditarVehiculo">✏️ Editar</button>
    </div>
    <div class="card-body" id="vehiculoViewMode">
        <?php if (!empty($vehiculo)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
            <?php
            $campos = [
                ['Marca',          $vehiculo['marca']      ?? '—'],
                ['Submarca',       $vehiculo['submarca']   ?? '—'],
                ['Versión',        $vehiculo['version']    ?? '—'],
                ['Modelo (año)',   $vehiculo['modelo']     ?? '—'],
                ['Color',          $vehiculo['color']      ?? '—'],
                ['Placas',         $vehiculo['placas']     ?? '—'],
                ['VIN / Serie',    $vehiculo['vin']        ?? '—'],
                ['Tipo',           $vehiculo['tipo']       ?? '—'],
                ['Rines de lujo',  $vehiculo['rines_lujo'] ? ucfirst($vehiculo['rines_lujo']) : '—'],
                ['GPS',            $vehiculo['gps']        ? ucfirst($vehiculo['gps'])        : '—'],
                ['Uso',            $vehiculo['uso_auto']   ? ucfirst($vehiculo['uso_auto'])   : '—'],
            ];
            foreach ($campos as [$lbl, $val]): ?>
            <div style="background:var(--bg-secondary);border-radius:8px;padding:12px;">
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;"><?= $lbl ?></div>
                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($val) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($vehiculo['tiene_adaptaciones'])): ?>
        <div style="margin-top:12px;background:var(--info-light);border-radius:8px;padding:12px;font-size:13px;">
            <strong>🔧 Adaptaciones / Modificaciones:</strong><br>
            <?= nl2br(htmlspecialchars($vehiculo['detalle_adaptaciones'] ?? '—')) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($vehiculo['info_adicional'])): ?>
        <div style="margin-top:8px;background:var(--bg-secondary);border-radius:8px;padding:12px;font-size:12px;color:var(--text-secondary);">
            📝 <?= nl2br(htmlspecialchars($vehiculo['info_adicional'])) ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;">Sin vehículo registrado.</p>
        <?php endif; ?>
    </div>
 
    <!-- Modo edición -->
    <div id="vehiculoEditMode" style="display:none;padding:16px 20px;">
        <input type="hidden" name="vehiculo_id" value="<?= (int)($vehiculo['id'] ?? 0) ?>">
 
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Marca</label>
                <select class="form-select" name="vehiculo_marca" id="inpMarca">
                    <option value="">Selecciona marca…</option>
                    <?php foreach ($MARCAS_VEHICULO as $m): ?>
                    <option value="<?= $m ?>" <?= ($vehiculo['marca'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Submarca / Línea</label>
                <input type="text" class="form-input" name="vehiculo_submarca" id="inpSubmarca"
                       value="<?= htmlspecialchars($vehiculo['submarca'] ?? '') ?>"
                       placeholder="Ej. Jetta, Tiguan, Civic…">
            </div>
        </div>
 
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Versión / Trim</label>
                <input type="text" class="form-input" name="vehiculo_version" id="inpVersion"
                       value="<?= htmlspecialchars($vehiculo['version'] ?? '') ?>"
                       placeholder="Ej. SE, Sport, Highline, 1.4T…">
            </div>
            <div class="form-group">
                <label class="form-label">Modelo (año)</label>
                <input type="number" class="form-input" name="vehiculo_modelo"
                       value="<?= htmlspecialchars($vehiculo['modelo'] ?? '') ?>"
                       min="1990" max="<?= date('Y') + 1 ?>" placeholder="<?= date('Y') ?>">
            </div>
        </div>
 
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Color</label>
                <input type="text" class="form-input" name="vehiculo_color"
                       value="<?= htmlspecialchars($vehiculo['color'] ?? '') ?>"
                       placeholder="Ej. Blanco perla">
            </div>
            <div class="form-group">
                <label class="form-label">Placas</label>
                <input type="text" class="form-input" name="vehiculo_placas"
                       value="<?= htmlspecialchars($vehiculo['placas'] ?? '') ?>"
                       placeholder="Ej. ABC-123-D">
            </div>
        </div>
 
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">VIN / Número de serie</label>
                <input type="text" class="form-input" name="vehiculo_vin"
                       value="<?= htmlspecialchars($vehiculo['vin'] ?? '') ?>"
                       placeholder="17 caracteres">
            </div>
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select class="form-select" name="vehiculo_tipo">
                    <?php foreach (['Sedán','SUV','Pickup','Hatchback','Coupé','Convertible','Minivan','Van','Camión','Motocicleta','Otro'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($vehiculo['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
 
        <!-- Rines / GPS / Uso -->
        <div class="form-row" style="margin-top:4px;">
            <div class="form-group">
                <label class="form-label">Rines de lujo</label>
                <select class="form-select" name="vehiculo_rines_lujo">
                    <option value="">— No especificado —</option>
                    <option value="si"  <?= ($vehiculo['rines_lujo'] ?? '') === 'si'    ? 'selected' : '' ?>>Sí</option>
                    <option value="no"  <?= ($vehiculo['rines_lujo'] ?? '') === 'no'    ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">GPS instalado</label>
                <select class="form-select" name="vehiculo_gps">
                    <option value="">— No especificado —</option>
                    <option value="si"    <?= ($vehiculo['gps'] ?? '') === 'si'    ? 'selected' : '' ?>>Sí</option>
                    <option value="no"    <?= ($vehiculo['gps'] ?? '') === 'no'    ? 'selected' : '' ?>>No</option>
                    <option value="no_se" <?= ($vehiculo['gps'] ?? '') === 'no_se' ? 'selected' : '' ?>>No sé</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Uso del vehículo</label>
                <select class="form-select" name="vehiculo_uso_auto">
                    <option value="">— No especificado —</option>
                    <option value="particular" <?= ($vehiculo['uso_auto'] ?? '') === 'particular' ? 'selected' : '' ?>>Particular</option>
                    <option value="comercial"  <?= ($vehiculo['uso_auto'] ?? '') === 'comercial'  ? 'selected' : '' ?>>Comercial</option>
                    <option value="uber_didi"  <?= ($vehiculo['uso_auto'] ?? '') === 'uber_didi'  ? 'selected' : '' ?>>Uber / DiDi</option>
                </select>
            </div>
        </div>

        <!-- Adaptaciones -->
        <div class="form-group" style="margin-top:8px;">
            <label class="form-label" style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <input type="checkbox" name="vehiculo_tiene_adaptaciones" id="chkAdapt"
                       value="1" <?= !empty($vehiculo['tiene_adaptaciones']) ? 'checked' : '' ?>
                       onchange="document.getElementById('grpAdapt').style.display=this.checked?'':'none'">
                <span>¿Cuenta con alguna adaptación o modificación?</span>
            </label>
        </div>
        <div id="grpAdapt" style="<?= !empty($vehiculo['tiene_adaptaciones']) ? '' : 'display:none' ?>">
            <div class="form-group">
                <label class="form-label">¿Cuáles adaptaciones o modificaciones?</label>
                <textarea class="form-input" name="vehiculo_detalle_adaptaciones" rows="2"
                          placeholder="Describe las adaptaciones o modificaciones del vehículo…"
                          style="resize:vertical;"><?= htmlspecialchars($vehiculo['detalle_adaptaciones'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">¿Deseas agregar algo más?</label>
                <textarea class="form-input" name="vehiculo_info_adicional" rows="2"
                          placeholder="Información adicional relevante del vehículo…"
                          style="resize:vertical;"><?= htmlspecialchars($vehiculo['info_adicional'] ?? '') ?></textarea>
            </div>
        </div>
 
        <div style="display:flex;gap:10px;margin-top:16px;">
            <button type="button" class="btn btn-outline btn-sm" id="btnCancelarVehiculo">Cancelar</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnGuardarVehiculo">💾 Guardar vehículo</button>
        </div>
    </div>
</div>
                        <?php endif; ?>

                        <?php if (in_array($ramoCliente, ['vida','gastos_medicos'], true)): ?>
                        <div class="section-subtitle"><?php echo $ramoCliente==='vida'?'❤️ Seguro de Vida':'🏥 Gastos Médicos'; ?></div>
                        <div class="info-grid">
                            <div class="info-item"><span class="info-label">Sexo</span><span class="info-value"><?php echo ucfirst($cliente['sexo']??'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Fecha Nacimiento</span><span class="info-value"><?php echo $cliente['fecha_nacimiento']?date('d/m/Y',strtotime($cliente['fecha_nacimiento'])):'—'; ?></span></div>
                            <div class="info-item"><span class="info-label">¿Tiene seguro?</span>
                                <span class="info-value"><span class="badge <?php echo $cliente['asegurado']?'badge-success':'badge-neutral'; ?>"><?php echo $cliente['asegurado']?'Sí':'No'; ?></span></span></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($ramoCliente === 'empresas'): ?>
                        <div class="section-subtitle">🏢 Datos Empresariales</div>
                        <div class="info-grid">
                            <div class="info-item"><span class="info-label">Giro / Industria</span><span class="info-value"><?php echo htmlspecialchars($cliente['giro_empresa']??'—'); ?></span></div>
                            <div class="info-item"><span class="info-label">Núm. Empleados</span><span class="info-value"><?php echo $cliente['num_empleados']??'—'; ?></span></div>
                            <div class="info-item"><span class="info-label">Facturación Anual</span><span class="info-value"><?php echo $cliente['facturacion_anual']?'$'.number_format((float)$cliente['facturacion_anual']):'—'; ?></span></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($datosExtrasVehiculo)): ?>
                        <div class="section-subtitle">Datos adicionales</div>
                        <div class="info-grid">
                            <?php foreach ($datosExtrasVehiculo as $lbl=>$val): ?>
                            <div class="info-item">
                                <span class="info-label"><?php echo htmlspecialchars($lbl); ?></span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$val))); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ PÓLIZAS ═══ -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h3 class="card-title">Pólizas <span class="badge badge-neutral"><?php echo count($polizas); ?></span></h3>
                        <!-- <?php if ($puedeGestionarPolizas): ?>
                        <button class="btn btn-sm btn-primary" data-action="det-open" data-modal="modalSubirPoliza">+ Subir Póliza</button>
                        <?php endif; ?> -->
                    </div>
                    <div class="card-body">
                        <?php if ($rolAgente === 'asesor'): ?>
                        <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;line-height:1.45;">
                            Resumen informativo: sube tu primera poliza.
                        </p>
                        <?php endif; ?>
                        <?php
                        // Recopilar documentos de póliza del coordinador para todas las cotizaciones del cliente
                        $allDocPoliza = [];
                        foreach ($cotizaciones as $_cot) {
                            if (!empty($docPolizaPorCot[(int)$_cot['id']])) {
                                foreach ($docPolizaPorCot[(int)$_cot['id']] as $_doc) {
                                    $_doc['_folio']   = $_cot['folio'] ?? '#'.$_cot['id'];
                                    $_doc['_compania'] = $_cot['compania'] ?? '';
                                    $allDocPoliza[] = $_doc;
                                }
                            }
                        }
                        ?>
                        <?php if (empty($polizas) && empty($allDocPoliza)): ?>
                        <div style="text-align:center;padding:28px 0;color:var(--text-muted);">
                            <div style="font-size:36px;margin-bottom:8px;">📄</div>
                            <p style="font-size:13px;margin-bottom:10px;">Sin pólizas registradas.</p>
                            <!-- <?php if ($puedeGestionarPolizas): ?>
                            <button class="btn btn-primary btn-sm" data-action="det-open" data-modal="modalSubirPoliza">Subir primera póliza</button>
                            <?php endif; ?> -->
                        </div>
                        <?php elseif (empty($polizas) && !empty($allDocPoliza)): ?>
                        <!-- El coordinador subió documentos de póliza pero no hay póliza en la tabla polizas -->
                        <div style="margin-bottom:12px;">
                            <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">📋 Documentos de póliza registrados por el coordinador:</p>
                            <?php foreach ($allDocPoliza as $_dp):
                                $_rutaF = dirname(__DIR__,3).'/uploads/cotizaciones/'.(int)($_dp['cotizacion_id'] ?? 0).'/'.$_dp['nombre_archivo'];
                                $_urlD  = file_exists($_rutaF) ? '/archivo?file=cotizaciones/'.(int)$_dp['cotizacion_id'].'/'.$_dp['nombre_archivo'] : null;
                                $_extD  = strtolower(pathinfo($_dp['nombre_original'] ?? '', PATHINFO_EXTENSION));
                                $_iconD = $_extD === 'pdf' ? '📄' : '🖼️';
                            ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;margin-bottom:8px;">
                                <span style="font-size:22px;"><?php echo $_iconD; ?></span>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($_dp['nombre_original'] ?? $_dp['nombre_archivo']); ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($_dp['_folio']); ?> · <?php echo htmlspecialchars($_dp['_compania']); ?></div>
                                </div>
                                <?php if ($_urlD): ?>
                                <a href="<?php echo $_urlD; ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 10px;">Ver</a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <?php foreach ($polizas as $pol):
                            $epI   =$estadosPoliza[$pol['estado']]??['label'=>'—','class'=>'badge-neutral','emoji'=>'📄'];
                            $dias  =(int)($pol['dias_restantes']??999);
                            $dCss  =$dias<=7?'dias-danger':($dias<=30?'dias-warn':'dias-ok');
                            $cCss  =$pol['estado']==='vencida'?'vencida':($pol['estado']==='por_vencer'?'por_vencer':'');
                            $rutaF =dirname(__DIR__,3).'/uploads/polizas/'.$clienteId.'/'.$pol['nombre_archivo'];
                            $urlP  =file_exists($rutaF)?'/archivo?file=polizas/'.$clienteId.'/'.$pol['nombre_archivo']:null;
                            $estadoCotPol = '';
                            foreach ($cotizaciones as $_c) { if ($_c['id'] == $pol['cotizacion_id']) { $estadoCotPol = $_c['estado']; break; } }
                            // Asegurarnos de que verifique todos los indicadores de pago
                            $pagada = ((bool)($pol['pago_confirmado']??false)) || ($estadoCotPol === 'pagado') || (($pol['estado_pago'] ?? '') === 'confirmado');
                        ?>
                        <div class="poliza-card <?php echo $cCss; ?>">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                                        <span class="badge <?php echo $epI['class']; ?>"><?php echo $epI['emoji'].' '.$epI['label']; ?></span>
                                        <?php if ($pagada): ?>
                                        <span class="badge-pagado">✅ Pagada</span>
                                        <?php else: ?>
                                        <span class="badge-pendiente-pago">⏳ Pago pendiente</span>
                                        <?php endif; ?>
                                        <?php if ($pol['idrive_subido']): ?><span style="font-size:10px;background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:99px;font-weight:600;">☁️ iDrive</span><?php endif; ?>
                                        <?php if (!empty($pol['compania'])): ?><span style="font-size:12px;font-weight:600;"><?php echo htmlspecialchars($pol['compania']); ?></span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($pol['numero_poliza'])): ?>
                                    <div style="font-size:11px;color:var(--text-secondary);margin-bottom:4px;"># <?php echo htmlspecialchars($pol['numero_poliza']); ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:12px;color:var(--text-secondary);display:flex;gap:12px;flex-wrap:wrap;">
                                        <?php if ($pol['fecha_inicio']): ?><span>📅 <?php echo date('d/m/Y',strtotime($pol['fecha_inicio'])); ?></span><?php endif; ?>
                                        <?php if ($pol['fecha_fin']): ?>
                                        <span>⏳ <?php echo date('d/m/Y',strtotime($pol['fecha_fin'])); ?></span>
                                        <?php if (!in_array($pol['estado'],['vencida','cancelada'],true)): ?>
                                        <span class="poliza-dias <?php echo $dCss; ?>"><?php echo $dias; ?> días</span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($pol['frecuencia_renovacion'])): ?><span>🔄 <?php echo ucfirst($pol['frecuencia_renovacion']); ?></span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($pol['notas'])): ?><div style="font-size:12px;color:var(--text-muted);margin-top:6px;"><?php echo htmlspecialchars($pol['notas']); ?></div><?php endif; ?>
                                </div>
                                <div style="display:flex;gap:6px;flex-shrink:0;align-items:center;">
                                        <?php if (!empty($pol['cotizacion_id'])): ?>
                                    <a href="/cotizaciones?ver=<?php echo (int)$pol['cotizacion_id']; ?>"
                                       style="font-size:11px;color:var(--accent-color);" title="Ver cotización vinculada">📋 Cotización</a>
                                    <?php endif; ?>
                                    <?php if ($puedeGestionarPolizas): ?>
                                        <?php if ($urlP): ?>
                                    <a href="<?php echo $urlP; ?>" target="_blank" class="btn btn-sm btn-outline" title="Ver">👁</a>
                                    <a href="<?php echo $urlP; ?>" download="<?php echo htmlspecialchars($pol['nombre_original']); ?>" class="btn btn-sm btn-outline" title="Descargar">⬇</a>
                                        <?php endif; ?>
                                    <button class="btn btn-sm btn-outline" data-action="det-editarPoliza" data-pol="<?php echo htmlspecialchars(json_encode($pol),ENT_QUOTES); ?>" title="Editar">✏️</button>
                                    <button class="btn btn-sm btn-outline" data-action="det-eliminarPoliza" data-id="<?php echo $pol['id']; ?>" title="Eliminar" style="color:var(--danger-color,#ef4444);">🗑</button>
                                    <?php elseif ($urlP): ?>
                                    <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;">Archivo en custodia</span>
                                    <?php endif; ?>
                                    <!-- Botón de Renovación: visible siempre para el asesor -->
                                    <button class="btn btn-sm btn-outline"
                                            style="color:var(--accent-color);border-color:var(--accent-color);"
                                            data-action="det-solicitarRenovacion"
                                            data-poliza-id="<?php echo (int)$pol['id']; ?>"
                                            data-poliza-num="<?php echo htmlspecialchars($pol['numero_poliza'] ?? 'Sin número'); ?>"
                                            title="Solicitar renovación de póliza">
                                        🔄 Renovar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ═══ PAGOS ═══ -->
                <?php if (!empty($pagosPorCot)): ?>
                <div class="card mb-24">
                    <div class="card-header"><h3 class="card-title">💰 Pagos Registrados</h3></div>
                    <div class="card-body">
                        <?php foreach ($cotizaciones as $cot):
                            if (empty($pagosPorCot[(int)$cot['id']])) continue;
                            $pagos=$pagosPorCot[(int)$cot['id']];
                            $metodosLabel=['spei'=>'🏦 SPEI','contado'=>'💵 Contado','meses_sin_intereses'=>'💳 MSI'];
                        ?>
                        <div style="margin-bottom:18px;">
                            <div style="font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;">
                                <span style="font-family:'Courier New',monospace;background:var(--info-light,#eff6ff);color:var(--accent-color,#0ea5e9);padding:2px 8px;border-radius:4px;">
                                    <?php echo htmlspecialchars($cot['folio']??'#'.$cot['id']); ?>
                                </span>
                                <?php echo htmlspecialchars($cot['compania']); ?> — <?php echo htmlspecialchars($cot['tipo_cobertura']); ?>
                            </div>
                            <?php foreach ($pagos as $pago):
                                $mLabel = $metodosLabel[$pago['metodo_pago']] ?? $pago['metodo_pago'];
                                $urlPago = null;
                                                        
                                if (!empty($pago['ruta'])) {
                                                        
                                    // Ruta real en el servidor (con uploads)
                                    $rp = dirname(__DIR__, 3) . '/' . $pago['ruta'];
                                                        
                                    if (file_exists($rp)) {
                                                        
                                        // Quitar "uploads/" solo para la URL
                                        $rutaPublica = preg_replace('#^uploads/#', '', $pago['ruta']);
                                                        
                                        $urlPago = '/archivo?file=' . $rutaPublica;
                                    }
                                }
                            ?>
                            <div class="pago-row">
                                <div style="font-size:20px;">💰</div>
                                <div style="flex:1;">
                                    <div style="font-weight:600;"><?php echo $mLabel; ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?php echo date('d/m/Y', strtotime($pago['fecha_pago'] ?? $pago['creada_en'] ?? 'now')); ?><?php if ($pago['notas']): ?> · <?php echo htmlspecialchars($pago['notas']); ?><?php endif; ?></div>
                                </div>
                                <?php if ($pago['monto']>0): ?><div style="font-weight:700;color:var(--success-color,#22c55e);">$<?php echo number_format((float)$pago['monto'],2); ?></div><?php endif; ?>
                                <?php if ($urlPago): ?><a href="<?php echo $urlPago; ?>" target="_blank" class="btn btn-sm btn-outline" title="Ver comprobante">📎</a><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Historial -->
                <div class="card mb-24">
                    <div class="card-header"><h3 class="card-title">Historial de Actividad</h3></div>
                    <div class="card-body">
                        <?php if (empty($historial)): ?>
                        <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px 0;">Sin actividad registrada.</p>
                        <?php else: ?>
                        <div id="histFeed">
                        <?php foreach ($historial as $ev): $inf=$iconosH[$ev['tipo']]??'•'; ?>
                        <div class="timeline-item hist-item">
                            <div class="timeline-marker"><?php echo $inf; ?></div>
                            <div style="flex:1;">
                                <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($ev['titulo']); ?></div>
                                <?php if (!empty($ev['descripcion'])): ?><div style="font-size:13px;color:var(--text-secondary);margin-top:3px;"><?php echo htmlspecialchars($ev['descripcion']); ?></div><?php endif; ?>
                                <div class="timeline-meta">
                                    <span><?php echo date('d/m/Y H:i',strtotime($ev['fecha'])); ?></span>
                                    <?php if (!empty($ev['agente_nombre'])): ?><span>· <?php echo htmlspecialchars($ev['agente_nombre']); ?></span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <div id="histPag" style="display:flex;gap:4px;justify-content:center;padding:12px 0;flex-wrap:wrap;"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notas -->
                <?php if (!empty($notas)): ?>
                <div class="card mb-24">
                    <div class="card-header"><h3 class="card-title">Notas <span class="badge badge-neutral"><?php echo count($notas); ?></span></h3>
                        <button class="btn btn-sm btn-outline" data-action="det-open" data-modal="modalNota">+ Agregar</button></div>
                    <div class="card-body">
                        <?php foreach ($notas as $nota): ?>
                        <div style="padding:10px 0;border-bottom:1px solid var(--border-color,#e5e7eb);font-size:13px;">
                            <div><?php echo htmlspecialchars($nota['texto']); ?></div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:8px;"><?php echo date('d/m/Y H:i',strtotime($nota['fecha'])); ?><?php if (!empty($nota['agente_nombre'])): ?> · <?php echo htmlspecialchars($nota['agente_nombre']); ?><?php endif; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- COLUMNA DERECHA -->
            <div>
                <div class="card mb-24">
                    <div class="card-header"><h3 class="card-title">Acciones Rápidas</h3></div>
                    <div class="card-body">
                        <a href="mailto:<?php echo htmlspecialchars($cliente['correo']); ?>" class="quick-action-btn">✉️ Enviar Correo</a>
                        <?php if (!empty($cliente['telefono'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($cliente['telefono']); ?>" class="quick-action-btn">📞 Llamar al Cliente</a>
                        <a href="https://wa.me/<?php echo preg_replace('/\D/','',$cliente['telefono']); ?>" target="_blank" class="quick-action-btn">💬 WhatsApp</a>
                        <?php endif; ?>
                        <a href="/crear-cotizacion?cliente_id=<?php echo $cliente['id']; ?>" class="quick-action-btn">📋 Nueva Cotización</a>
                        <button class="quick-action-btn" data-action="det-open" data-modal="modalCita">📅 Programar Cita</button>
                        <button class="quick-action-btn" data-action="det-open" data-modal="modalNota">📝 Agregar Nota</button>
                        <!-- <?php if ($puedeGestionarPolizas): ?>
                        <button class="quick-action-btn" data-action="det-open" data-modal="modalSubirPoliza">📄 Subir Póliza</button>
                        <?php endif; ?> -->
                    </div>
                </div>

                <!-- Cotizaciones -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Cotizaciones <span class="badge badge-neutral"><?php echo count($cotizaciones); ?></span></h3>
                        <a href="/crear-cotizacion?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-primary">+ Nueva</a></div>
                    <div class="card-body" style="padding:0 20px;">
                        <?php if (empty($cotizaciones)): ?>
                        <div style="text-align:center;padding:24px 0;">
                            <p style="color:var(--text-muted);font-size:13px;margin-bottom:10px;">Sin cotizaciones.</p>
                            <a href="/crear-cotizacion?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-primary btn-sm">Crear Cotización</a>
                        </div>
                        <?php else: ?>
                        <!-- Navegador numerado -->
                        <div id="cotNav" style="display:flex;align-items:center;gap:8px;padding:8px 0 4px;border-bottom:1px solid var(--border-color,#e5e7eb);margin-bottom:4px;">
                            <button id="cotPrev" class="btn btn-sm btn-outline" style="padding:2px 10px;" disabled>‹</button>
                            <span id="cotLabel" style="font-size:12px;font-weight:600;color:var(--text-secondary);flex:1;text-align:center;"></span>
                            <button id="cotNext" class="btn btn-sm btn-outline" style="padding:2px 10px;">›</button>
                        </div>
                        <?php foreach ($cotizaciones as $cot):
                            $estI =$estadosCot[$cot['estado']]??['label'=>'—','class'=>'badge-neutral'];
                            $archs=$archivosPorCot[$cot['id']]??[];
                        ?>
                        <?php
$BADGE_PAGO_CL = [
    'pendiente'  => ['badge-warning', '⏳ Pago pendiente'],
    'confirmado' => ['badge-success', '✅ Pagado'],
    'rechazado'  => ['badge-danger',  '✗ Rechazado'],
];

[$bPagoClass, $bPagoLabel] =
    $BADGE_PAGO_CL[$cot['estado_pago'] ?? 'pendiente']
    ?? ['badge-neutral','—'];
?>
                        <div class="cot-item" style="padding:14px 0;border-bottom:1px solid var(--border-color,#e5e7eb);">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                                <div style="flex:1;min-width:0;">
                                    <?php if (!empty($cot['folio'])): ?>
                                    <div style="font-family:'Courier New',monospace;font-size:11px;font-weight:700;color:var(--accent-color,#0ea5e9);background:var(--info-light,#eff6ff);padding:2px 7px;border-radius:4px;display:inline-block;margin-bottom:5px;"><?php echo htmlspecialchars($cot['folio']); ?></div>
                                    <?php endif; ?>
                                    <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($cot['compania']); ?> &mdash; <?php echo htmlspecialchars($cot['tipo_cobertura']); ?></div>
                                    <?php
                                    // Mostrar datos de vehículo distintos por cotización (si los hay en detalles_ramo)
                                    $_dr = $cot['detalles_ramo'] ?? [];
                                    $_ramoC = $cot['ramo'] ?? 'autos';
                                    if ($_ramoC === 'autos' && !empty($_dr)):
                                        $_partes = array_filter([
                                            !empty($_dr['tipo_vehiculo']) ? ucfirst($_dr['tipo_vehiculo']) : '',
                                            !empty($_dr['marca'])        ? $_dr['marca']  : '',
                                            !empty($_dr['modelo'])       ? $_dr['modelo'] : '',
                                            !empty($_dr['anio'])         ? $_dr['anio']   : '',
                                            !empty($_dr['uso'])          ? '(' . ucfirst($_dr['uso']) . ')' : '',
                                        ]);
                                        if (!empty($_partes)): ?>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;display:flex;gap:6px;flex-wrap:wrap;">
                                        🚗 <span><?= htmlspecialchars(implode(' ', $_partes)) ?></span>
                                        <?php if (!empty($_dr['gps'])): ?><span>· GPS: <?= ucfirst($_dr['gps']) ?></span><?php endif; ?>
                                        <?php if (!empty($_dr['rines_lujo'])): ?><span>· Rines: <?= ucfirst($_dr['rines_lujo']) ?></span><?php endif; ?>
                                    </div>
                                    <?php endif; endif; ?>
                                    <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;"><?php echo date('d/m/Y',strtotime($cot['fecha'])); ?>
                                    <?php
                                    $cotEst = $cot['estado'] ?? '';
                                    if (!empty($cot['fecha_fin_vigencia']) && in_array($cotEst, ['aceptada', 'pagado'], true)): ?>
                                         &middot; Fin vig.: <?php echo date('d/m/Y', strtotime($cot['fecha_fin_vigencia'])); ?>
                                    <?php elseif (!empty($cot['fecha_fin_vigencia'])): ?>
                                         &middot; Vig. propuesta: <?php echo date('d/m/Y', strtotime($cot['fecha_fin_vigencia'])); ?>
                                    <?php endif; ?></div>
                                </div>
                                <div style="text-align:right;flex-shrink:0;">
                                    <div style="font-weight:700;color:var(--success-color,#22c55e);">$<?php echo number_format((float)$cot['precio'],0); ?></div>
                                    <span class="badge <?php echo $estI['class']; ?>" style="font-size:10px;"><?php echo $estI['label']; ?></span>
                                </div>
                            </div>
                            <!-- Acciones de cotizacion -->
                            <?php
                            $chk       = $checklistPorCot[(int)$cot['id']] ?? null;
                            $cotListo  = $chk && $chk['listo'];
                            $yaPagada  = ($cot['estado_pago'] ?? 'pendiente') === 'confirmado' || $cot['estado'] === 'pagado';
                            ?>
                            <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
                                <?php if ($rolAgente === 'asesor'): ?>
    <?php
    // --- LÍNEA DE PROTECCIÓN ---
    $archs = $archs ?? []; 
    // ---------------------------

    // 1. Definir los documentos estrictamente obligatorios del ASESOR
    $docs_obligatorios_asesor = [];
    $ramo_cot = $cot['ramo'] ?? 'autos';
    if ($ramo_cot === 'autos') {
        $docs_obligatorios_asesor = ['doc_cotizacion', 'ine', 'comprobante_dom', 'tarjeta_circulacion', 'foto_coche_1', 'foto_coche_2'];
    } else {
        $docs_obligatorios_asesor = ['doc_cotizacion', 'ine', 'comprobante_dom'];
    }

    // 2. Mapeo de nombres limpios para la interfaz
    $nombres_documentos = [
        'doc_cotizacion'      => 'Cotización',
        'ine'                 => 'INE',
        'comprobante_dom'     => 'Comprobante de Domicilio',
        'tarjeta_circulacion' => 'Tarjeta de Circulación',
        'foto_coche_1'        => 'Foto de Coche 1',
        'foto_coche_2'        => 'Foto de Coche 2'
    ];

    // 3. Obtener los documentos que el asesor ya subió en esta cotización
    $tipos_subidos = array_column($archs, 'tipo_documento');

    // 4. Calcular cuáles le faltan
    $documentos_faltantes = [];
    foreach ($docs_obligatorios_asesor as $doc_req) {
        if (!in_array($doc_req, $tipos_subidos)) {
            $documentos_faltantes[] = $nombres_documentos[$doc_req] ?? $doc_req;
        }
    }
    $bloquear_boton_emision = !empty($documentos_faltantes);
    ?>

    <a href="/crear-cotizacion?cotizacion_id=<?php echo (int)$cot['id']; ?>"
       class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 10px;" title="Editar cotizacion">&#9999;&#65039; Editar</a>
    
    <?php if ($yaPagada): ?>
        <span style="font-size:11px;padding:3px 10px;background:#dcfce7;color:#16a34a;border-radius:6px;font-weight:600;">&#10003; Pago confirmado</span>
    <?php else: ?>
        <button type="button" 
    class="btn btn-sm btn-primary" 
    style="font-size:11px;padding:3px 12px; <?php echo $bloquear_boton_emision ? 'opacity:0.5; cursor:not-allowed;' : ''; ?>" 
    <?php echo $bloquear_boton_emision ? 'disabled' : ''; ?>
    data-cot-id="<?php echo $cot['id']; ?>"
    data-action="abrir-emision">
    📝 Emitir y Subir Póliza
</button>
    <?php endif; ?>

    <?php if ($bloquear_boton_emision && !$yaPagada): ?>
        <div style="flex-basis: 100%; background-color: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 6px 10px; border-radius: 6px; margin-top: 4px; font-size: 11px;">
            <strong>⚠️ Faltan:</strong> <?php echo htmlspecialchars(implode(', ', $documentos_faltantes)); ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
                            </div>
                            <?php if (!empty($archs)): ?>
                            <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px;">
                                <?php foreach ($archs as $arch):
                                    if (!empty($arch['eliminado_en'])) continue;
                                    $extA=strtolower(pathinfo($arch['nombre_original']??$arch['nombre_archivo']??'',PATHINFO_EXTENSION));
                                    $iconA=$extA==='pdf'?'📄':'🖼️';
                                    $rutaFA=dirname(__DIR__,3).'/uploads/cotizaciones/'.$cot['id'].'/'.($arch['nombre_archivo']??'');
                                    $urlA=file_exists($rutaFA)?'/archivo?file=cotizaciones/'.$cot['id'].'/'.$arch['nombre_archivo']:null;
                                ?>
                                <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;font-size:12px;">
                                    <span><?php echo $iconA; ?></span>
                                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($arch['nombre_original']??$arch['nombre_archivo']??''); ?></span>
                                    <?php if ($urlA): ?><a href="<?php echo $urlA; ?>" target="_blank" class="btn btn-sm btn-outline" style="padding:2px 8px;font-size:10px;">Ver</a><?php endif; ?>
                                    <button data-action="det-eliminarArchivo" data-id="<?php echo $arch['id']; ?>" class="btn btn-sm" style="padding:2px 8px;font-size:10px;color:var(--danger-color,#ef4444);background:none;border:1px solid var(--danger-color,#ef4444);" title="Eliminar">🗑</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php
                            // ── Checklist del asesor ─────────────────────────
                            $cl = $checklistPorCot[(int)$cot['id']] ?? null;
                            if ($cl && !$puedeGestionarPolizas):
                                $pct = $cl['total'] > 0 ? round($cl['completos'] / $cl['total'] * 100) : 0;
                                $barColor = $cl['listo'] ? '#22c55e' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                            ?>
                            <div class="checklist-wrap" style="margin-top:10px;">
                                <div class="checklist-header">
                                    <span style="font-size:12px;font-weight:700;color:var(--text-secondary);">
                                        📋 Documentos (<?php echo $cl['completos']; ?>/<?php echo $cl['total']; ?>)
                                    </span>
                                    <?php if ($cl['listo']): ?>
                                    <span style="font-size:11px;background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:99px;font-weight:700;">✅ Completo</span>
                                    <?php else: ?>
                                    <span style="font-size:11px;background:#fef9c3;color:#a16207;padding:2px 8px;border-radius:99px;font-weight:700;">⚠ Faltan docs</span>
                                    <?php endif; ?>
                                </div>
                                <div class="checklist-progress">
                                    <div class="checklist-progress-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>;"></div>
                                </div>
                                <div class="checklist-items">
                                    <?php foreach ($cl['items'] as $tipo => $item): ?>
                                    <div class="checklist-item <?php echo $item['subido'] ? 'ok' : 'pending'; ?>">
                                        <div class="checklist-dot <?php echo $item['subido'] ? 'ok' : 'pending'; ?>"></div>
                                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!$cl['listo']): ?>
                                <div style="margin-top:8px;">
                                    <a href="/crear-cotizacion?cotizacion_id=<?php echo (int)$cot['id']; ?>"
                                       class="btn btn-sm btn-outline" style="font-size:11px;width:100%;text-align:center;padding:5px 10px;">
                                        📎 Subir documentos faltantes
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ═══ MODALES ═══════════════════════════════════════════════════════════════ -->
<!-- Modal Nota -->
<div class="modal-overlay" id="modalNota">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Agregar Nota</h3><button class="modal-close" data-close="modalNota">&times;</button></div>
        <div class="modal-body">
            <div class="form-group"><label class="form-label required">Nota</label>
                <textarea class="form-textarea" id="notaTexto" rows="4" placeholder="Escribe tu nota..."></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalNota">Cancelar</button>
            <button class="btn btn-primary" data-action="det-guardarNota">Guardar Nota</button>
        </div>
    </div>
</div>

<!-- Modal Cita -->
<div class="modal-overlay" id="modalCita">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Programar Cita</h3><button class="modal-close" data-close="modalCita">&times;</button></div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label class="form-label required">Fecha</label><input type="date" class="form-input" id="citaFecha" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="form-group"><label class="form-label required">Hora</label><input type="time" class="form-input" id="citaHora" value="10:00"></div>
            </div>
            <div class="form-group"><label class="form-label">Tipo</label>
                <select class="form-select" id="citaTipo">
                    <option value="llamada">📞 Llamada</option><option value="videollamada">💻 Videollamada</option><option value="presencial">🤝 Presencial</option>
                </select>
            </div>
            <div class="form-group" id="grupoEnlaceMeet" style="display:none;">
                <label class="form-label">Enlace de Videollamada</label>
                <div style="display:flex;gap:8px;"><input type="text" class="form-input" id="citaEnlace" placeholder="https://meet.google.com/...">
                    <button type="button" class="btn btn-outline btn-sm" data-action="det-generarMeet" style="white-space:nowrap;">🎥 Generar</button></div>
            </div>
            <div class="form-group"><label class="form-label">Comentarios</label>
                <textarea class="form-textarea" id="citaComentarios" placeholder="Objetivo de la cita..."></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalCita">Cancelar</button>
            <button class="btn btn-success" data-action="det-guardarCita">Programar Cita</button>
        </div>
    </div>
</div>

<!-- Modal Subir Póliza (solo coordinador) -->
<?php if ($puedeGestionarPolizas): ?>
<div class="modal-overlay" id="modalSubirPoliza">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header"><h3 class="modal-title">📄 Subir Póliza</h3><button class="modal-close" data-close="modalSubirPoliza">&times;</button></div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Compañía</label>
                    <select class="form-select" id="polizaCompania">
                        <option value="">— Selecciona —</option>
                        <?php foreach(['GNP','Qualitas','Chubb','Inbursa'] as $c): ?>
                        <option><?php echo $c; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Número de Póliza</label><input type="text" class="form-input" id="polizaNumero" placeholder="Ej. 01234567"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Inicio de Vigencia</label><input type="date" class="form-input" id="polizaFechaInicio"></div>
                <div class="form-group"><label class="form-label">Fin de Vigencia</label><input type="date" class="form-input" id="polizaFechaFin"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Frecuencia de Renovación</label>
                    <select class="form-select" id="polizaFrecuencia">
                        <option value="anual">🔄 Anual</option>
                        <option value="trimestral">🔄 Trimestral</option>
                        <option value="mensual">🔄 Mensual</option>
                        <option value="semanal">🔄 Semanal</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Vincular a cotización</label>
                    <select class="form-select" id="polizaCotizacion">
                        <option value="">— Sin vincular —</option>
                        <?php foreach ($cotizaciones as $cot): ?>
                        <option value="<?php echo $cot['id']; ?>"><?php echo htmlspecialchars(($cot['folio']??'#'.$cot['id']).' — '.$cot['compania']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Notas</label><textarea class="form-textarea" id="polizaNotas" rows="2" placeholder="Observaciones..."></textarea></div>
            <div class="form-group">
                <label class="form-label required">Archivo <small style="color:var(--text-muted);">PDF/imagen · máx 20MB</small></label>
                <div id="polizaDropZone" data-action="det-triggerPolizaFile"
                     style="border:2px dashed var(--border-color,#e5e7eb);border-radius:10px;padding:24px;text-align:center;cursor:pointer;background:var(--bg-secondary);">
                    <input type="file" id="polizaFile" accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                    <div style="font-size:28px;margin-bottom:6px;">📄</div>
                    <div style="font-size:13px;color:var(--text-muted);">Clic para seleccionar archivo</div>
                </div>
                <div id="polizaFileInfo" style="display:none;align-items:center;gap:10px;padding:10px;background:var(--bg-secondary);border-radius:8px;margin-top:6px;">
                    <span id="polizaFileName" style="flex:1;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                    <button data-action="det-quitarPoliza" style="background:none;border:none;color:var(--danger-color,#ef4444);cursor:pointer;">✕</button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalSubirPoliza">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarPoliza" data-action="det-guardarPoliza">💾 Guardar Póliza</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Editar Póliza -->
<?php if ($puedeGestionarPolizas): ?>
<div class="modal-overlay" id="modalEditarPoliza">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header"><h3 class="modal-title">✏️ Editar Póliza</h3><button class="modal-close" data-close="modalEditarPoliza">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="editPolizaId">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Compañía</label>
                    <select class="form-select" id="editPolizaCompania">
                        <?php foreach(['GNP','Qualitas','AXA','HDI','Mapfre','Zurich','Banorte','Chubb','Ana Seguros','Inbursa','Otra'] as $c): ?>
                        <option><?php echo $c; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Número de Póliza</label><input type="text" class="form-input" id="editPolizaNumero"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Inicio</label><input type="date" class="form-input" id="editPolizaInicio"></div>
                <div class="form-group"><label class="form-label">Fin</label><input type="date" class="form-input" id="editPolizaFin"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Frecuencia</label>
                    <select class="form-select" id="editPolizaFrecuencia">
                        <option value="anual">Anual</option><option value="trimestral">Trimestral</option>
                        <option value="mensual">Mensual</option><option value="semanal">Semanal</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Estado</label>
                    <select class="form-select" id="editPolizaEstado">
                        <option value="vigente">✅ Vigente</option><option value="por_vencer">⚠️ Por vencer</option>
                        <option value="vencida">❌ Vencida</option><option value="cancelada">🚫 Cancelada</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Notas</label><textarea class="form-textarea" id="editPolizaNotas" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEditarPoliza">Cancelar</button>
            <button class="btn btn-primary" data-action="det-guardarEdicionPoliza">Guardar Cambios</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Modal: Solicitar Renovación de Póliza ─────────────────────────── -->
<div class="modal-overlay" id="modalRenovacion">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">🔄 Solicitar Renovación de Póliza</h3>
            <button class="modal-close" data-close="modalRenovacion">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="renovPolizaId" value="">
            <div style="background:var(--info-light,#eff6ff);border:1px solid var(--info-color,#3b82f6);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
                <strong>📄 Póliza:</strong> <span id="renovPolizaNum" style="color:var(--accent-color);font-weight:600;"></span>
            </div>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;line-height:1.55;">
                Al solicitar la renovación, esta póliza quedará marcada como <strong>Por vencer</strong> y se registrará en el historial del cliente para darle seguimiento.
            </p>
            <div class="form-group">
                <label class="form-label">Comentario adicional (opcional)</label>
                <textarea class="form-input" id="renovComentarios" rows="3" placeholder="Ej: Cliente interesado en ampliar cobertura, cambio de vehículo…" style="resize:vertical;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalRenovacion">Cancelar</button>
            <button class="btn btn-primary" id="btnConfirmarRenovacion" data-action="det-confirmarRenovacion">🔄 Confirmar Renovación</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script nonce="<?= $cspNonce ?>">
const clienteId = <?php echo $cliente['id']; ?>;
document.getElementById('citaTipo')?.addEventListener('change', function(ev){ toggleEnlaceMeet(ev.target.value); });
document.getElementById('polizaFile')?.addEventListener('change', function(){ onPolizaFile(this); });
function openModal(id){const m=document.getElementById(id);if(m){m.classList.add('active');document.body.style.overflow='hidden';}}
function closeModal(id){const m=document.getElementById(id);if(m){m.classList.remove('active');document.body.style.overflow='';}}
document.addEventListener('click',e=>{if(e.target.classList.contains('modal-overlay')){e.target.classList.remove('active');document.body.style.overflow='';}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){const a=document.querySelector('.modal-overlay.active');if(a){a.classList.remove('active');document.body.style.overflow='';}}});
function toggleEnlaceMeet(t){const g=document.getElementById('grupoEnlaceMeet');if(g)g.style.display=t==='videollamada'?'block':'none';if(t!=='videollamada'){const i=document.getElementById('citaEnlace');if(i)i.value='';}}
function generarMeetLink(){window.open('https://meet.google.com/new','_blank');setTimeout(()=>{const i=document.getElementById('citaEnlace');if(i&&!i.value)i.focus();},1500);}
function postJSON(data){const fd=new FormData();Object.entries(data).forEach(([k,v])=>fd.append(k,v));return fetch(`/cliente/${clienteId}`,{method:'POST',body:fd}).then(r=>r.json());}
function guardarNota(){const t=document.getElementById('notaTexto').value.trim();if(!t){showToast('Escribe una nota','error');return;}postJSON({accion:'nota',texto:t}).then(d=>{if(d.ok){showToast('Nota guardada','success');closeModal('modalNota');setTimeout(()=>location.reload(),900);}else showToast(d.msg||'Error','error');}).catch(()=>showToast('Error de conexión','error'));}
function guardarCita(){const fecha=document.getElementById('citaFecha').value,hora=document.getElementById('citaHora').value,tipo=document.getElementById('citaTipo').value,com=document.getElementById('citaComentarios').value;if(!fecha||!hora){showToast('Indica fecha y hora','error');return;}const enlace=document.getElementById('citaEnlace')?.value||'';const fd=new FormData();fd.append('accion','cita');fd.append('tipo',tipo);fd.append('fecha',fecha);fd.append('hora',hora);fd.append('comentarios',com);fd.append('enlace_meet',enlace);fetch(`/cliente/${clienteId}`,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.ok){showToast('Cita programada','success');closeModal('modalCita');}else showToast(d.msg||'Error','error');}).catch(()=>showToast('Error de conexión','error'));}
function cambiarEstadoCliente(estado){if(!estado)return;postJSON({accion:'cambiar_estado',estado}).then(d=>{if(d.ok){showToast('Estado actualizado','success');setTimeout(()=>location.reload(),900);}else showToast(d.msg||'Error','error');}).catch(()=>showToast('Error de conexión','error'));document.getElementById('selectEstadoCliente').value='';}
function onPolizaFile(input){const file=input.files[0];if(!file)return;if(file.size>20*1024*1024){showToast('El archivo supera 20MB','error');input.value='';return;}document.getElementById('polizaFileName').textContent='📎 '+file.name;document.getElementById('polizaFileInfo').style.display='flex';document.getElementById('polizaDropZone').style.borderColor='var(--success-color,#22c55e)';}
function quitarPolizaFile(){document.getElementById('polizaFile').value='';document.getElementById('polizaFileInfo').style.display='none';document.getElementById('polizaDropZone').style.borderColor='var(--border-color,#e5e7eb)';}
function guardarPoliza(){const file=document.getElementById('polizaFile').files[0];if(!file){showToast('Selecciona un archivo','error');return;}const btn=document.getElementById('btnGuardarPoliza');btn.disabled=true;btn.textContent='Subiendo...';const fd=new FormData();fd.append('accion','subir_poliza');fd.append('archivo_poliza',file);fd.append('compania',document.getElementById('polizaCompania').value);fd.append('numero_poliza',document.getElementById('polizaNumero').value);fd.append('fecha_inicio',document.getElementById('polizaFechaInicio').value);fd.append('fecha_fin',document.getElementById('polizaFechaFin').value);fd.append('frecuencia_renovacion',document.getElementById('polizaFrecuencia').value);fd.append('cotizacion_id',document.getElementById('polizaCotizacion').value);fd.append('notas',document.getElementById('polizaNotas').value);fetch(`/cliente/${clienteId}`,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{btn.disabled=false;btn.textContent='💾 Guardar Póliza';if(d.ok){showToast('Póliza guardada'+(d.en_idrive?' · ☁️ iDrive':''),'success');closeModal('modalSubirPoliza');setTimeout(()=>location.reload(),900);}else showToast(d.msg||'Error al guardar','error');}).catch(()=>{btn.disabled=false;btn.textContent='💾 Guardar Póliza';showToast('Error de conexión','error');});}
function editarPoliza(pol){document.getElementById('editPolizaId').value=pol.id;document.getElementById('editPolizaCompania').value=pol.compania||'';document.getElementById('editPolizaNumero').value=pol.numero_poliza||'';document.getElementById('editPolizaInicio').value=pol.fecha_inicio||'';document.getElementById('editPolizaFin').value=pol.fecha_fin||'';document.getElementById('editPolizaFrecuencia').value=pol.frecuencia_renovacion||'anual';document.getElementById('editPolizaEstado').value=pol.estado||'vigente';document.getElementById('editPolizaNotas').value=pol.notas||'';openModal('modalEditarPoliza');}
function guardarEdicionPoliza(){const id=document.getElementById('editPolizaId').value;const fd=new FormData();fd.append('accion','editar_poliza');fd.append('poliza_id',id);fd.append('compania',document.getElementById('editPolizaCompania').value);fd.append('numero_poliza',document.getElementById('editPolizaNumero').value);fd.append('fecha_inicio',document.getElementById('editPolizaInicio').value);fd.append('fecha_fin',document.getElementById('editPolizaFin').value);fd.append('frecuencia_renovacion',document.getElementById('editPolizaFrecuencia').value);fd.append('estado',document.getElementById('editPolizaEstado').value);fd.append('notas',document.getElementById('editPolizaNotas').value);fetch(`/cliente/${clienteId}`,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.ok){showToast('Póliza actualizada','success');closeModal('modalEditarPoliza');setTimeout(()=>location.reload(),900);}else showToast(d.msg||'Error','error');}).catch(()=>showToast('Error de conexión','error'));}
function eliminarPoliza(id){if(!confirm('¿Eliminar esta póliza? No se puede deshacer.'))return;postJSON({accion:'eliminar_poliza',poliza_id:id}).then(d=>{if(d.ok){showToast('Póliza eliminada','success');setTimeout(()=>location.reload(),900);}else showToast(d.msg||'Error','error');}).catch(()=>showToast('Error de conexión','error'));}
function eliminarArchivoCot(archivoId,btn){if(!confirm('¿Eliminar este archivo?'))return;btn.disabled=true;postJSON({accion:'eliminar_archivo_cot',archivo_id:archivoId}).then(d=>{if(d.ok){showToast('Archivo eliminado','success');btn.closest('div[style]')?.remove();}else{btn.disabled=false;showToast(d.msg||'Error','error');}}).catch(()=>{btn.disabled=false;showToast('Error de conexión','error');});}
function showToast(msg,type='success'){const c=document.getElementById('toastContainer');if(!c)return;const t=document.createElement('div');t.className=`toast ${type}`;t.innerHTML=`<div class="toast-icon">${type==='error'?'✗':'✓'}</div><div class="toast-message">${msg}</div><button class="toast-close" data-action="toast-close">&times;</button>`;c.appendChild(t);setTimeout(()=>{t.classList.add('hiding');setTimeout(()=>t.remove(),300);},5000);}
</script>
<script nonce="<?= $cspNonce ?>">
    document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="abrir-emision"]');
    if (btn) {
        const cotId = btn.getAttribute('data-cot-id');
        const campo = document.getElementById('polizaCotizacion');
        if (campo) campo.value = cotId;
        
        // Hacer que el input de archivo del modal sea estrictamente obligatorio
        const inputArchivo = document.querySelector('#modalSubirPoliza input[type="file"]');
        if (inputArchivo) inputArchivo.required = true;

        // Abrir la ventana
        if (typeof openModal === 'function') openModal('modalSubirPoliza');
    }
});
// ── Historial paginación (10 por página) — botones sin inline handlers (CSP) ──
(function(){
    const feed=document.getElementById('histFeed');
    const pag=document.getElementById('histPag');
    if(!feed||!pag) return;
    const items=[...feed.querySelectorAll('.hist-item')];
    const PP=10; let cp=1;
    const pages=()=>Math.max(1,Math.ceil(items.length/PP));
    function render(){
        const p=pages();
        items.forEach((el,i)=>{el.style.display=(i>=(cp-1)*PP&&i<cp*PP)?'':'none';});
        pag.innerHTML='';
        if(p<=1) return;
        const mk=function(label,targetPage,isDisabled,isActive){
            const b=document.createElement('button');
            b.type='button';
            b.textContent=label;
            if(isDisabled)b.disabled=true;
            b.dataset.histPage=String(targetPage);
            b.style.padding='4px 10px';
            b.style.borderRadius='6px';
            b.style.border='1px solid var(--border-color)';
            b.style.cursor=isDisabled?'not-allowed':'pointer';
            b.style.background=isActive?'var(--accent-color)':'#fff';
            b.style.color=isActive?'#fff':'inherit';
            pag.appendChild(b);
        };
        mk('‹',cp-1,cp===1,false);
        for(let i=1;i<=p;i++)mk(String(i),i,false,i===cp);
        mk('›',cp+1,cp===p,false);
    }
    pag.addEventListener('click',function(e){
        const btn=e.target.closest('button[data-hist-page]');
        if(!btn||btn.disabled) return;
        const target=parseInt(btn.dataset.histPage,10);
        if(isNaN(target)) return;
        cp=Math.max(1,Math.min(target,pages()));
        render();
    });
    render();
})();

// ── Cotizaciones: acordeón numerado (una a la vez) ────────────────────────────
(function(){
    const items=document.querySelectorAll('.cot-item');
    const navEl=document.getElementById('cotNav');
    if(!items.length||!navEl) return;
    let curr=0;
    function show(idx){
        items.forEach((el,i)=>{el.style.display=(i===idx)?'':'none';});
        curr=idx;
        navEl.querySelector('#cotLabel').textContent=(idx+1)+' / '+items.length;
        navEl.querySelector('#cotPrev').disabled=(idx===0);
        navEl.querySelector('#cotNext').disabled=(idx===items.length-1);
    }
    navEl.querySelector('#cotPrev').addEventListener('click',()=>show(Math.max(0,curr-1)));
    navEl.querySelector('#cotNext').addEventListener('click',()=>show(Math.min(items.length-1,curr+1)));
    show(0);
})();
</script>

<!-- Modal Confirmar Pago -->
<div class="modal-overlay" id="modalConfirmarPago">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <h3 class="modal-title">&#128176; Confirmar Pago</h3>
            <button class="modal-close" data-close="modalConfirmarPago">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Info de la cotizacion -->
            <div style="background:var(--info-light,#eff6ff);border:1px solid var(--accent-color,#0ea5e9);border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                <div id="pagoFolio" style="font-family:'Courier New',monospace;font-size:12px;font-weight:700;color:var(--accent-color);margin-bottom:4px;"></div>
                <div id="pagoResumen" style="font-size:13px;font-weight:600;"></div>
                <div id="pagoPrecio" style="font-size:20px;font-weight:800;color:var(--success-color,#22c55e);margin-top:4px;"></div>
            </div>
            <input type="hidden" id="pagoCotizacionId" value="">

            <!-- Metodo de pago -->
            <div class="form-group">
                <label class="form-label required">Metodo de Pago</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:4px;">
                    <div class="metodo-pago-card" id="mpCard_spei" data-action="det-metodoPago" data-metodo="spei"
                         style="border:2px solid var(--border-color);border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:all .2s;">
                        <div style="font-size:20px;">&#127970;</div>
                        <div style="font-size:11px;font-weight:700;margin-top:3px;">SPEI</div>
                        <div style="font-size:10px;color:var(--text-muted);">Transferencia</div>
                    </div>
                    <div class="metodo-pago-card" id="mpCard_contado" data-action="det-metodoPago" data-metodo="contado"
                         style="border:2px solid var(--border-color);border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:all .2s;">
                        <div style="font-size:20px;">&#128181;</div>
                        <div style="font-size:11px;font-weight:700;margin-top:3px;">Efectivo</div>
                        <div style="font-size:10px;color:var(--text-muted);">En sucursal</div>
                    </div>
                    <div class="metodo-pago-card" id="mpCard_meses_sin_intereses" data-action="det-metodoPago" data-metodo="meses_sin_intereses"
                         style="border:2px solid var(--border-color);border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:all .2s;">
                        <div style="font-size:20px;">&#128179;</div>
                        <div style="font-size:11px;font-weight:700;margin-top:3px;">MSI</div>
                        <div style="font-size:10px;color:var(--text-muted);">Meses sin intereses</div>
                    </div>
                </div>
            </div>

            <!-- Aviso segun metodo -->
            <div id="pagoAvisoTransf" style="display:none;padding:10px 14px;background:#fef9c3;border:1px solid #ca8a04;border-radius:8px;font-size:12px;margin-bottom:12px;">
                &#9888;&#65039; <strong>Comprobante obligatorio:</strong> Para SPEI o MSI debes subir el recibo o comprobante de pago.
            </div>
            <div id="pagoAvisoEfectivo" style="display:none;padding:10px 14px;background:#f0fdf4;border:1px solid #16a34a;border-radius:8px;font-size:12px;margin-bottom:12px;">
                &#128722; <strong>Pago en sucursal:</strong> El cliente realizo el pago en efectivo. No se requiere comprobante digital.
            </div>

            <!-- Upload comprobante (solo spei/msi) -->
            <div id="pagoComprobanteGrupo" style="display:none;" class="form-group">
                <label class="form-label required">Comprobante de pago <span style="font-size:10px;color:var(--text-muted);">PDF, JPG o PNG &middot; max 15MB</span></label>
                <div id="pagoDropZone" data-action="det-triggerPagoFile"
                     style="border:2px dashed var(--border-color);border-radius:8px;padding:16px;text-align:center;cursor:pointer;background:var(--bg-secondary);transition:all .2s;">
                    <input type="file" id="pagoComprobanteFile" accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                    <div style="font-size:24px;margin-bottom:4px;">&#128196;</div>
                    <div style="font-size:12px;color:var(--text-muted);">Clic para seleccionar archivo</div>
                </div>
                <div id="pagoFileInfo" style="display:none;align-items:center;gap:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #16a34a;border-radius:6px;margin-top:6px;font-size:12px;">
                    <span>&#128206;</span><span id="pagoFileName" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                    <button data-action="det-quitarPago" style="background:none;border:none;color:var(--danger-color,#ef4444);cursor:pointer;font-size:14px;">&times;</button>
                </div>
            </div>

            <!-- Notas opcionales -->
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Notas (opcional)</label>
                <textarea class="form-textarea" id="pagoNotas" rows="2" placeholder="Referencia de transferencia, numero de meses, observaciones..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalConfirmarPago">Cancelar</button>
            <button class="btn btn-success" id="btnGuardarPago" data-action="det-guardarPago">&#10003; Confirmar Pago</button>
        </div>
    </div>
</div>


<script nonce="<?= $cspNonce ?>">
// ─── Modal Confirmar Pago ───────────────────────────────────────────────────
let _metodoPagoSel = '';

function abrirModalPago(cot) {
    _metodoPagoSel = '';
    document.getElementById('pagoCotizacionId').value = cot.id;
    document.getElementById('pagoFolio').textContent   = cot.folio ? 'Folio: ' + cot.folio : 'Cotizacion #' + cot.id;
    document.getElementById('pagoResumen').textContent = cot.compania;
    document.getElementById('pagoPrecio').textContent  = '$' + parseFloat(cot.precio).toLocaleString('es-MX', {minimumFractionDigits: 2});
    // Reset metodo cards
    ['spei','contado','meses_sin_intereses'].forEach(m => {
        const c = document.getElementById('mpCard_' + m);
        if (c) { c.style.borderColor = 'var(--border-color)'; c.style.background = ''; }
    });
    // Si la cot ya tenia un metodo preseleccionado, seleccionarlo
    if (cot.metodo_pago) selMetodoPago(cot.metodo_pago);
    // Reset avisos y comprobante
    document.getElementById('pagoAvisoTransf').style.display    = 'none';
    document.getElementById('pagoAvisoEfectivo').style.display  = 'none';
    document.getElementById('pagoComprobanteGrupo').style.display = 'none';
    document.getElementById('pagoFileInfo').style.display = 'none';
    document.getElementById('pagoComprobanteFile').value = '';
    document.getElementById('pagoNotas').value = '';
    const btn = document.getElementById('btnGuardarPago');
    btn.disabled = false; btn.textContent = '✓ Confirmar Pago';
    openModal('modalConfirmarPago');
}

function selMetodoPago(m) {
    ['spei','contado','meses_sin_intereses'].forEach(k => {
        const c = document.getElementById('mpCard_' + k);
        if (c) { c.style.borderColor = k === m ? 'var(--accent-color,#0ea5e9)' : 'var(--border-color)';
                 c.style.background  = k === m ? 'var(--info-light,#eff6ff)' : ''; }
    });
    _metodoPagoSel = m;
    const requiere = (m === 'spei' || m === 'meses_sin_intereses');
    document.getElementById('pagoAvisoTransf').style.display    = requiere ? '' : 'none';
    document.getElementById('pagoAvisoEfectivo').style.display  = m === 'contado' ? '' : 'none';
    document.getElementById('pagoComprobanteGrupo').style.display = requiere ? '' : 'none';
}

function onPagoFileChange(input) {
    const file = input.files[0]; if (!file) return;
    if (file.size > 15*1024*1024) { showToast('El archivo supera 15MB', 'error'); input.value = ''; return; }
    document.getElementById('pagoFileName').textContent = file.name;
    document.getElementById('pagoFileInfo').style.display = 'flex';
    document.getElementById('pagoDropZone').style.borderColor = 'var(--success-color,#22c55e)';
}
function quitarPagoFile() {
    document.getElementById('pagoComprobanteFile').value = '';
    document.getElementById('pagoFileInfo').style.display = 'none';
    document.getElementById('pagoDropZone').style.borderColor = 'var(--border-color)';
}

function guardarPago() {
    const cotId = document.getElementById('pagoCotizacionId').value;
    if (!_metodoPagoSel) { showToast('Selecciona el metodo de pago', 'error'); return; }
    const requiere = (_metodoPagoSel === 'spei' || _metodoPagoSel === 'meses_sin_intereses');
    const file = document.getElementById('pagoComprobanteFile').files[0];
    if (requiere && !file) { showToast('Debes subir el comprobante de pago', 'error'); return; }

    const btn = document.getElementById('btnGuardarPago');
    btn.disabled = true; btn.textContent = 'Guardando...';

    const fd = new FormData();
    fd.append('accion', 'confirmar_pago');
    fd.append('cotizacion_id', cotId);
    fd.append('metodo_pago', _metodoPagoSel);
    fd.append('notas_pago', document.getElementById('pagoNotas').value);
    if (file) fd.append('comprobante_pago', file);

    fetch(`/cliente/${clienteId}`, {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                showToast('Pago registrado correctamente', 'success');
                closeModal('modalConfirmarPago');
                setTimeout(() => location.reload(), 1000);
            } else {
                btn.disabled = false; btn.textContent = '✓ Confirmar Pago';
                showToast(d.msg || 'Error al registrar el pago', 'error');
            }
        })
        .catch(() => { btn.disabled = false; btn.textContent = '✓ Confirmar Pago'; showToast('Error de conexion', 'error'); });
}
document.getElementById('pagoComprobanteFile')?.addEventListener('change', function(){ onPagoFileChange(this); });
</script>
<!-- ═══ MODAL EDITAR CLIENTE ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEditarCliente">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Editar Datos del Cliente</h3>
            <button class="modal-close" data-close="modalEditarCliente">&times;</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">

            <!-- Datos personales -->
            <div style="font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">Datos personales</div>
            <div class="form-row">
                <div class="form-group"><label class="form-label required">Nombre</label>
                    <input type="text" class="form-input" id="ec_nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>"></div>
                <div class="form-group"><label class="form-label required">Ap. Paterno</label>
                    <input type="text" class="form-input" id="ec_apellido_paterno" value="<?php echo htmlspecialchars($cliente['apellido_paterno']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Ap. Materno</label>
                    <input type="text" class="form-input" id="ec_apellido_materno" value="<?php echo htmlspecialchars($cliente['apellido_materno']??''); ?>"></div>
                <div class="form-group"><label class="form-label required">Correo</label>
                    <input type="email" class="form-input" id="ec_correo" value="<?php echo htmlspecialchars($cliente['correo']); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Teléfono</label>
                    <input type="text" class="form-input" id="ec_telefono" value="<?php echo htmlspecialchars($cliente['telefono']??''); ?>"></div>
                <div class="form-group"><label class="form-label">Fecha de nacimiento</label>
                    <input type="date" class="form-input" id="ec_fecha_nacimiento" value="<?php echo htmlspecialchars($cliente['fecha_nacimiento']??''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Sexo</label>
                    <select class="form-select" id="ec_sexo">
                        <option value="">— —</option>
                        <option value="masculino" <?php echo ($cliente['sexo']??'')==='masculino'?'selected':''; ?>>Masculino</option>
                        <option value="femenino"  <?php echo ($cliente['sexo']??'')==='femenino' ?'selected':''; ?>>Femenino</option>
                    </select></div>
                <div class="form-group"><label class="form-label">Estado Civil</label>
                    <select class="form-select" id="ec_estado_civil">
                        <option value="">— —</option>
                        <?php foreach(['soltero','casado','divorciado','viudo','union_libre'] as $ec): ?>
                        <option value="<?php echo $ec; ?>" <?php echo ($cliente['estado_civil']??'')===$ec?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$ec)); ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>

            <!-- Dirección -->
            <div style="font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.5px;margin:16px 0 10px;padding-top:14px;border-top:1px solid var(--border-color);">Dirección</div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Calle</label>
                    <input type="text" class="form-input" id="ec_calle" value="<?php echo htmlspecialchars($cliente['calle']??''); ?>"></div>
                <div class="form-group"><label class="form-label">Colonia</label>
                    <input type="text" class="form-input" id="ec_colonia" value="<?php echo htmlspecialchars($cliente['colonia']??''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Ciudad</label>
                    <input type="text" class="form-input" id="ec_ciudad" value="<?php echo htmlspecialchars($cliente['ciudad']??''); ?>"></div>
                <div class="form-group"><label class="form-label">Estado</label>
                    <input type="text" class="form-input" id="ec_estado" value="<?php echo htmlspecialchars($cliente['estado']??''); ?>"></div>
                <div class="form-group"><label class="form-label">C.P.</label>
                    <input type="text" class="form-input" id="ec_codigo_postal" maxlength="5" value="<?php echo htmlspecialchars($cliente['codigo_postal']??''); ?>"></div>
            </div>

            <?php $ramoCliente = $cliente['ramo'] ?? 'autos'; ?>

            <!-- Campos extra por ramo -->
            <?php if ($ramoCliente === 'autos'): ?>
            <div style="font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.5px;margin:16px 0 10px;padding-top:14px;border-top:1px solid var(--border-color);">🚗 Vehículo</div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Marca</label>
                    <input type="text" class="form-input" id="ec_marca" value="<?php echo htmlspecialchars($cliente['marca']??''); ?>"></div>
                <div class="form-group"><label class="form-label">Modelo</label>
                    <input type="text" class="form-input" id="ec_modelo" value="<?php echo htmlspecialchars($cliente['modelo']??''); ?>"></div>
                <div class="form-group"><label class="form-label">Año</label>
                    <input type="number" class="form-input" id="ec_anio_vehiculo" min="1990" max="<?php echo date('Y')+1; ?>" value="<?php echo htmlspecialchars((string)($cliente['anio_vehiculo']??'')); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Tipo de vehículo</label>
                    <select class="form-select" id="ec_tipo_vehiculo">
                        <?php foreach(['auto'=>'Auto','suv_camioneta'=>'SUV/Camioneta','pickup'=>'Pickup','moto'=>'Moto','deportivo'=>'Deportivo','electrico'=>'Eléctrico','van'=>'Van/Minivan'] as $v=>$l): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($cliente['tipo_vehiculo']??'')===$v?'selected':''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">¿Ya tiene seguro?</label>
                    <select class="form-select" id="ec_asegurado">
                        <option value="si"  <?php echo ($cliente['asegurado'] ?'selected':''); ?>>Sí</option>
                        <option value="no"  <?php echo (!$cliente['asegurado']?'selected':''); ?>>No</option>
                    </select></div>
                <div class="form-group"><label class="form-label">Intención de compra</label>
                    <select class="form-select" id="ec_tiempo_compra">
                        <?php foreach(['inmediato'=>'Inmediato','esta_semana'=>'Esta semana','este_mes'=>'Este mes','solo_cotizando'=>'Solo cotizando'] as $v=>$l): ?>
                        <option value="<?php echo $v; ?>" <?php echo ($cliente['tiempo_compra']??'')===$v?'selected':''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <?php endif; ?>

            <?php if ($ramoCliente === 'empresas'): ?>
            <div style="font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.5px;margin:16px 0 10px;padding-top:14px;border-top:1px solid var(--border-color);">🏢 Datos Empresariales</div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Giro / Industria</label>
                    <input type="text" class="form-input" id="ec_giro_empresa" value="<?php echo htmlspecialchars($cliente['giro_empresa']??''); ?>"></div>
                <div class="form-group"><label class="form-label">Núm. empleados</label>
                    <input type="number" class="form-input" id="ec_num_empleados" min="1" value="<?php echo htmlspecialchars((string)($cliente['num_empleados']??'')); ?>"></div>
                <div class="form-group"><label class="form-label">Facturación anual (MXN)</label>
                    <input type="number" class="form-input" id="ec_facturacion_anual" min="0" value="<?php echo htmlspecialchars((string)($cliente['facturacion_anual']??'')); ?>"></div>
            </div>
            <?php endif; ?>

        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEditarCliente">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarEdicionCliente" data-action="det-guardarEdicionCliente">💾 Guardar Cambios</button>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
// ── Fix CSP: listener del select de estado cliente ─────────────────────────
// Reemplaza el onchange="cambiarEstadoCliente(this.value)" eliminado del HTML.
document.getElementById('selectEstadoCliente')?.addEventListener('change', function () {
    if (typeof cambiarEstadoCliente === 'function') {
        cambiarEstadoCliente(this.value);
    }
});
</script>

<script nonce="<?= $cspNonce ?>">
function guardarEdicionCliente() {
    const btn = document.getElementById('btnGuardarEdicionCliente');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const fd = new FormData();
    fd.append('accion',            'editar_cliente');
    fd.append('nombre',            document.getElementById('ec_nombre').value.trim());
    fd.append('apellido_paterno',  document.getElementById('ec_apellido_paterno').value.trim());
    fd.append('apellido_materno',  document.getElementById('ec_apellido_materno')?.value.trim() ?? '');
    fd.append('correo',            document.getElementById('ec_correo').value.trim());
    fd.append('telefono',          document.getElementById('ec_telefono')?.value.trim() ?? '');
    fd.append('fecha_nacimiento',  document.getElementById('ec_fecha_nacimiento')?.value ?? '');
    fd.append('sexo',              document.getElementById('ec_sexo')?.value ?? '');
    fd.append('estado_civil',      document.getElementById('ec_estado_civil')?.value ?? '');
    fd.append('calle',             document.getElementById('ec_calle')?.value.trim() ?? '');
    fd.append('colonia',           document.getElementById('ec_colonia')?.value.trim() ?? '');
    fd.append('ciudad',            document.getElementById('ec_ciudad')?.value.trim() ?? '');
    fd.append('estado',            document.getElementById('ec_estado')?.value.trim() ?? '');
    fd.append('codigo_postal',     document.getElementById('ec_codigo_postal')?.value.trim() ?? '');

    // Campos extra por ramo (solo si existen en el DOM)
    const campos = ['marca','modelo','anio_vehiculo','tipo_vehiculo','asegurado','tiempo_compra',
                    'giro_empresa','num_empleados','facturacion_anual'];
    campos.forEach(c => {
        const el = document.getElementById('ec_' + c);
        if (el) fd.append(c, el.value);
    });

    fetch(`/cliente/${clienteId}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = '💾 Guardar Cambios';
            if (d.ok) {
                showToast('Datos actualizados correctamente', 'success');
                closeModal('modalEditarCliente');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(d.msg || 'Error al guardar', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = '💾 Guardar Cambios';
            showToast('Error de conexión', 'error');
        });
}
</script>

<script nonce="<?= $cspNonce ?>">
// ── Vehículo: toggle edición ──────────────────────────────────────────────────
(function(){
    const btnEdit   = document.getElementById('btnEditarVehiculo');
    const btnCancel = document.getElementById('btnCancelarVehiculo');
    const btnSave   = document.getElementById('btnGuardarVehiculo');
    const viewMode  = document.getElementById('vehiculoViewMode');
    const editMode  = document.getElementById('vehiculoEditMode');
    if (!btnEdit) return;
    btnEdit.addEventListener('click', () => {
        viewMode.style.display = 'none';
        editMode.style.display = '';
        btnEdit.style.display  = 'none';
    });
    if (btnCancel) btnCancel.addEventListener('click', () => {
        viewMode.style.display = '';
        editMode.style.display = 'none';
        btnEdit.style.display  = '';
    });
    if (btnSave) btnSave.addEventListener('click', () => {
        btnSave.disabled = true;
        btnSave.textContent = 'Guardando…';
        const fd = new FormData();
        fd.append('accion', 'editar_cliente');
        const form = editMode;
        // Campos principales (nombres exactos que espera el PHP handler)
        fd.append('marca',         form.querySelector('[name="vehiculo_marca"]')?.value ?? '');
        fd.append('modelo',        form.querySelector('[name="vehiculo_submarca"]')?.value ?? ''); // submarca→modelo en BD
        fd.append('anio_vehiculo', form.querySelector('[name="vehiculo_anio"]')?.value ?? '');
        fd.append('tipo_vehiculo', form.querySelector('[name="vehiculo_tipo"]')?.value ?? '');
        // Campos adicionales (se guardan en detalle_adaptaciones JSON)
        fd.append('color',      form.querySelector('[name="vehiculo_color"]')?.value ?? '');
        fd.append('placas',     form.querySelector('[name="vehiculo_placas"]')?.value ?? '');
        fd.append('vin',        form.querySelector('[name="vehiculo_vin"]')?.value ?? '');
        fd.append('rines_lujo', form.querySelector('[name="vehiculo_rines_lujo"]')?.value ?? '');
        fd.append('gps',        form.querySelector('[name="vehiculo_gps"]')?.value ?? '');
        fd.append('uso_auto',   form.querySelector('[name="vehiculo_uso_auto"]')?.value ?? '');
        fd.append('_solo_vehiculo', '1');
        fetch(`/cliente/<?= $clienteId ?>`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                btnSave.disabled = false;
                btnSave.textContent = '💾 Guardar vehículo';
                if (d.ok) {
                    showToast('Vehículo actualizado', 'success');
                    setTimeout(() => location.reload(), 900);
                } else {
                    showToast(d.msg || 'Error al guardar', 'error');
                }
            })
            .catch(() => {
                btnSave.disabled = false;
                btnSave.textContent = '💾 Guardar vehículo';
                showToast('Error de conexión', 'error');
            });
    });
})();
</script>

<script nonce="<?= $cspNonce ?>">
// ── Delegación de eventos detalle.php ────────────────────────────────────────
document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-action],[data-close]');
    if (!el) return;

    if (el.dataset.close) {
        if (typeof closeModal === 'function') closeModal(el.dataset.close);
        return;
    }

    const action = el.dataset.action;
    if (!action) return;

    switch (action) {
        case 'det-open':
            if (typeof openModal === 'function') openModal(el.dataset.modal);
            break;
        case 'det-guardarNota':       guardarNota && guardarNota(); break;
        case 'det-guardarCita':       guardarCita && guardarCita(); break;
        case 'det-guardarPoliza':     guardarPoliza && guardarPoliza(); break;
        case 'det-guardarEdicionPoliza':   guardarEdicionPoliza && guardarEdicionPoliza(); break;
        case 'det-guardarEdicionCliente':  guardarEdicionCliente && guardarEdicionCliente(); break;
        case 'det-guardarPago':       guardarPago && guardarPago(); break;
        case 'det-generarMeet':       generarMeetLink && generarMeetLink(); break;
        case 'det-quitarPoliza':      quitarPolizaFile && quitarPolizaFile(); break;
        case 'det-quitarPago':        quitarPagoFile && quitarPagoFile(); break;
        case 'det-triggerPolizaFile': document.getElementById('polizaFile')?.click(); break;
        case 'det-triggerPagoFile':   document.getElementById('pagoComprobanteFile')?.click(); break;
        case 'det-metodoPago':
            if (typeof selMetodoPago === 'function') selMetodoPago(el.dataset.metodo);
            break;
        case 'det-eliminarPoliza':
            if (typeof eliminarPoliza === 'function') eliminarPoliza(parseInt(el.dataset.id));
            break;
        case 'det-editarPoliza':
            try { editarPoliza && editarPoliza(JSON.parse(el.dataset.pol)); }
            catch(err) { console.error('editarPoliza', err); }
            break;
        case 'det-solicitarRenovacion': {
            const polId  = el.dataset.polizaId;
            const polNum = el.dataset.polizaNum;
            document.getElementById('renovPolizaId').value = polId;
            document.getElementById('renovPolizaNum').textContent = polNum;
            document.getElementById('renovComentarios').value = '';
            if (typeof openModal === 'function') openModal('modalRenovacion');
            break;
        }
        case 'det-confirmarRenovacion':
            confirmarRenovacion();
            break;
        case 'det-abrirPago':
            try { abrirModalPago && abrirModalPago(JSON.parse(el.dataset.cot)); }
            catch(err) { console.error('abrirModalPago', err); }
            break;
        case 'det-eliminarArchivo':
            if (typeof eliminarArchivoCot === 'function') eliminarArchivoCot(parseInt(el.dataset.id), el);
            break;
        case 'det-enviarCoordinador': {
            const cotId  = el.dataset.cotId;
            const folio  = el.dataset.folio || '#' + cotId;
            if (!confirm(`¿Enviar el expediente "${folio}" al coordinador para emisión y cobro?\n\nEsto le notificará que los documentos están listos.`)) break;
            el.disabled = true;
            el.textContent = 'Enviando…';
            const fd = new FormData();
            fd.append('accion', 'enviar_coordinador');
            fd.append('cot_id', cotId);
            fetch(`/cliente/${clienteId}`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    el.disabled = false;
                    el.textContent = '📝 Emitir y Subir Póliza';
                    if (d.ok) {
                        showToast(d.msg || 'Expediente enviado a Emisión', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(d.msg || 'Error al enviar', 'error');
                    }
                })
                .catch(() => {
                    el.disabled = false;
                    el.textContent = '📝 Emitir y Subir Póliza';
                    showToast('Error de conexión', 'error');
                });
            break;
        }
        case 'toast-close':
            el.closest('.toast')?.remove();
            break;
    }
});

function confirmarRenovacion() {
    const btn  = document.getElementById('btnConfirmarRenovacion');
    const polizaId   = document.getElementById('renovPolizaId').value;
    const comentarios= document.getElementById('renovComentarios').value.trim();
    if (!polizaId) { showToast('Error: no hay póliza seleccionada', 'error'); return; }
    btn.disabled = true;
    btn.textContent = 'Procesando…';
    const fd = new FormData();
    fd.append('accion', 'solicitar_renovacion');
    fd.append('poliza_id', polizaId);
    fd.append('comentarios', comentarios);
    fetch(`/cliente/${clienteId}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = '🔄 Confirmar Renovación';
            if (d.ok) {
                showToast('Renovación registrada en el historial del cliente', 'success');
                if (typeof closeModal === 'function') closeModal('modalRenovacion');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(d.msg || 'Error al registrar renovación', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = '🔄 Confirmar Renovación';
            showToast('Error de conexión', 'error');
        });
}
</script>
</body>
</html>
