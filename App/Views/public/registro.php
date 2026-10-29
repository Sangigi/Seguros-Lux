<?php
declare(strict_types=1);

use App\Models\InvitacionModel;
use App\Security\SessionManager;

/**
 * Activación de cuenta por invitación.
 *
 * Página pública, pero no abierta: solo hace algo con un token válido. Es el
 * único sitio donde se crea un usuario, y la contraseña la fija la propia
 * persona, de modo que ni quien invita ni el propietario de la plataforma
 * llegan a conocerla.
 *
 * Sobre los mensajes de error: un token inexistente, uno ya usado, uno
 * cancelado y uno caducado dan exactamente la misma respuesta. Si se
 * distinguieran, cualquiera podría probar tokens y averiguar cuáles
 * existieron; y como el correo invitado se muestra en la pantalla siguiente,
 * eso además revelaría quién trabaja en qué empresa.
 */

SessionManager::enforceHttps();
SessionManager::sendSecurityHeaders();
SessionManager::start();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

$modelo     = new InvitacionModel();
$invitacion = $modelo->porToken($token);

$error      = null;
$completado = false;

if ($invitacion !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!SessionManager::validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'El formulario caducó. Vuelve a intentarlo.';
    } elseif (($_POST['contrasena'] ?? '') !== ($_POST['contrasena2'] ?? '')) {
        $error = 'Las dos contraseñas no coinciden.';
    } else {
        $resultado = $modelo->aceptar(
            $token,
            (string) ($_POST['contrasena'] ?? ''),
            [
                'nombre'   => (string) ($_POST['nombre']   ?? ''),
                'apellido' => (string) ($_POST['apellido'] ?? ''),
                'telefono' => (string) ($_POST['telefono'] ?? ''),
            ]
        );

        if ($resultado['ok'] ?? false) {
            $completado = true;
        } else {
            $error = $resultado['error'] ?? 'No se pudo activar la cuenta.';
        }
    }
}

$csrfToken = SessionManager::getCsrfToken();
$h = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= asset_url('css/LR.css') ?>">
    <title>Activar cuenta | Seguros Lux</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_url('images/logo.png') ?>">
</head>
<body>
<div class="w" id="lrRoot">

    <div class="left" id="leftPanel">
        <div class="left-logo">
            <div class="left-logo-mark">
                <div class="logo-square"></div>
                <div class="logo-wordmark">Seguros Lux</div>
            </div>
        </div>

        <div class="left-content">
            <div class="left-tag">Alta de usuario</div>
            <div class="left-headline">
                Crea<br>tu acceso<br><em>en un paso.</em>
            </div>
        </div>

        <div class="left-foot">© <?= date('Y') ?> Lux Seguros · Todos los derechos reservados</div>
    </div>

    <div class="right">
        <div class="bg-num">02</div>

        <?php if ($invitacion === null): ?>

            <div class="form-header">
                <div class="form-eyebrow">Enlace no válido</div>
                <div class="form-title">Esta invitación<br>ya no sirve</div>
            </div>

            <div class="alert show" style="background:rgba(239,68,68,.1);color:#991b1b;border-color:rgba(239,68,68,.2)">
                El enlace no es válido, ya se usó o caducó. Pide a quien te invitó que te mande uno nuevo.
            </div>

            <p style="margin-top:24px"><a href="/login">Volver a iniciar sesión</a></p>

        <?php elseif ($completado): ?>

            <div class="form-header">
                <div class="form-eyebrow">Todo listo</div>
                <div class="form-title">Tu cuenta<br>está activa</div>
            </div>

            <div class="alert show" style="background:rgba(34,197,94,.1);color:#166534;border-color:rgba(34,197,94,.2)">
                Ya puedes entrar con <strong><?= $h($invitacion['correo']) ?></strong>
                en <strong><?= $h($invitacion['empresa_nombre']) ?></strong>.
            </div>

            <div class="btn-wrap" style="margin-top:24px">
                <a class="btn-submit" href="/login" style="display:inline-block;text-align:center;text-decoration:none">
                    Iniciar sesión
                </a>
            </div>

        <?php else: ?>

            <div class="form-header">
                <div class="form-eyebrow">
                    Invitación de <?= $h($invitacion['empresa_nombre']) ?>
                </div>
                <div class="form-title">Activa<br>tu cuenta</div>
            </div>

            <p style="margin:-8px 0 20px;color:#64748b;font-size:14px">
                Se creará el acceso de <strong><?= $h($invitacion['correo']) ?></strong>
                con el perfil de <strong><?= $h(\App\Security\Roles::etiqueta((string) $invitacion['rol'])) ?></strong>.
            </p>

            <?php if ($error !== null): ?>
                <div class="alert show" style="background:rgba(239,68,68,.1);color:#991b1b;border-color:rgba(239,68,68,.2)">
                    <?= $h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/registro" novalidate autocomplete="off">

                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="token" value="<?= $h($token) ?>">

                <div class="field">
                    <div class="field-inner">
                        <span class="field-label">Nombre</span>
                        <input class="field-inp" type="text" name="nombre" required maxlength="80"
                               value="<?= $h((string) ($_POST['nombre'] ?? $invitacion['nombre'])) ?>">
                        <div class="field-bar"></div>
                    </div>
                </div>

                <div class="field">
                    <div class="field-inner">
                        <span class="field-label">Apellido</span>
                        <input class="field-inp" type="text" name="apellido" maxlength="80"
                               value="<?= $h((string) ($_POST['apellido'] ?? $invitacion['apellido'])) ?>">
                        <div class="field-bar"></div>
                    </div>
                </div>

                <div class="field">
                    <div class="field-inner">
                        <span class="field-label">Teléfono</span>
                        <input class="field-inp" type="tel" name="telefono" maxlength="30"
                               value="<?= $h((string) ($_POST['telefono'] ?? $invitacion['telefono'])) ?>">
                        <div class="field-bar"></div>
                    </div>
                </div>

                <div class="field">
                    <div class="field-inner">
                        <span class="field-label">Contraseña</span>
                        <input class="field-inp" type="password" name="contrasena" required
                               autocomplete="new-password" maxlength="128">
                        <div class="field-bar"></div>
                    </div>
                </div>

                <div class="field">
                    <div class="field-inner">
                        <span class="field-label">Repite la contraseña</span>
                        <input class="field-inp" type="password" name="contrasena2" required
                               autocomplete="new-password" maxlength="128">
                        <div class="field-bar"></div>
                    </div>
                </div>

                <p style="margin:-4px 0 18px;color:#64748b;font-size:13px">
                    <?= $h(InvitacionModel::REQUISITOS_PASSWORD) ?>
                </p>

                <div class="btn-wrap">
                    <button type="submit" class="btn-submit">
                        <div class="btn-text-wrap"><span>Activar mi cuenta</span></div>
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
