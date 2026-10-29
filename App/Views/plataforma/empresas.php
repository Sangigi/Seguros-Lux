<?php
declare(strict_types=1);

use App\Models\EmpresaModel;
use App\Security\Roles;
use App\Security\SessionManager;

/**
 * Panel del propietario: alta y administración de empresas.
 *
 * Es la única pantalla del CRM que ve más de una empresa a la vez, y por eso
 * está detrás de `Roles::EMPRESAS_VER` y no de «es administrador». Un
 * superadmin administra SU empresa; dar de alta empresas es otra cosa.
 *
 * Los datos se pintan en servidor y las acciones van contra
 * /api/empresas, que vuelve a comprobar los permisos. Lo que se oculta aquí
 * es por comodidad, no por seguridad: esconder un botón nunca protege nada.
 */

SessionManager::requireAuth('/login');
SessionManager::requirePermiso(Roles::EMPRESAS_VER, '/dashboard');

$modelo    = new EmpresaModel();
$empresas  = $modelo->listar(true);
$resumen   = $modelo->resumenPlataforma();
$planes    = EmpresaModel::PLANES;
$csrfToken = SessionManager::getCsrfToken();
$activa    = SessionManager::getEmpresaId();

$puedeCrear    = SessionManager::puede(Roles::EMPRESAS_CREAR);
$puedeSuspender= SessionManager::puede(Roles::EMPRESAS_SUSPENDER);
$puedeEntrar   = SessionManager::puede(Roles::EMPRESAS_ENTRAR);

