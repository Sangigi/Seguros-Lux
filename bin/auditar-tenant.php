#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/auditar-tenant.php — encuentra consultas que tocan tablas aisladas por
 * empresa sin filtrar por empresa.
 *
 * En un CRM multiempresa la fuga no llega por un fallo espectacular, sino por
 * un SELECT al que se le olvidó el WHERE. Con ~340 consultas repartidas entre
 * modelos, controladores, crons y vistas, revisarlas a ojo no escala: hace
 * falta algo que las recorra en cada cambio.
 *
 * Analiza el SQL incrustado en el código PHP y marca:
 *   - SELECT/UPDATE/DELETE sobre tablas con `empresa_id` sin filtro.
 *   - INSERT sobre esas tablas que no asignan `empresa_id`.
 *
 * Reconoce como filtro válido: `empresa_id = ?`, `:empresa`, el helper
 * `sqlEmpresa()`, un JOIN a una tabla ya filtrada, y los bloques marcados
 * con el comentario de exención.
 *
 * Uso:
 *   php bin/auditar-tenant.php              informe completo
 *   php bin/auditar-tenant.php --resumen    solo el conteo
 *   php bin/auditar-tenant.php --fallar     devuelve 1 si hay hallazgos (CI)
 *
 * Exención deliberada: poner `// tenant-ok: <motivo>` en la línea anterior o
 * en la misma línea de la consulta. Obliga a justificar la excepción en el
 * código, donde se lee, en vez de en la cabeza de quien la escribió.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI.\n");
}

$raiz = dirname(__DIR__);

/** Tablas aisladas por empresa (las mismas de la migración 005). */
const TABLAS_TENANT = [
    'clientes', 'citas', 'cotizaciones', 'polizas', 'cotizacion_archivos',
    'cotizacion_pagos', 'cotizacion_comprobantes_pago', 'cotizacion_detalles',
    'emi_checklist', 'historial_cliente', 'historial_coordinador',
    'notificaciones', 'notas', 'mail_queue',
    'renovacion_notificaciones_programadas', 'meta_leads', 'vida_solicitudes',
    'agentes', 'invitaciones', 'configuracion',
];

/** Rutas que no se auditan. */
const EXCLUIR = [
    '/vendor/', '/node_modules/', '/.git/', '/tests/', '/bin/',
    '/Database/migrations/', '/Database/Migrator.php', '/Database/SchemaHelper.php',
];

$opciones = $argv;
$soloResumen = in_array('--resumen', $opciones, true);
$fallarSiHay = in_array('--fallar', $opciones, true);

// ── Recolección de archivos ──────────────────────────────────────────────────
$archivos = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $ruta = str_replace('\\', '/', $f->getPathname());
    foreach (EXCLUIR as $ex) {
        if (str_contains($ruta, $ex)) {
            continue 2;
        }
    }
    $archivos[] = $ruta;
}

sort($archivos);

// ── Análisis ─────────────────────────────────────────────────────────────────
$hallazgos = [];
$totalSql  = 0;

$tablasRegex = implode('|', array_map('preg_quote', TABLAS_TENANT));

