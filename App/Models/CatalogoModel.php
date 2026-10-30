<?php
declare(strict_types=1);

namespace App\Models;

use App\Security\Tenant;
use App\Services\QueryCache;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;

/**
 * CatalogoModel — las listas configurables de cada empresa.
 *
 * Cuatro catálogos comparten la tabla `catalogos`, distinguidos por `tipo`:
 *
 *   ramo         Los ramos que la empresa trabaja. Claves fijas (autos, vida,
 *                gastos_medicos, empresas): son las que se guardan en
 *                clientes.ramo, cotizaciones.ramo y citas.ramo, y el código
 *                decide cosas por ellas. Se activan o se renombran; no se
 *                crean ni se borran. Siempre queda al menos uno activo.
 *   aseguradora  Las compañías que se ofrecen en cada ramo. Lista libre.
 *   cobertura    Los tipos de cobertura de cada ramo. Lista libre.
 *   documento    Los documentos del expediente de cada ramo. Cada clave es el
 *                `tipo_documento` de un espacio de carga que ya existe en las
 *                pantallas, así que no se pueden inventar claves nuevas: solo
 *                marcar cuáles se exigen y cambiarles el nombre.
 *
 * QUÉ SIGNIFICA `activo` EN CADA UNO
 *
 *   ramo / aseguradora / cobertura → se ofrece al crear registros nuevos.
 *   documento → se exige para emitir una póliza NUEVA de ese ramo. Las
 *   renovaciones conservan su regla fija (solo la póliza renovada y el
 *   comprobante de pago) y no se modelan aquí. Los documentos de
 *   DOCUMENTOS_PROCESO (comprobante de pago, póliza emitida) están en el
 *   catálogo para que su nombre sea uno solo en todo el CRM, pero los pide el
 *   propio flujo en su etapa: no se marcan como requisito para enviar a
 *   emisión, porque la póliza no puede existir antes de emitirse.
 *
 * LO QUE SE GUARDA EN LOS REGISTROS
 *
 *   Las cotizaciones guardan el TEXTO de la aseguradora y de la cobertura
 *   (cotizaciones.compania = 'GNP', tipo_cobertura = 'Amplio'), no la clave.
 *   Por eso renombrar solo afecta a los registros nuevos: lo ya guardado
 *   conserva el nombre con el que se guardó, y un filtro por compañía tiene
 *   que contar con esos valores antiguos. Los ramos y los documentos, en
 *   cambio, se guardan por clave, y renombrarlos cambia lo que se ve en todas
 *   partes.
 *
 * AISLAMIENTO
 *
 *   El catálogo es siempre el de una empresa concreta, también en modo
 *   plataforma: ahí sqlEmpresa() quitaría el filtro y mezclaría los catálogos
 *   de todas. Por eso las consultas de este modelo llevan el filtro explícito
 *   con la empresa activa (Tenant::requireId()), y sin empresa en contexto
 *   fallan en vez de devolver nada ajeno.
 *
 * VALORES POR DEFECTO
 *
 *   RAMOS, DOCUMENTOS y DEFAULTS reproducen lo que las pantallas tenían
 *   escrito a mano: las aseguradoras que tenía CotizacionModel (que ahora
 *   las lee de aquí),
 *   las coberturas de la pantalla de nueva cotización y, como documentos
 *   requeridos, los que el asesor tiene que subir antes de enviar a emisión.
 *   La migración 007 y el alta de empresas siembran a partir de aquí, y una
 *   empresa sin catálogo se siembra sola en la primera lectura.
 */
class CatalogoModel extends BaseModel
{
    protected string $table    = 'catalogos';
    protected int    $cacheTtl = 300; // configuración: cambia poco y se lee en cada pantalla

    protected array $defaultColumns = ['id', 'tipo', 'ramo', 'clave', 'etiqueta', 'activo', 'orden'];

    protected array $fillable = [
        'tipo'     => 'string',
        'ramo'     => 'string',
        'clave'    => 'string',
        'etiqueta' => 'string',
        'activo'   => 'int',
        'orden'    => 'int',
    ];

    public const RAMO        = 'ramo';
    public const ASEGURADORA = 'aseguradora';
    public const COBERTURA   = 'cobertura';
    public const DOCUMENTO   = 'documento';