$pageTitle = 'Empresas';
$h = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style nonce="<?= $h($cspNonce) ?>">
        .plat-wrap      { padding: 24px; max-width: 1200px; }
        .plat-kpis      { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 28px; }
        .plat-kpi       { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; }
        .plat-kpi b     { display: block; font-size: 28px; line-height: 1.1; }
        .plat-kpi span  { color: #64748b; font-size: 13px; }
        .plat-card      { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .plat-card h2   { margin: 0 0 4px; font-size: 17px; }
        .plat-card p.sub{ margin: 0 0 18px; color: #64748b; font-size: 13px; }
        .plat-grid      { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .plat-grid label{ display: block; font-size: 12px; color: #475569; margin-bottom: 6px; }
        .plat-grid input, .plat-grid select {
            width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1;
            border-radius: 8px; font-size: 14px; background: #fff;
        }
        .plat-tabla     { width: 100%; border-collapse: collapse; background: #fff; }
        .plat-tabla th  { text-align: left; font-size: 12px; color: #64748b; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .plat-tabla td  { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        .plat-badge     { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .plat-on        { background: #dcfce7; color: #166534; }
        .plat-off       { background: #fee2e2; color: #991b1b; }
        .plat-aqui      { background: #dbeafe; color: #1e40af; }
        .plat-btn       { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 7px; cursor: pointer; font-size: 13px; }
        .plat-btn:hover { background: #f8fafc; }
        .plat-btn.prim  { background: #0f172a; color: #fff; border-color: #0f172a; }
        .plat-msg       { padding: 12px 14px; border-radius: 9px; margin-bottom: 16px; font-size: 14px; display: none; }
        .plat-msg.ok    { background: #dcfce7; color: #166534; display: block; }
        .plat-msg.err   { background: #fee2e2; color: #991b1b; display: block; }
        .plat-enlace    { font-family: ui-monospace, monospace; font-size: 12px; word-break: break-all;
                          background: #f1f5f9; padding: 9px 11px; border-radius: 7px; margin-top: 10px; display: none; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<main class="plat-wrap">

    <h1 style="font-size:22px;margin:0 0 20px">Empresas de la plataforma</h1>

    <div class="plat-kpis">
        <div class="plat-kpi"><b><?= (int) $resumen['empresas'] ?></b><span>Empresas</span></div>
        <div class="plat-kpi"><b><?= (int) $resumen['activas'] ?></b><span>Activas</span></div>
        <div class="plat-kpi"><b><?= (int) $resumen['usuarios'] ?></b><span>Usuarios</span></div>
        <div class="plat-kpi"><b><?= (int) $resumen['clientes'] ?></b><span>Clientes</span></div>
    </div>

    <div id="platMsg" class="plat-msg"></div>
    <div id="platEnlace" class="plat-enlace"></div>

    <?php if ($puedeCrear): ?>
    <section class="plat-card">
        <h2>Dar de alta una empresa</h2>
        <p class="sub">
            Se crea la empresa y se invita a su primer administrador. La contraseña
            la elige él al activar la cuenta: aquí no se escribe ninguna.
        </p>

        <form id="formAlta">
            <div class="plat-grid">
                <div>
                    <label for="f-nombre">Nombre de la empresa *</label>
                    <input id="f-nombre" name="nombre" required maxlength="150">
                </div>
                <div>
                    <label for="f-razon">Razón social</label>
                    <input id="f-razon" name="razon_social" maxlength="200">
                </div>
                <div>
                    <label for="f-rfc">RFC</label>
                    <input id="f-rfc" name="rfc" maxlength="20">
                </div>
                <div>
                    <label for="f-plan">Plan</label>
                    <select id="f-plan" name="plan">
                        <?php foreach ($planes as $clave => $cfg): ?>
                            <option value="<?= $h((string) $clave) ?>">
                                <?= $h((string) ($cfg['label'] ?? ucfirst((string) $clave))) ?>
                                — hasta <?= (int) ($cfg['max_usuarios'] ?? 0) ?> usuarios
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f-tel">Teléfono</label>
                    <input id="f-tel" name="telefono" maxlength="30">
                </div>
                <div>
                    <label for="f-contacto">Correo de contacto</label>
                    <input id="f-contacto" name="correo_contacto" type="email" maxlength="150">
                </div>
            </div>

            <h2 style="margin:22px 0 4px;font-size:15px">Primer administrador</h2>
            <p class="sub">Recibirá un enlace de activación válido 7 días.</p>

            <div class="plat-grid">
                <div>
                    <label for="f-an">Nombre</label>
                    <input id="f-an" name="admin_nombre" maxlength="80">
                </div>
                <div>
                    <label for="f-aa">Apellido</label>
                    <input id="f-aa" name="admin_apellido" maxlength="80">
                </div>
                <div>
                    <label for="f-ac">Correo *</label>
                    <input id="f-ac" name="admin_correo" type="email" required maxlength="254">
                </div>
                <div>
                    <label for="f-at">Teléfono</label>
                    <input id="f-at" name="admin_telefono" maxlength="30">
                </div>
            </div>

            <div style="margin-top:18px">
                <button type="submit" class="plat-btn prim">Crear empresa e invitar</button>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="plat-card">
        <h2>Listado</h2>
        <p class="sub">Una empresa suspendida no deja entrar a nadie, y sus datos siguen intactos.</p>

        <table class="plat-tabla">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Plan</th>
                    <th>Usuarios</th>
                    <th>Estado</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $e):
                $id      = (int) $e['id'];
                $esActiva= (int) ($e['activo'] ?? 0) === 1;
                $aqui    = $activa === $id;
            ?>
                <tr>
                    <td>
                        <strong><?= $h((string) $e['nombre']) ?></strong>
                        <?php if ($aqui): ?>
                            <span class="plat-badge plat-aqui">estás aquí</span>
                        <?php endif; ?>
                        <div style="color:#94a3b8;font-size:12px"><?= $h((string) $e['slug']) ?></div>
                    </td>
                    <td><?= $h((string) ($e['plan'] ?? '')) ?></td>
                    <?php // listar() ya trae el conteo: pedirlo por fila sería una consulta por empresa ?>
                    <td><?= (int) ($e['usuarios'] ?? 0) ?> / <?= (int) ($e['max_usuarios'] ?? 0) ?></td>
                    <td>
                        <span class="plat-badge <?= $esActiva ? 'plat-on' : 'plat-off' ?>">
                            <?= $esActiva ? 'Activa' : 'Suspendida' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <?php if ($puedeEntrar && $esActiva && !$aqui): ?>
                            <button class="plat-btn" data-accion="entrar" data-id="<?= $id ?>">Entrar</button>
                        <?php endif; ?>
                        <?php if ($aqui): ?>
                            <button class="plat-btn" data-accion="entrar" data-id="0">Salir</button>
                        <?php endif; ?>
                        <?php if ($puedeSuspender): ?>
                            <button class="plat-btn"
                                    data-accion="estado"
                                    data-id="<?= $id ?>"
                                    data-activa="<?= $esActiva ? '0' : '1' ?>">
                                <?= $esActiva ? 'Suspender' : 'Reactivar' ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($empresas === []): ?>
                <tr><td colspan="5" style="color:#94a3b8">Todavía no hay empresas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<script nonce="<?= $h($cspNonce) ?>">
(function () {
    'use strict';

    const CSRF   = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
    const msg    = document.getElementById('platMsg');
    const enlace = document.getElementById('platEnlace');

    function aviso(texto, ok) {
        msg.textContent = texto;
        msg.className   = 'plat-msg ' + (ok ? 'ok' : 'err');
    }

    async function llamar(cuerpo) {
        const r = await fetch('/api/empresas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf: CSRF }, cuerpo))
        });
        return { ok: r.ok, data: await r.json().catch(() => ({})) };
    }

    const form = document.getElementById('formAlta');

    if (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();

            const boton = form.querySelector('button[type=submit]');
            boton.disabled = true;

            const datos = Object.fromEntries(new FormData(form).entries());
            datos.accion = 'crear';

            const { ok, data } = await llamar(datos);

            aviso(data.msg || (ok ? 'Empresa creada.' : 'No se pudo crear.'), ok);

            if (ok && data.enlace) {
                // El enlace se muestra para poder pasarlo por otro canal si el
                // correo no llega: sin él, la empresa queda bloqueada.
                enlace.textContent    = 'Enlace de activación: ' + data.enlace;
                enlace.style.display  = 'block';
                form.reset();
            }

            boton.disabled = false;
        });
    }

    document.querySelectorAll('[data-accion]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const accion = btn.dataset.accion;
            const cuerpo = { accion: accion, id: Number(btn.dataset.id) };

            if (accion === 'estado') {
                cuerpo.activa = btn.dataset.activa === '1';
                if (!cuerpo.activa && !confirm('¿Suspender esta empresa? Nadie de ella podrá entrar.')) {
                    return;
                }
            }

            btn.disabled = true;
            const { ok, data } = await llamar(cuerpo);

            if (ok) {
                location.reload();
                return;
            }

            aviso(data.msg || 'No se pudo completar la acción.', false);
            btn.disabled = false;
        });
    });
})();
</script>
</body>
</html>
