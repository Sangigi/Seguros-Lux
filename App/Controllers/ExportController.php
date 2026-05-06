<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\SessionManager;
use App\Models\{CotizacionModel, AgentModel};

class ExportController
{
    public static function handle(): void
    {
        SessionManager::requireAuth('/login');

        $agenteId     = SessionManager::getAgentId();
        $agente       = SessionManager::getAgentData();
        $isAdmin      = SessionManager::isAdmin();
        $isSuperadmin = SessionManager::isSuperadmin();

        $formato      = $_GET['formato'] ?? 'excel';
        $tipo         = $_GET['tipo']    ?? 'cotizaciones';
        $fechaDesde   = $_GET['desde']   ?? null;
        $fechaHasta   = $_GET['hasta']   ?? null;
        $filtroAgente = ($isAdmin && isset($_GET['agente_id'])) ? (int)$_GET['agente_id'] : ($isAdmin ? null : $agenteId);

        $fechaGenerado = date('d/m/Y H:i');
        $nombreArchivo = 'EIRA_' . strtoupper($tipo) . '_' . date('Y-m-d');

        if ($tipo === 'cotizaciones') {
            $filtroEstado = $_GET['estado'] ?? null;
            $valEstados   = ['pendiente','enviada','aceptada','rechazada','pagado'];
            if ($filtroEstado && !in_array($filtroEstado, $valEstados, true)) {
                $filtroEstado = null;
            }

            $datos = (new CotizacionModel())->getReporteExportable(
                $isSuperadmin && !$filtroAgente ? null : ($filtroAgente ?? $agenteId),
                $fechaDesde,
                $fechaHasta,
                $filtroEstado
            );

            $titulo = 'Reporte de Cotizaciones';
            $cols   = ['Folio','Fecha','Asesor','Cliente','Compañía','Cobertura','Prima','Estado','Ramo','Vigencia'];
            $filas  = array_map(static function ($r): array {
                $vigTxt = trim((string) ($r['vigencia'] ?? ''));
                $fin    = $r['fecha_fin_vigencia'] ?? '';
                if ($fin !== '' && $fin !== null && !in_array($fin, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
                    $suf = date('d/m/Y', strtotime((string) $fin));
                    $vigTxt = $vigTxt !== '' ? ($vigTxt . ' · fin ' . $suf) : ('Fin vig. ' . $suf);
                }

                return [
                    $r['folio'] ?? '',
                    !empty($r['fecha']) ? date('d/m/Y', strtotime((string) $r['fecha'])) : '',
                    $r['asesor'] ?? '',
                    $r['cliente'] ?? '',
                    $r['compania'] ?? '',
                    $r['tipo_cobertura'] ?? '',
                    '$' . number_format((float) ($r['precio'] ?? 0), 2),
                    ucfirst((string) ($r['estado'] ?? '')),
                    ucfirst(str_replace('_', ' ', (string) ($r['ramo'] ?? ''))),
                    $vigTxt,
                ];
            }, $datos);

        } elseif ($tipo === 'ranking') {
            $datos  = (new AgentModel())->getRankingAsesores();
            $titulo = 'Ranking de Asesores';
            $cols   = ['#','Asesor','Correo','Clientes','Cotizaciones','Ventas','Conversión','Ingreso Total','Ingreso Este Mes'];
            $filas  = array_map(static function ($r, $i): array {
                $tc = (int) ($r['total_cotizaciones'] ?? 0);
                $tv = (int) ($r['total_ventas'] ?? 0);
                $conv = $tc > 0 ? round($tv / $tc * 100) : 0;

                return [
                    $i + 1,
                    trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')),
                    $r['correo'] ?? '',
                    $r['total_clientes'] ?? 0,
                    $tc,
                    $tv,
                    $conv . '%',
                    '$' . number_format((float) ($r['ingreso_total'] ?? 0), 2),
                    '$' . number_format((float) ($r['ingreso_mes'] ?? 0), 2),
                ];
            }, $datos, array_keys($datos));
        } else {
            http_response_code(400);
            echo 'Tipo no válido';
            return;
        }

        match($formato) {
            'excel' => self::renderExcel($nombreArchivo, $titulo, $fechaGenerado, $cols, $filas, $datos, $tipo),
            'pdf'   => self::renderPdf(
                $titulo,
                $fechaGenerado,
                $agente,
                $datos,
                $cols,
                $filas,
                $tipo,
                $fechaDesde,
                $fechaHasta
            ),
            default => (function() { http_response_code(400); echo 'Formato no válido'; })(),
        };
    }

    private static function renderExcel(string $archivo, string $titulo, string $fecha, array $cols, array $filas, array $datos, string $tipo): void 
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $archivo . '.csv"');
        header('Cache-Control: no-cache, no-store');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, [$titulo . ' — ' . $fecha]);
        fputcsv($out, []);
        fputcsv($out, $cols);
        foreach ($filas as $fila) fputcsv($out, $fila);

        if ($tipo === 'cotizaciones' && !empty($datos)) {
            $total = array_sum(array_column($datos, 'precio'));
            fputcsv($out, []);
            fputcsv($out, ['TOTAL','','','','','','','','$' . number_format($total, 2)]);
        }
        fclose($out);
    }

    private static function renderPdf(
        string $titulo,
        string $fechaGen,
        array $agente,
        array $datos,
        array $cols,
        array $filas,
        string $tipoExport,
        ?string $fechaDesde,
        ?string $fechaHasta
    ): void {
        header('Content-Type: text/html; charset=utf-8');
        if ($fechaDesde && $fechaHasta) {
            $rangoFechas = date('d/m/Y', strtotime($fechaDesde)) . ' — ' . date('d/m/Y', strtotime($fechaHasta));
        } elseif ($fechaDesde) {
            $rangoFechas = 'Desde ' . date('d/m/Y', strtotime($fechaDesde));
        } elseif ($fechaHasta) {
            $rangoFechas = 'Hasta ' . date('d/m/Y', strtotime($fechaHasta));
        } else {
            $rangoFechas = 'Todos los registros';
        }

        $agenteNombre = htmlspecialchars(($agente['nombre'] ?? '') . ' ' . ($agente['apellido'] ?? ''));
        $tipo        = $tipoExport;

        ob_start();
        require ROOT . '/App/Views/exports/cotizaciones_pdf.php';
        ob_end_flush();
    }
}