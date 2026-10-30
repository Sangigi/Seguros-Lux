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

/** Tablas aisladas por empresa (las de la migración 005, más las que llegaron después). */
const TABLAS_TENANT = [
    'clientes', 'citas', 'cotizaciones', 'polizas', 'cotizacion_archivos',
    'cotizacion_pagos', 'cotizacion_comprobantes_pago', 'cotizacion_detalles',
    'emi_checklist', 'historial_cliente', 'historial_coordinador',
    'notificaciones', 'notas', 'mail_queue',
    'renovacion_notificaciones_programadas', 'meta_leads', 'vida_solicitudes',
    'agentes', 'invitaciones', 'configuracion',
    'catalogos',
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

/**
 * Parte un archivo PHP en sentencias y devuelve, de cada una, el texto de sus
 * cadenas (donde vive el SQL), el código que las rodea, sus comentarios y la
 * línea en que empieza.
 *
 * La unidad es la sentencia completa, hasta su `;`. La versión anterior
 * juntaba líneas desde la palabra SQL y cortaba en la primera que pareciera
 * cerrar una cadena: con `SUM(estado='x')` en la segunda línea cortaba antes
 * del FROM y la consulta ni siquiera se contaba. Además daba por filtrada
 * cualquier consulta con un `empresa_id =` en las 30 líneas de alrededor,
 * aunque perteneciera a otra consulta. Así se colaron consultas que
 * mezclaban asesores de todas las empresas.
 *
 * `ambito` identifica la función a la que pertenece la sentencia (0 = fuera
 * de funciones), para no buscar el filtro de una consulta en otra función.
 *
 * @return array<int,array{linea:int,ambito:int,sql:string,codigo:string,comentarios:string}>
 */
function sentencias(string $contenido): array
{
    $vacia    = ['linea' => 0, 'ambito' => 0, 'sql' => '', 'codigo' => '', 'comentarios' => ''];
    $actual   = $vacia;
    $out      = [];
    $enCadena = false;

    $profundidad      = 0;
    $funciones        = [];   // pila de [profundidad de apertura, id de ámbito]
    $funcionPendiente = false;
    $siguienteAmbito  = 1;

    foreach (token_get_all($contenido) as $t) {
        [$id, $texto, $linea] = is_array($t) ? $t : [null, $t, 0];

        if (!$enCadena && $id === T_FUNCTION) {
            $funcionPendiente = true;
        }

        $corte = !$enCadena
            && ($texto === ';' || $texto === '{' || $texto === '}' || $id === T_INLINE_HTML || $id === T_CLOSE_TAG);
        if ($corte) {
            $out[]  = $actual;
            $actual = $vacia;

            if ($texto === ';') {
                $funcionPendiente = false; // método abstracto o `use function`
            } elseif ($texto === '{') {
                $profundidad++;
                if ($funcionPendiente) {
                    $funciones[]      = [$profundidad, $siguienteAmbito++];
                    $funcionPendiente = false;
                }
            } elseif ($texto === '}') {
                if ($funciones !== [] && end($funciones)[0] === $profundidad) {
                    array_pop($funciones);
                }
                $profundidad--;
            }
            $actual['ambito'] = $funciones === [] ? 0 : end($funciones)[1];
            continue;
        }

        if ($actual['linea'] === 0 && $linea > 0 && $id !== T_WHITESPACE) {
            $actual['linea'] = $linea;
        }

        if ($texto === '"' || $id === T_START_HEREDOC || $id === T_END_HEREDOC) {
            $enCadena = $id === T_START_HEREDOC || ($texto === '"' && !$enCadena);
            continue;
        }

        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            $actual['comentarios'] .= ' ' . $texto;
        } elseif ($id === T_CONSTANT_ENCAPSED_STRING) {
            $actual['sql'] .= ' ' . substr($texto, 1, -1);
        } elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
            $actual['sql'] .= $texto;
        } elseif ($enCadena) {
            // Variable interpolada: queda en el SQL como marcador y en el
            // código para poder seguirla.
            $actual['sql']    .= $texto;
            $actual['codigo'] .= $texto;
        } else {
            $actual['codigo'] .= $texto;
        }
    }
    $out[] = $actual;

    return array_values(array_filter($out, static fn(array $s): bool => $s['linea'] > 0));
}

/** True si el texto de una sentencia ya filtra por empresa. */
function filtraEmpresa(array $s): bool
{
    return preg_match('/empresa_id\s*(=|IN\b|<=>)/i', $s['sql'])
        || preg_match('/:__empresa\w*|:empresa\w*/i', $s['sql'])
        || preg_match('/sqlEmpresa(Pos)?\s*\(|bindEmpresa\s*\(|empresaPos\s*\(/i', $s['codigo']);
}