    public const TIPOS = [self::RAMO, self::ASEGURADORA, self::COBERTURA, self::DOCUMENTO];

    /** Los catálogos de lista libre: los únicos donde se añade y se borra. */
    public const TIPOS_LIBRES = [self::ASEGURADORA, self::COBERTURA];

    public const MAX_ETIQUETA = 120;

    /** Ramos que conoce el código, con su nombre por defecto. */
    public const RAMOS = [
        'autos'          => 'Autos',
        'vida'           => 'Vida',
        'gastos_medicos' => 'Gastos Médicos',
        'empresas'       => 'Empresas',
    ];

    /** Ícono del sprite /svg/iconos.svg de cada ramo. No es configurable. */
    public const ICONOS_RAMO = [
        'autos'          => 'car-front',
        'vida'           => 'heart-fill',
        'gastos_medicos' => 'hospital',
        'empresas'       => 'building',
    ];

    /**
     * Todos los documentos que conoce el CRM, con su nombre por defecto.
     * La clave es el `tipo_documento` con el que se guarda el archivo.
     */
    public const DOCUMENTOS = [
        'doc_cotizacion'      => 'Documento de cotización',
        'ine'                 => 'INE / Identificación oficial',
        'comprobante_dom'     => 'Comprobante de domicilio',
        'tarjeta_circulacion' => 'Tarjeta de circulación',
        'foto_coche_1'        => 'Foto del vehículo 1',
        'foto_coche_2'        => 'Foto del vehículo 2',
        'foto_coche_3'        => 'Foto del vehículo 3',
        'foto_coche_4'        => 'Foto del vehículo 4',
        'foto_coche_5'        => 'Foto del vehículo 5',
        'factura_auto'        => 'Factura del auto',
        'constancia_fiscal'   => 'Constancia de situación fiscal',
        'comprobante_pago'    => 'Comprobante de pago',
        'poliza'              => 'Póliza',
    ];

    /**
     * Documentos que pide el flujo en su propia etapa (pago, emisión) y que,
     * por eso, no pueden marcarse como requisito para enviar a emisión.
     */
    public const DOCUMENTOS_PROCESO = ['comprobante_pago', 'poliza'];

    /**
     * Lo que recibe una empresa nueva.
     *
     * aseguradora / cobertura: ramo => etiquetas, en orden.
     * documento: ramo => [clave => requerido para una póliza nueva]. Solo
     * aparecen los documentos que tienen espacio de carga en ese ramo (las
     * fotos del vehículo, por ejemplo, solo existen en autos).
     */
    public const DEFAULTS = [
        self::ASEGURADORA => [
            'autos'          => ['GNP', 'Qualitas', 'Chubb', 'Inbursa'],
            'vida'           => ['GNP', 'MetLife', 'Seguros Monterrey', 'Banamex Seguros', 'BBVA Seguros', 'Inbursa'],
            'gastos_medicos' => ['GNP', 'AXA', 'Qualitas', 'Mapfre', 'Chubb', 'BUPA', 'Allianz'],
            'empresas'       => ['GNP', 'AXA', 'Mapfre', 'Chubb', 'HDI', 'Zurich', 'Allianz'],
        ],
        self::COBERTURA => [
            'autos'          => ['Básico', 'Amplio', 'Amplio Total', 'Premium'],
            'vida'           => ['Temporal', 'Entera', 'Dotal', 'Entera con Ahorro'],
            'gastos_medicos' => ['Individual', 'Familiar', 'Empresarial', 'PyME'],
            'empresas'       => [
                'Responsabilidad Civil', 'Daños', 'Flotillas', 'Colectivo de Vida',
                'GMM Colectivo', 'Multirriesgo', 'Fianzas',
            ],
        ],
        self::DOCUMENTO => [
            'autos' => [
                'doc_cotizacion'      => true,
                'ine'                 => true,
                'comprobante_dom'     => true,
                'tarjeta_circulacion' => true,
                'foto_coche_1'        => true,
                'foto_coche_2'        => true,
                'foto_coche_3'        => false,
                'foto_coche_4'        => false,
                'foto_coche_5'        => false,
                'factura_auto'        => false,
                'constancia_fiscal'   => false,
                'comprobante_pago'    => false,
                'poliza'              => false,
            ],
            'vida' => [
                'doc_cotizacion'    => true,
                'ine'               => true,
                'comprobante_dom'   => true,
                'constancia_fiscal' => false,
                'comprobante_pago'  => false,
                'poliza'            => false,
            ],
            'gastos_medicos' => [
                'doc_cotizacion'    => true,
                'ine'               => true,
                'comprobante_dom'   => true,
                'constancia_fiscal' => false,
                'comprobante_pago'  => false,
                'poliza'            => false,
            ],
            'empresas' => [
                'doc_cotizacion'      => true,
                'ine'                 => true,
                'comprobante_dom'     => true,
                'tarjeta_circulacion' => false,
                'factura_auto'        => false,
                'constancia_fiscal'   => false,
                'comprobante_pago'    => false,
                'poliza'              => false,
            ],
        ],
    ];

