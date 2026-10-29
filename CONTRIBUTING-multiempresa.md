# Cómo escribir consultas en el CRM multiempresa

Este documento es el contrato que debe seguir **toda** consulta nueva. La regla
de fondo es una sola:

> Ningún dato de una empresa puede alcanzarse desde otra, por ninguna vía.

Y la razón de que esto esté escrito: en un CRM multiempresa la fuga no llega
por un fallo espectacular, llega por un `SELECT` al que se le olvidó el
`WHERE`. Con ~330 consultas repartidas entre modelos, controladores, crons y
vistas, la disciplina no se sostiene con buena voluntad, se sostiene con
herramientas.

## Las tres piezas

| Pieza | Para qué |
|---|---|
| `App\Security\Tenant` | Quién es la empresa activa ahora mismo |
| `BaseModel::sqlEmpresa()` / `bindEmpresa()` | Añadir el filtro a SQL escrito a mano |
| `bin/auditar-tenant.php` | Encontrar lo que se escapó |

## 1. Si usas los métodos de BaseModel, ya está

`find()`, `all()`, `paginate()`, `insert()`, `update()` y `delete()` filtran
solos. No hay nada que recordar:

```php
$cliente = (new ClientModel())->find($id);   // solo lo encuentra si es de tu empresa
```

`insert()` sella la empresa activa y **descarta** cualquier `empresa_id` que
venga en los datos, para que no se pueda escribir en la empresa de al lado
colando un campo en un POST.

## 2. Si escribes SQL a mano, añade el filtro con los helpers

Dentro de un modelo:

```php
$stmt = $this->db()->prepare(
    'SELECT id, nombre FROM clientes
     WHERE estado_cliente = :estado' . $this->sqlEmpresa() . '
     ORDER BY nombre'
);
$stmt->execute($this->bindEmpresa(['estado' => $estado]));
```

`sqlEmpresa()` devuelve `' AND empresa_id = :__empresa'`, o cadena vacía en
modo plataforma. `bindEmpresa()` añade el parámetro solo si hace falta. Así el
SQL se arma igual en ambos casos y no hay dos caminos que mantener.

Con alias de tabla, pásalo:

```php
'SELECT c.*, a.nombre FROM clientes c
 JOIN agentes a ON a.id = c.agente_id
 WHERE c.id = :__id' . $this->sqlEmpresa('c')
```

Si la consulta empieza sin `WHERE`, pide el conector:

```php
'SELECT COUNT(*) FROM citas' . $this->sqlEmpresa('', 'WHERE')
```

### Fuera de un modelo (vistas, controladores)

El SQL que vive en vistas usa `$pdo` directamente. Ahí el filtro va explícito:

```php
use App\Security\Tenant;

$empresaId = Tenant::requireId();

$stmt = $pdo->prepare(
    'SELECT * FROM cotizaciones WHERE agente_id = ? AND empresa_id = ?'
);
$stmt->execute([$agenteId, $empresaId]);
```

## 3. JOINs: filtra la tabla que manda

No hace falta repetir `empresa_id` en cada tabla del JOIN si el filtro está en
la tabla raíz y la relación es por id:

```php
// basta con filtrar cotizaciones: los archivos cuelgan de ella
'SELECT a.* FROM cotizacion_archivos a
 JOIN cotizaciones c ON c.id = a.cotizacion_id
 WHERE c.empresa_id = :__empresa'
```

## 4. Procesos sin sesión (crons, webhooks)

Un cron no tiene empresa en contexto, y las consultas **fallan** si no se dice
nada. Es deliberado: el valor por defecto es "nada", nunca "todo".

Dos formas correctas, según lo que haga el proceso:

```php
// A) Recorrer la plataforma entera (ej. cola de correo)
Tenant::sinRestriccion(function () {
    // ...
});

// B) Procesar empresa por empresa (lo preferible: cada vuelta queda aislada,
//    y un fallo en una empresa no contamina a las demás)
foreach ($empresas as $e) {
    Tenant::comoEmpresa((int) $e['id'], function () use ($e) {
        // ...
    });
}
```