foreach ($archivos as $archivo) {
    $contenido = file_get_contents($archivo);
    if ($contenido === false) {
        continue;
    }

    $lista = sentencias($contenido);

    foreach ($lista as $k => $s) {
        $sqlNorm = preg_replace('/\s+/', ' ', $s['sql']) ?? $s['sql'];

        if (!preg_match('/\b(SELECT|UPDATE|DELETE\s+FROM|INSERT\s+INTO|REPLACE\s+INTO)\b/i', $sqlNorm, $m)) {
            continue;
        }
        if (!preg_match('/\b(FROM|JOIN|UPDATE|INTO)\s+`?(' . $tablasRegex . ')`?\b/i', $sqlNorm, $mt)) {
            continue;
        }

        $totalSql++;
        $tabla = strtolower($mt[2]);
        $tipo  = strtoupper(preg_replace('/\s+/', ' ', $m[1]));

        // ── Exención explícita ───────────────────────────────────────────────
        // La marca va en un comentario de la propia sentencia, normalmente en
        // la línea de encima del `prepare(`.
        if (preg_match('/tenant-ok\s*:/i', $s['comentarios'])) {
            continue;
        }

        if (filtraEmpresa($s)) {
            continue;
        }

        // Los INSERT deben asignar empresa_id, salvo que vayan por el
        // insert() de BaseModel, que la sella solo.
        if ((str_starts_with($tipo, 'INSERT') || str_starts_with($tipo, 'REPLACE'))
            && preg_match('/empresa_id/i', $sqlNorm . ' ' . $s['codigo'])) {
            continue;
        }

        // ── Consultas armadas por partes ─────────────────────────────────────
        // `$sql = "SELECT ..."; ... $sql .= $this->sqlEmpresa();`, o un
        // `{$where}` construido antes con el filtro. Cuenta solo si el filtro
        // se añade a ESA variable, en la misma función y mientras conserva
        // ese valor: no basta con que otra consulta cercana filtre.
        $mismaVariable = static function (string $var, int $desde, int $paso) use ($lista, $s): bool {
            $q = preg_quote($var, '/');
            for ($j = $desde; $j >= 0 && $j < count($lista); $j += $paso) {
                $otra = $lista[$j];
                if ($otra['ambito'] !== $s['ambito']) {
                    break;
                }
                $anade  = (bool) preg_match('/^\s*' . $q . '\s*\.=/', $otra['codigo']);
                $asigna = (bool) preg_match('/^\s*' . $q . '\s*=(?!=)/', $otra['codigo']);
                if ($paso > 0 && $asigna) {
                    return false; // la variable se reasignó: lo de después ya es otra consulta
                }
                // Hacia delante también vale usarla con el filtro pegado:
                // `prepare($sql . $this->sqlEmpresa())`.
                $usa = $paso > 0 && preg_match('/' . $q . '\s*\.\s*\$this->sqlEmpresa/', $otra['codigo']);
                if (($anade || $asigna || $usa) && filtraEmpresa($otra)) {
                    return true;
                }
                if ($paso < 0 && $asigna) {
                    return false; // llegamos a donde nació el valor sin ver filtro
                }
            }
            return false;
        };

        $filtradaPorPartes = false;
        if (preg_match('/^\s*(\$\w+)\s*\.?=/', $s['codigo'], $mv)) {
            // La consulta se guarda en una variable: el filtro puede llegar después.
            $filtradaPorPartes = $mismaVariable($mv[1], $k + 1, 1);
        }
        if (!$filtradaPorPartes) {
            // Variables interpoladas o concatenadas en el SQL: el filtro pudo
            // venir dentro de ellas.
            preg_match_all('/\$\w+/', $s['sql'] . ' ' . $s['codigo'], $mi);
            foreach (array_unique($mi[0]) as $var) {
                if ($var !== '$this' && $mismaVariable($var, $k - 1, -1)) {
                    $filtradaPorPartes = true;
                    break;
                }
            }
        }
        if ($filtradaPorPartes) {
            continue;
        }

        $hallazgos[] = [
            'archivo' => str_replace($raiz . '/', '', $archivo),
            'linea'   => $s['linea'],
            'tipo'    => $tipo,
            'tabla'   => $tabla,
            'sql'     => trim(mb_substr($sqlNorm, 0, 110)),
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