    // ── Lectura ───────────────────────────────────────────────────────────────

    /**
     * Ramos de la empresa, en su orden.
     *
     * @return array<string,string> clave => etiqueta
     */
    public function ramos(bool $soloActivos = true): array
    {
        return self::mapa($this->seleccionar(self::RAMO, '', $soloActivos));
    }

    /**
     * Etiquetas de un catálogo, en su orden, listas para un <select>.
     *
     * Con $ramo vacío (en aseguradora, cobertura o documento) junta las de
     * todos los ramos, en el orden de los ramos; si una clave se repite entre
     * ramos gana la primera. Sirve para filtros que no dependen del ramo.
     *
     * En documentos, $soloActivos = true devuelve solo los requeridos.
     *
     * @return array<string,string> clave => etiqueta
     */
    public function etiquetas(string $tipo, string $ramo = '', bool $soloActivos = true): array
    {
        self::exigirTipo($tipo);

        if ($tipo === self::RAMO) {
            return $this->ramos($soloActivos);
        }

        return self::mapa($this->seleccionar($tipo, $ramo, $soloActivos));
    }

    /**
     * Filas completas para la pantalla de administración.
     *
     * Cada fila: id (int), tipo, ramo, clave, etiqueta, activo (bool),
     * orden (int), eliminable (bool) y conmutable (bool: si se le puede
     * cambiar `activo`).
     *
     * @return list<array<string,mixed>>
     */
    public function lista(string $tipo, string $ramo = '', bool $soloActivos = false): array
    {
        self::exigirTipo($tipo);

        return array_map(
            [self::class, 'conPermisos'],
            $this->seleccionar($tipo, $tipo === self::RAMO ? '' : $ramo, $soloActivos)
        );
    }

    /**
     * Claves de los documentos que se exigen para una póliza nueva del ramo.
     * Un ramo vacío o desconocido no exige nada.
     *
     * @return list<string>
     */
    public function requeridos(string $ramo): array
    {
        if ($ramo === '') {
            return [];
        }

        return array_keys(self::mapa($this->seleccionar(self::DOCUMENTO, $ramo, true)));
    }

    /** Una fila del catálogo de la empresa activa, o null si no es suya. */
    public function fila(int $id): ?array
    {
        $fila = $this->buscar($id);

        return $fila === null ? null : self::conPermisos($fila);
    }