Prefiere (B) siempre que el proceso genere datos o notificaciones: así lo que
se cree queda sellado en la empresa correcta sin pensarlo.

## 5. Webhooks

Un webhook entrante (Meta, WhatsApp, Telegram) no sabe de qué empresa es hasta
que resuelve el registro al que corresponde. Patrón:

1. Identificar el registro (por `form_id`, `chat_id`, `phone_id`…).
2. Leer su `empresa_id` **en modo plataforma**.
3. Entrar a esa empresa con `Tenant::comoEmpresa()` y trabajar dentro.

Nunca crear un cliente "sin empresa": quedaría invisible para todos.

## 6. Excepciones

Hay consultas que legítimamente cruzan empresas: el panel del propietario, el
login (ocurre antes de saber la empresa), las métricas de plataforma. Se marcan
en el código, donde se leen:

```php
// tenant-ok: el login ocurre antes de que exista contexto de empresa
$stmt = $pdo->prepare('SELECT ... FROM agentes WHERE correo = ?');
```

El auditor respeta esa marca. Obliga a escribir el motivo, que es el punto: una
excepción justificada por escrito es revisable; una silenciosa, no.

## 7. Antes de subir cambios

```bash
php bin/auditar-tenant.php      # ninguna consulta sin filtro
php bin/verificar-aislamiento.php   # el aislamiento se sostiene de verdad
vendor/bin/phpunit
```

El primero es análisis estático y puede equivocarse en ambos sentidos. El
segundo monta dos empresas reales e intenta leer, modificar y borrar datos
de una desde la otra. Ese es el que no se puede engañar.

## Puesta en marcha

Orden importante: las migraciones primero, el resto después.

```bash
php bin/migrate.php            # aplica 005 (multiempresa) y 006 (canales)
```

La migración 005 crea la empresa 1 con los datos actuales y le asigna todo lo
que ya existe, derivando la empresa del dueño real de cada registro
(cotización → cliente → agente), no poniendo 1 a ciegas. Es idempotente.

### Variables de entorno nuevas

| Variable | Para qué |
|---|---|
| `TELEGRAM_WEBHOOK_SECRET` | **Obligatoria.** El webhook de Telegram rechaza todo si falta. Genérala con `php -r "echo bin2hex(random_bytes(32));"` y vuelve a registrar el webhook. |
| `DIAGNOSTICO_HABILITADO` | `0` en producción. Con `0`, `/diagnostico.php` y `/register_webhook.php` responden 404. |

### Canales entrantes

Un webhook llega sin sesión: lo único que trae es el identificador del canal
por el que entró. La tabla `empresa_canales` traduce ese identificador a una
empresa, y **sin una fila ahí el mensaje se descarta**.

La migración 006 da de alta automáticamente los canales que ya estaban en el
`.env` y los asigna a la empresa 1, de modo que al desplegar nada deja de
funcionar. Para cada empresa nueva hay que registrar los suyos:

```sql
INSERT INTO empresa_canales (empresa_id, canal, identificador, etiqueta)
VALUES (2, 'whatsapp', '<phone_number_id de Meta>', 'Línea principal');
```

### El primer acceso

El alta de una empresa no crea contraseñas: crea la empresa e invita a su
primer administrador, que la elige al activar su cuenta desde
`/registro?token=…`. Nadie más llega a conocerla, tampoco el propietario de
la plataforma.

Para dar de alta al propietario inicial, asígnale el rol a mano una sola vez:

```sql
UPDATE agentes SET rol = 'propietario', empresa_id = NULL WHERE correo = '<tu correo>';
```

El propietario no pertenece a ninguna empresa: entra a cada una desde
*Empresas*, y eso acota lo que ve sin darle permisos extra.

## Tablas que NO se filtran

`empresas` y `migraciones` son de plataforma. Sus modelos lo declaran con
`protected bool $tenantScoped = false;`. El valor por defecto es `true`, de
modo que una tabla nueva queda protegida sin que nadie se acuerde de activarlo.