foreach ($archivos as $archivo) {
    $contenido = file_get_contents($archivo);
    if ($contenido === false) {
        continue;
    }

    $lineas = explode("\n", $contenido);

    // Se buscan sentencias SQL dentro de cadenas PHP. Una consulta puede
    // ocupar varias líneas, así que se junta el texto desde la palabra clave
    // hasta el cierre de la cadena.
    $patron = '/\b(SELECT|UPDATE|DELETE\s+FROM|INSERT\s+INTO|REPLACE\s+INTO)\b/i';

    foreach ($lineas as $i => $linea) {
        if (!preg_match($patron, $linea, $m)) {
            continue;
        }

        // Descarta comentarios de documentación.
        $trim = ltrim($linea);
        if (str_starts_with($trim, '*') || str_starts_with($trim, '//') || str_starts_with($trim, '#')) {
            continue;
        }

        // Reúne la consulta: hasta 25 líneas o hasta que aparezca el cierre.
        $bloque = '';
        for ($j = $i; $j < min($i + 25, count($lineas)); $j++) {
            $bloque .= ' ' . $lineas[$j];
            if (preg_match('/(\'\s*\)|"\s*\)|SQL;|\'\s*,|"\s*,|;\s*$)/', $lineas[$j]) && $j > $i) {
                break;
            }
        }

        $bloqueNorm = preg_replace('/\s+/', ' ', $bloque) ?? $bloque;

        // ¿Toca alguna tabla aislada?
        if (!preg_match('/\b(FROM|JOIN|UPDATE|INTO)\s+`?(' . $tablasRegex . ')`?\b/i', $bloqueNorm, $mt)) {
            continue;
        }

        $totalSql++;
        $tabla  = strtolower($mt[2]);
        $tipo   = strtoupper(preg_replace('/\s+/', ' ', $m[1]));

        // ── Exención explícita ───────────────────────────────────────────────
        // Ventana de varias líneas: la marca suele ir sobre el `prepare(`, y la
        // palabra clave SQL aparece una o dos líneas más abajo, dentro de la
        // cadena. Mirar solo la línea anterior dejaría fuera ese caso, que es
        // el habitual cuando la consulta ocupa varias líneas.
        $contexto = implode(' ', array_slice($lineas, max(0, $i - 3), 5));
        if (preg_match('/tenant-ok\s*:/i', $contexto)) {
            continue;
        }

        // ── ¿Tiene filtro de empresa? ────────────────────────────────────────
        $tieneFiltro =
               preg_match('/empresa_id\s*(=|IN)\s*/i', $bloqueNorm)
            || preg_match('/sqlEmpresa(Pos)?\s*\(/i', $bloqueNorm)
            || preg_match('/bindEmpresa\s*\(|empresaPos\s*\(/i', $bloqueNorm)
            || preg_match('/:__empresa|:empresa/i', $bloqueNorm);

        // Contexto cercano: el filtro puede añadirse en las líneas siguientes
        // al armar la consulta por partes.
        if (!$tieneFiltro) {
            $vecindad = implode(' ', array_slice($lineas, max(0, $i - 3), 30));
            $tieneFiltro = (bool) preg_match(
                '/sqlEmpresa(Pos)?\s*\(|bindEmpresa\s*\(|empresaPos\s*\(|empresa_id\s*(=|IN)/i',
                $vecindad
            );
        }

        if ($tieneFiltro) {
            continue;
        }

        // Los INSERT se evalúan aparte: deben asignar empresa_id, salvo que
        // vayan por el insert() de BaseModel, que lo sella solo.
        if (str_starts_with($tipo, 'INSERT') || str_starts_with($tipo, 'REPLACE')) {
            $vecindad = implode(' ', array_slice($lineas, max(0, $i - 2), 25));
            if (preg_match('/->insert\s*\(|empresa_id/i', $vecindad)) {
                continue;
            }
        }

        $hallazgos[] = [
            'archivo' => str_replace($raiz . '/', '', $archivo),
            'linea'   => $i + 1,
            'tipo'    => $tipo,
            'tabla'   => $tabla,
            'sql'     => trim(mb_substr($bloqueNorm, 0, 110)),
        ];
    }
}

// ── Informe ──────────────────────────────────────────────────────────────────
$porArchivo = [];
foreach ($hallazgos as $h) {
    $porArchivo[$h['archivo']][] = $h;
}
krsort($porArchivo);
uasort($porArchivo, static fn($a, $b) => count($b) <=> count($a));

echo "\n\033[1mAuditoría de aislamiento por empresa\033[0m\n";
echo str_repeat('─', 78) . "\n";
echo 'Archivos analizados: ' . count($archivos) . "\n";
echo "Consultas sobre tablas aisladas: {$totalSql}\n";
echo 'Sin filtro de empresa: ' . ($hallazgos === []
        ? "\033[32m0\033[0m"
        : "\033[31m" . count($hallazgos) . "\033[0m") . "\n";

$cobertura = $totalSql > 0
    ? round((($totalSql - count($hallazgos)) / $totalSql) * 100, 1)
    : 100.0;
echo "Cobertura: {$cobertura}%\n";

if (!$soloResumen && $hallazgos !== []) {
    echo "\n";
    foreach ($porArchivo as $archivo => $lista) {
        echo "\033[1m{$archivo}\033[0m \033[90m(" . count($lista) . ")\033[0m\n";
        foreach ($lista as $h) {
            printf("  \033[90m%5d\033[0m  %-12s %-28s %s\n",
                $h['linea'],
                $h['tipo'],
                $h['tabla'],
                mb_substr($h['sql'], 0, 70)
            );
        }
        echo "\n";
    }
    echo "Para una excepción deliberada, añade  // tenant-ok: <motivo>  junto a la consulta.\n";
}

echo "\n";

exit($fallarSiHay && $hallazgos !== [] ? 1 : 0);
