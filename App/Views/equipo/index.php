<?php
declare(strict_types=1);

use App\Models\EmpresaModel;
use App\Models\InvitacionModel;
use App\Security\Roles;
use App\Security\SessionManager;
use App\Security\Tenant;

/**
 * Equipo de la empresa: quién está invitado y con qué perfil.
 *
 * El alta de personas vive aquí y no en el panel de empresas, porque es
 * asunto de cada empresa, no de la plataforma. Un administrador invita a los
 * suyos; nunca ve ni toca los de otra.
 *
 * Los roles que puede ofrecer el desplegable salen de
 * `Roles::asignablesPor()`, que excluye su propio nivel y los de arriba. No
 * es decoración: el modelo vuelve a comprobarlo al invitar, de modo que
 * manipular el HTML no sirve de nada.
 */

SessionManager::requireAuth('/login');
SessionManager::requirePermiso(Roles::USUARIOS_INVITAR, '/dashboard');

$empresaId = Tenant::id();

$rolActual   = SessionManager::getRole();
$asignables  = Roles::asignablesPor($rolActual);
$csrfToken   = SessionManager::getCsrfToken();

$invitaciones = [];
$empresa      = null;
$sinCupo      = false;

if ($empresaId !== null) {
    $invitaciones = (new InvitacionModel())->pendientesDe($empresaId);
    $empresaModel = new EmpresaModel();
    $empresa      = $empresaModel->find($empresaId);
    $sinCupo      = !$empresaModel->admiteOtroUsuario($empresaId);
}

