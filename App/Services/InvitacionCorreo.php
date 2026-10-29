<?php
declare(strict_types=1);

namespace App\Services;

use App\Security\Roles;

/**
 * Correo con el enlace para activar una cuenta invitada.
 *
 * Se envía en el momento y no por la cola: quien invita está mirando la
 * pantalla y tiene que saber si el correo salió. Si no sale (SMTP sin
 * configurar, servidor de correo caído), devuelve false y la pantalla ofrece
 * el enlace para compartirlo por otro canal. La invitación existe aunque el
 * correo no llegue.
 */
final class InvitacionCorreo
{
    public static function enviar(
        string $correo,
        string $nombre,
        string $empresa,
        string $rol,
        string $enlace,
        string $expira,
    ): bool {
        try {
            $mail = MailService::buildMailer();
            $mail->addAddress($correo, $nombre);
            $mail->Subject = 'Activa tu cuenta en ' . $empresa;
            $mail->Body    = self::cuerpoHtml($nombre, $empresa, $rol, $enlace, $expira);
            $mail->AltBody = self::cuerpoTexto($nombre, $empresa, $rol, $enlace, $expira);
            $mail->send();

            return true;
        } catch (\Throwable $e) {
            // Sin el enlace en el log: lleva el token en claro.
            error_log('[InvitacionCorreo] No se pudo enviar a ' . $correo . ': ' . $e->getMessage());

            return false;
        }
    }

    private static function cuerpoHtml(string $nombre, string $empresa, string $rol, string $enlace, string $expira): string
    {
        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $saludo = $nombre !== '' ? 'Hola, ' . $h($nombre) . ':' : 'Hola:';
        $perfil = $h(Roles::etiqueta($rol));
        $fecha  = $h(date('d/m/Y', strtotime($expira) ?: time()));

        return <<<HTML
            <div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#0f172a">
              <p>{$saludo}</p>
              <p>Te invitaron a unirte a <strong>{$h($empresa)}</strong> en el CRM de Seguros Lux con el perfil
                 <strong>{$perfil}</strong>.</p>
              <p>Para activar tu cuenta y elegir tu contraseña, entra a este enlace:</p>
              <p style="margin:28px 0">
                <a href="{$h($enlace)}" style="background:#0f172a;color:#ffffff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Activar mi cuenta</a>
              </p>
              <p style="font-size:13px;color:#475569">El enlace sirve una sola vez y vence el {$fecha}.
                 Si no esperabas esta invitación, ignora este correo.</p>
              <p style="font-size:12px;color:#94a3b8;word-break:break-all">{$h($enlace)}</p>
            </div>
            HTML;
    }

    private static function cuerpoTexto(string $nombre, string $empresa, string $rol, string $enlace, string $expira): string
    {
        $saludo = $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:';
        $perfil = Roles::etiqueta($rol);
        $fecha  = date('d/m/Y', strtotime($expira) ?: time());

        return "{$saludo}\n\n"
            . "Te invitaron a unirte a {$empresa} en el CRM de Seguros Lux con el perfil {$perfil}.\n\n"
            . "Activa tu cuenta y elige tu contraseña aquí:\n{$enlace}\n\n"
            . "El enlace sirve una sola vez y vence el {$fecha}. "
            . "Si no esperabas esta invitación, ignora este correo.\n";
    }
}