    /**
     * Todo el catálogo de la empresa, agrupado como lo pinta la pantalla de
     * administración. Incluye lo inactivo.
     *
     * @return array{ramos: list<array<string,mixed>>, por_ramo: array<string,array{aseguradoras: list<array<string,mixed>>, coberturas: list<array<string,mixed>>, documentos: list<array<string,mixed>>}>}
     */
    public function agrupado(): array
    {
        $ramos   = $this->lista(self::RAMO);
        $porRamo = [];

        foreach ($ramos as $r) {
            $porRamo[$r['clave']] = [
                'aseguradoras' => $this->lista(self::ASEGURADORA, $r['clave']),
                'coberturas'   => $this->lista(self::COBERTURA, $r['clave']),
                'documentos'   => $this->lista(self::DOCUMENTO, $r['clave']),
            ];
        }

        return ['ramos' => $ramos, 'por_ramo' => $porRamo];
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    /**
     * Añade una aseguradora o una cobertura al ramo. Devuelve su id.
     *
     * La clave sale del nombre y es única dentro del tipo y el ramo. No se
     * admite un nombre que ya esté en la lista, sin distinguir mayúsculas,
     * acentos ni signos ("Daños" y "danos" son el mismo).
     *
     * @throws InvalidArgumentException tipo no admitido, ramo inexistente,
     *                                  nombre vacío, largo o repetido.
     */
    public function agregar(string $tipo, string $ramo, string $etiqueta): int
    {
        if (!in_array($tipo, self::TIPOS_LIBRES, true)) {
            throw new InvalidArgumentException(
                'Solo se pueden añadir aseguradoras y coberturas. Los ramos y los documentos '
                . 'son fijos: se activan, se desactivan o se renombran.'
            );
        }

        if (!isset($this->ramos(false)[$ramo])) {
            throw new InvalidArgumentException('Ese ramo no existe.');
        }

        $etiqueta = self::limpiarEtiqueta($etiqueta);
        $hermanas = $this->seleccionar($tipo, $ramo, false);

        self::exigirUnica($hermanas, $etiqueta);

        $orden = 0;
        foreach ($hermanas as $h) {
            $orden = max($orden, $h['orden']);
        }

        try {
            // insert() sella la empresa activa: el dato de entrada nunca la decide.
            return $this->insert([
                'tipo'     => $tipo,
                'ramo'     => $ramo,
                'clave'    => self::claveUnica(self::slug($etiqueta), array_column($hermanas, 'clave')),
                'etiqueta' => $etiqueta,
                'activo'   => 1,
                'orden'    => $orden + 1,
            ]);
        } catch (PDOException $e) {
            // El índice único es la última palabra si dos personas añaden lo
            // mismo a la vez: la segunda recibe el mismo aviso que si hubiera
            // llegado después.
            if ((string) $e->getCode() === '23000') {
                throw new InvalidArgumentException("«{$etiqueta}» ya está en la lista.", 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Cambia etiqueta, activo y/o orden de un elemento de la empresa activa.
     * Devuelve false si el elemento no es de la empresa o no hay nada que
     * cambiar.
     *
     * @param  array<string,mixed> $datos
     * @throws InvalidArgumentException nombre vacío, largo o repetido.
     * @throws DomainException          desactivar el último ramo activo, o
     *                                  marcar como requisito un documento que
     *                                  pide el propio flujo.
     */
    public function actualizar(int $id, array $datos): bool
    {
        $fila = $this->buscar($id);

        if ($fila === null) {
            return false;
        }

        $cambios = [];

        if (array_key_exists('etiqueta', $datos)) {
            $etiqueta = self::limpiarEtiqueta((string) $datos['etiqueta']);

            $hermanas = array_filter(
                $this->seleccionar($fila['tipo'], $fila['ramo'], false),
                static fn(array $h): bool => $h['id'] !== $id
            );
            self::exigirUnica($hermanas, $etiqueta);

            $cambios['etiqueta'] = $etiqueta;
        }

        if (array_key_exists('activo', $datos)) {
            $activo = is_bool($datos['activo'])
                ? $datos['activo']
                : filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($activo === null) {
                throw new InvalidArgumentException('El valor de "activo" no es válido.');
            }

            if ($activo !== $fila['activo']) {
                $this->exigirConmutable($fila, $activo);
            }

            $cambios['activo'] = $activo ? 1 : 0;
        }

        if (array_key_exists('orden', $datos)) {
            $cambios['orden'] = max(0, (int) $datos['orden']);
        }

        if ($cambios === []) {
            return false;
        }

        $cambios['actualizado_en'] = date('Y-m-d H:i:s');

        $sets = implode(', ', array_map(static fn(string $c): string => "{$c} = :{$c}", array_keys($cambios)));

        $stmt = $this->db()->prepare(
            "UPDATE catalogos SET {$sets} WHERE id = :id AND empresa_id = :empresa"
        );
        $stmt->execute($cambios + ['id' => $id, 'empresa' => $this->empresa()]);

        QueryCache::invalidate($this->table);

        return true;
    }

    /**
     * Borra una aseguradora o una cobertura de la empresa activa.
     * Devuelve false si no es de la empresa.
     *
     * Lo ya guardado en cotizaciones no cambia: allí vive el texto, no una
     * referencia a esta fila.
     *
     * @throws DomainException si es un ramo o un documento.
     */
    public function eliminar(int $id): bool
    {
        $fila = $this->buscar($id);

        if ($fila === null) {
            return false;
        }

        if ($fila['tipo'] === self::RAMO) {
            throw new DomainException('Los ramos no se eliminan: desactívalo si tu empresa no lo maneja.');
        }

        if ($fila['tipo'] === self::DOCUMENTO) {
            throw new DomainException(
                'Los documentos no se eliminan: están ligados a los espacios de carga del expediente. '
                . 'Desmárcalo como requerido si no lo pides.'
            );
        }

        $stmt = $this->db()->prepare('DELETE FROM catalogos WHERE id = :id AND empresa_id = :empresa');
        $stmt->execute(['id' => $id, 'empresa' => $this->empresa()]);

        QueryCache::invalidate($this->table);

        return $stmt->rowCount() > 0;
    }

    // ── Siembra ───────────────────────────────────────────────────────────────

    /**
     * Da a una empresa los valores por defecto que le falten.
     *
     * Solo siembra la empresa activa (o cualquiera en modo plataforma): el
     * alta de empresas la llama dentro de Tenant::comoEmpresa(). Así ni
     * siquiera una operación inofensiva escribe en la empresa de al lado.
     */
    public function sembrar(int $empresaId): void
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa no válida.');
        }

        if (!Tenant::esGlobal() && Tenant::id() !== $empresaId) {
            throw new LogicException(
                'Solo se siembra el catálogo de la empresa activa. Usa Tenant::comoEmpresa().'
            );
        }

        self::sembrarCon($this->db(), $empresaId);
        QueryCache::invalidate($this->table);
    }

    /**
     * La siembra en sí, sobre una conexión dada. La usa también la migración
     * 007, que trabaja con su propio PDO y sin empresa en contexto.
     *
     * Qué cuenta como "lo que falta":
     *   - ramos y documentos, clave por clave: son conjuntos que conoce el
     *     código, así que un ramo o un documento nuevo llega a todas las
     *     empresas en la siguiente siembra;
     *   - aseguradoras y coberturas, solo en las listas que la empresa nunca
     *     tuvo. Si borró una a propósito, volver a sembrar no debe
     *     resucitarla.
     *
     * Devuelve cuántas filas añadió.
     */
    public static function sembrarCon(PDO $db, int $empresaId): int
    {
        $stmt = $db->prepare('SELECT tipo, ramo, clave FROM catalogos WHERE empresa_id = ?');
        $stmt->execute([$empresaId]);

        $existe = [];
        $listas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $existe[$f['tipo'] . '|' . $f['ramo'] . '|' . $f['clave']] = true;
            $listas[$f['tipo'] . '|' . $f['ramo']] = true;
        }

        $insert = $db->prepare(
            'INSERT INTO catalogos (empresa_id, tipo, ramo, clave, etiqueta, activo, orden)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }

        $nuevas = 0;

        try {
            foreach (self::filasPorDefecto() as $f) {
                if (isset($existe[$f['tipo'] . '|' . $f['ramo'] . '|' . $f['clave']])) {
                    continue;
                }

                if (in_array($f['tipo'], self::TIPOS_LIBRES, true) && isset($listas[$f['tipo'] . '|' . $f['ramo']])) {
                    continue;
                }

                $insert->execute([
                    $empresaId, $f['tipo'], $f['ramo'], $f['clave'], $f['etiqueta'], $f['activo'], $f['orden'],
                ]);
                $nuevas++;
            }

            if ($propia) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($propia) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($nuevas > 0) {
            QueryCache::invalidate('catalogos');
        }

        return $nuevas;
    }

    /**
     * Las filas que recibe una empresa nueva, en el orden en que se muestran.
     *
     * @return list<array{tipo:string,ramo:string,clave:string,etiqueta:string,activo:int,orden:int}>
     */
    public static function filasPorDefecto(): array
    {
        $filas = [];

        $orden = 0;
        foreach (self::RAMOS as $clave => $etiqueta) {
            $filas[] = self::filaDefecto(self::RAMO, '', $clave, $etiqueta, true, ++$orden);
        }

        foreach ([self::ASEGURADORA, self::COBERTURA] as $tipo) {
            foreach (self::DEFAULTS[$tipo] as $ramo => $etiquetas) {
                $orden = 0;
                foreach ($etiquetas as $etiqueta) {
                    $filas[] = self::filaDefecto($tipo, $ramo, self::slug($etiqueta), $etiqueta, true, ++$orden);
                }
            }
        }

        foreach (self::DEFAULTS[self::DOCUMENTO] as $ramo => $documentos) {
            $orden = 0;
            foreach ($documentos as $clave => $requerido) {
                $filas[] = self::filaDefecto(self::DOCUMENTO, $ramo, $clave, self::DOCUMENTOS[$clave], $requerido, ++$orden);
            }
        }

        return $filas;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    /**
     * Empresa cuyo catálogo se lee o se escribe. Sin empresa en contexto,
     * falla: un catálogo sin dueño no existe.
     */
    private function empresa(): int
    {
        return Tenant::requireId();
    }

    /**
     * Todo el catálogo de la empresa activa, ordenado por orden e id.
     *
     * Se lee entero y se filtra en PHP: son unas decenas de filas, y una
     * pantalla suele pedir varias listas seguidas. La clave de caché lleva la
     * empresa explícita porque en modo plataforma Tenant::claveCache() es la
     * misma para todas.
     *
     * Red de seguridad: si la empresa no tiene ni una fila (se creó antes de
     * la migración, o su siembra falló), se siembra aquí.
     *
     * @return list<array{id:int,tipo:string,ramo:string,clave:string,etiqueta:string,activo:bool,orden:int}>
     */
    private function filas(): array
    {
        $empresaId = $this->empresa();

        $leer = fn(): array => QueryCache::remember(
            table: $this->table,
            key:   'catalogo:e' . $empresaId,
            ttl:   $this->cacheTtl,
            fn:    fn(): array => $this->consultar($empresaId),
        );

        $filas = $leer();

        if ($filas === []) {
            try {
                $this->sembrar($empresaId);
            } catch (PDOException $e) {
                // Otra petición pudo sembrar a la vez y ganar el índice único.
                // Si es así, sus filas ya están; si no aparece nada, el fallo
                // es real y no se esconde.
                QueryCache::invalidate($this->table);
                if ($leer() === []) {
                    throw $e;
                }
            }
            $filas = $leer();
        }

        return $filas;
    }

    /** @return list<array{id:int,tipo:string,ramo:string,clave:string,etiqueta:string,activo:bool,orden:int}> */
    private function consultar(int $empresaId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, tipo, ramo, clave, etiqueta, activo, orden
               FROM catalogos
              WHERE empresa_id = :empresa
              ORDER BY orden, id'
        );
        $stmt->execute(['empresa' => $empresaId]);

        return array_map(static fn(array $f): array => [
            'id'       => (int) $f['id'],
            'tipo'     => (string) $f['tipo'],
            'ramo'     => (string) $f['ramo'],
            'clave'    => (string) $f['clave'],
            'etiqueta' => (string) $f['etiqueta'],
            'activo'   => (int) $f['activo'] === 1,
            'orden'    => (int) $f['orden'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function buscar(int $id): ?array
    {
        foreach ($this->filas() as $f) {
            if ($f['id'] === $id) {
                return $f;
            }
        }

        return null;
    }

    /**
     * Filas de un tipo, en orden. Para aseguradora, cobertura y documento,
     * un $ramo vacío trae las de todos los ramos, ordenadas primero por ramo.
     *
     * @return list<array<string,mixed>>
     */
    private function seleccionar(string $tipo, string $ramo, bool $soloActivos): array
    {
        $todas = $this->filas();

        $filas = array_values(array_filter(
            $todas,
            static fn(array $f): bool => $f['tipo'] === $tipo
                && ($ramo === '' || $tipo === self::RAMO || $f['ramo'] === $ramo)
                && (!$soloActivos || $f['activo'])
        ));

        if ($tipo !== self::RAMO && $ramo === '') {
            $posicion = [];
            foreach ($todas as $f) {
                if ($f['tipo'] === self::RAMO) {
                    $posicion[$f['clave']] ??= count($posicion);
                }
            }

            usort($filas, static fn(array $a, array $b): int =>
                [$posicion[$a['ramo']] ?? PHP_INT_MAX, $a['orden'], $a['id']]
                <=> [$posicion[$b['ramo']] ?? PHP_INT_MAX, $b['orden'], $b['id']]
            );
        }

        return $filas;
    }

    /**
     * Comprueba que el cambio de `activo` está permitido.
     *
     * @throws DomainException
     */
    private function exigirConmutable(array $fila, bool $activo): void
    {
        if (!self::conPermisos($fila)['conmutable']) {
            throw new DomainException(
                'Este documento lo pide el propio flujo en su etapa (pago o emisión): '
                . 'aquí solo se le puede cambiar el nombre.'
            );
        }

        if ($fila['tipo'] === self::RAMO && !$activo) {
            $otrosActivos = array_filter(
                $this->seleccionar(self::RAMO, '', true),
                static fn(array $r): bool => $r['id'] !== $fila['id']
            );

            if ($otrosActivos === []) {
                throw new DomainException('Tiene que quedar al menos un ramo activo: sin ramos no se puede cotizar.');
            }
        }
    }

    /** @param list<array<string,mixed>> $filas */
    private static function mapa(array $filas): array
    {
        $out = [];
        foreach ($filas as $f) {
            $out[$f['clave']] ??= $f['etiqueta'];
        }

        return $out;
    }

    private static function conPermisos(array $fila): array
    {
        $fila['eliminable'] = in_array($fila['tipo'], self::TIPOS_LIBRES, true);
        $fila['conmutable'] = !($fila['tipo'] === self::DOCUMENTO
            && in_array($fila['clave'], self::DOCUMENTOS_PROCESO, true));

        return $fila;
    }

    private static function filaDefecto(
        string $tipo,
        string $ramo,
        string $clave,
        string $etiqueta,
        bool $activo,
        int $orden
    ): array {
        return [
            'tipo'     => $tipo,
            'ramo'     => $ramo,
            'clave'    => $clave,
            'etiqueta' => $etiqueta,
            'activo'   => $activo ? 1 : 0,
            'orden'    => $orden,
        ];
    }

    /** @throws InvalidArgumentException */
    private static function exigirTipo(string $tipo): void
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException("Tipo de catálogo desconocido: {$tipo}.");
        }
    }

    /**
     * Normaliza un nombre escrito por una persona: espacios de más fuera,
     * sin caracteres de control y con un largo que quepa en la columna.
     *
     * @throws InvalidArgumentException
     */
    private static function limpiarEtiqueta(string $etiqueta): string
    {
        $limpia = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $etiqueta);
        $limpia = trim((string) preg_replace('/\s+/u', ' ', (string) $limpia));

        if ($limpia === '' || self::slug($limpia) === '') {
            throw new InvalidArgumentException('Escribe un nombre con letras o números.');
        }

        if (mb_strlen($limpia) > self::MAX_ETIQUETA) {
            throw new InvalidArgumentException('El nombre no puede pasar de ' . self::MAX_ETIQUETA . ' caracteres.');
        }

        return $limpia;
    }

    /**
     * Rechaza un nombre que ya está en la lista. Se compara por su forma
     * normalizada (sin mayúsculas, acentos ni signos): "Daños" y "DANOS" en
     * una misma lista solo confunden a quien elige.
     *
     * @param  iterable<array<string,mixed>> $hermanas
     * @throws InvalidArgumentException
     */
    private static function exigirUnica(iterable $hermanas, string $etiqueta): void
    {
        $forma = self::slug($etiqueta);

        foreach ($hermanas as $h) {
            if (self::slug((string) $h['etiqueta']) === $forma) {
                throw new InvalidArgumentException("«{$h['etiqueta']}» ya está en la lista.");
            }
        }
    }

    /**
     * Clave a partir de un nombre: minúsculas, sin acentos, con guion bajo.
     *
     * Los acentos se quitan con una tabla propia en lugar de iconv(): la
     * transliteración de iconv cambia entre sistemas (en Windows deja
     * apóstrofos), y una clave tiene que salir igual en todas partes.
     */
    private static function slug(string $texto): string
    {
        $texto = strtr(mb_strtolower($texto, 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);

        $texto = trim((string) preg_replace('/[^a-z0-9]+/', '_', $texto), '_');

        // Se deja sitio para el sufijo de claveUnica() dentro de los 60 de la columna.
        return rtrim(substr($texto, 0, 50), '_');
    }

    /** @param list<string> $ocupadas */
    private static function claveUnica(string $base, array $ocupadas): string
    {
        $clave = $base;
        $n     = 1;

        while (in_array($clave, $ocupadas, true)) {
            $clave = $base . '_' . (++$n);
        }

        return $clave;
    }
}