$pageTitle = 'Equipo';
$h = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$etiquetasEstado = [
    InvitacionModel::PENDIENTE => ['Pendiente',  'eq-pend'],
    InvitacionModel::ACEPTADA  => ['Activada',   'eq-ok'],
    InvitacionModel::CANCELADA => ['Cancelada',  'eq-off'],
    'expirada'                 => ['Caducada',   'eq-off'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style nonce="<?= $h($cspNonce) ?>">
        .eq-wrap   { padding: 24px; max-width: 1050px; }
        .eq-card   { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .eq-card h2{ margin: 0 0 4px; font-size: 17px; }
        .eq-card p.sub { margin: 0 0 18px; color: #64748b; font-size: 13px; }
        .eq-grid   { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
        .eq-grid label { display: block; font-size: 12px; color: #475569; margin-bottom: 6px; }
        .eq-grid input, .eq-grid select {
            width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #fff;
        }
        .eq-tabla  { width: 100%; border-collapse: collapse; }
        .eq-tabla th { text-align: left; font-size: 12px; color: #64748b; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .eq-tabla td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .eq-badge  { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .eq-pend   { background: #fef9c3; color: #854d0e; }
        .eq-ok     { background: #dcfce7; color: #166534; }
        .eq-off    { background: #e2e8f0; color: #475569; }
        .eq-btn    { padding: 6px 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 7px; cursor: pointer; font-size: 13px; }
        .eq-btn.prim { background: #0f172a; color: #fff; border-color: #0f172a; }
        .eq-btn:disabled { opacity: .5; cursor: not-allowed; }
        .eq-msg    { padding: 12px 14px; border-radius: 9px; margin-bottom: 16px; font-size: 14px; display: none; }
        .eq-msg.ok { background: #dcfce7; color: #166534; display: block; }
        .eq-msg.err{ background: #fee2e2; color: #991b1b; display: block; }
        .eq-enlace { font-family: ui-monospace, monospace; font-size: 12px; word-break: break-all;
                     background: #f1f5f9; padding: 9px 11px; border-radius: 7px; margin-top: 10px; display: none; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../partials/_navbar.php'; ?>

<main class="main-content eq-wrap">

    <h1 style="font-size:22px;margin:0 0 20px">
        Equipo<?= $empresa ? ' · ' . $h((string) $empresa['nombre']) : '' ?>
    </h1>

    <?php if ($empresaId === null): ?>

        <div class="eq-card">
            <h2>No hay empresa seleccionada</h2>
            <p class="sub" style="margin:0">
                Estás en vista de plataforma. Entra a una empresa desde
                <a href="/plataforma/empresas">Empresas</a> para gestionar su equipo.
            </p>
        </div>

    <?php else: ?>

        <div id="eqMsg" class="eq-msg"></div>
        <div id="eqEnlace" class="eq-enlace"></div>

        <section class="eq-card">
            <h2>Invitar a alguien</h2>
            <p class="sub">
                No se crea una contraseña aquí: la persona la elige al activar su cuenta,
                con un enlace de un solo uso válido <?= (int) InvitacionModel::VIGENCIA_DIAS ?> días.
            </p>

            <?php if ($sinCupo): ?>
                <div class="eq-msg err" style="display:block">
                    La empresa alcanzó el máximo de usuarios de su plan
                    (<?= (int) ($empresa['max_usuarios'] ?? 0) ?>). Amplía el plan para invitar a más gente.
                </div>
            <?php endif; ?>

            <form id="formInvitar">
                <div class="eq-grid">
                    <div>
                        <label for="i-correo">Correo *</label>
                        <input id="i-correo" name="correo" type="email" required maxlength="254" <?= $sinCupo ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label for="i-nombre">Nombre</label>
                        <input id="i-nombre" name="nombre" maxlength="80" <?= $sinCupo ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label for="i-apellido">Apellido</label>
                        <input id="i-apellido" name="apellido" maxlength="80" <?= $sinCupo ? 'disabled' : '' ?>>
                    </div>
                    <div>
                        <label for="i-rol">Perfil</label>
                        <select id="i-rol" name="rol" <?= $sinCupo ? 'disabled' : '' ?>>
                            <?php foreach ($asignables as $rol): ?>
                                <option value="<?= $h($rol) ?>"><?= $h(Roles::etiqueta($rol)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top:18px">
                    <button type="submit" class="eq-btn prim" <?= $sinCupo ? 'disabled' : '' ?>>Enviar invitación</button>
                </div>
            </form>
        </section>

        <section class="eq-card">
            <h2>Invitaciones</h2>
            <p class="sub">Las caducadas y canceladas se conservan para dejar rastro de quién invitó a quién.</p>

            <table class="eq-tabla">
                <thead>
                    <tr>
                        <th>Correo</th>
                        <th>Perfil</th>
                        <th>Estado</th>
                        <th>Caduca</th>
                        <th>Invitó</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invitaciones as $inv):
                    $estado   = (string) $inv['estado'];
                    $caducada = $estado === InvitacionModel::PENDIENTE
                                && strtotime((string) $inv['expira_en']) < time();
                    $clave    = $caducada ? 'expirada' : $estado;
                    [$texto, $css] = $etiquetasEstado[$clave] ?? [$estado, 'eq-off'];
                ?>
                    <tr>
                        <td>
                            <strong><?= $h((string) $inv['correo']) ?></strong>
                            <?php $nom = trim((string) $inv['nombre'] . ' ' . (string) $inv['apellido']); ?>
                            <?php if ($nom !== ''): ?>
                                <div style="color:#94a3b8;font-size:12px"><?= $h($nom) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $h(Roles::etiqueta((string) $inv['rol'])) ?></td>
                        <td><span class="eq-badge <?= $css ?>"><?= $h($texto) ?></span></td>
                        <td><?= $h(date('d/m/Y', strtotime((string) $inv['expira_en']))) ?></td>
                        <td><?= $h((string) ($inv['invitado_por_nombre'] ?? '—')) ?></td>
                        <td style="text-align:right;white-space:nowrap">
                            <?php if ($estado === InvitacionModel::PENDIENTE): ?>
                                <button class="eq-btn" data-accion="reenviar" data-id="<?= (int) $inv['id'] ?>">Reenviar</button>
                                <button class="eq-btn" data-accion="cancelar" data-id="<?= (int) $inv['id'] ?>">Cancelar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($invitaciones === []): ?>
                    <tr><td colspan="6" style="color:#94a3b8">Todavía no se ha invitado a nadie.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

    <?php endif; ?>
</main>

<script nonce="<?= $h($cspNonce) ?>">
(function () {
    'use strict';

    const CSRF   = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
    const msg    = document.getElementById('eqMsg');
    const enlace = document.getElementById('eqEnlace');

    if (!msg) { return; }

    function aviso(texto, ok) {
        msg.textContent = texto;
        msg.className   = 'eq-msg ' + (ok ? 'ok' : 'err');
    }

    async function llamar(cuerpo) {
        const r = await fetch('/api/invitaciones', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf: CSRF }, cuerpo))
        });
        return { ok: r.ok, data: await r.json().catch(() => ({})) };
    }

    function mostrarEnlace(url) {
        enlace.textContent   = 'Enlace de activación: ' + url;
        enlace.style.display = 'block';
    }

    const form = document.getElementById('formInvitar');

    if (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();

            const boton = form.querySelector('button[type=submit]');
            boton.disabled = true;

            const datos = Object.fromEntries(new FormData(form).entries());
            datos.accion = 'invitar';

            const { ok, data } = await llamar(datos);
            aviso(data.msg || (ok ? 'Invitación enviada.' : 'No se pudo invitar.'), ok);

            if (ok && data.enlace) {
                mostrarEnlace(data.enlace);
                form.reset();
            }

            boton.disabled = false;
        });
    }

    document.querySelectorAll('[data-accion]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const accion = btn.dataset.accion;

            if (accion === 'cancelar' && !confirm('¿Cancelar esta invitación? El enlace dejará de servir.')) {
                return;
            }

            btn.disabled = true;
            const { ok, data } = await llamar({ accion: accion, id: Number(btn.dataset.id) });

            if (ok && accion === 'reenviar' && data.enlace) {
                aviso(data.msg, true);
                mostrarEnlace(data.enlace);
                btn.disabled = false;
                return;
            }

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
