<?php
/**
 * Partial: _head.php
 * Uso: require __DIR__ . '/../partials/_head.php';
 * Variables esperadas: $pageTitle (string)
 *
 * Define $cspNonce UNA SOLA VEZ aquí para que todos los partials
 * y la vista usen el mismo valor en sus atributos nonce="".
 * SessionManager::nonce() genera un valor nuevo en cada llamada,
 * por lo que llamarlo varias veces produce nonces distintos que
 * el navegador rechaza al comparar con el del header CSP.
 */
$pageTitle ??= 'CRM EIRA';

// ── Nonce único para toda la petición ─────────────────────────────────────────
// Se define solo si aún no existe (por si _head.php se incluye más de una vez).
if (!isset($cspNonce)) {
    $cspNonce = \App\Security\SessionManager::nonce();
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> | CRM EIRA</title>
<link rel="stylesheet" href="/css/styles.css">
<link rel="stylesheet" href="/css/crm-theme.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<style nonce="<?= $cspNonce ?>">
/* ── Aliases de variables para compatibilidad con vistas del CRM ── */
:root {
    --accent-color:    var(--accent);
    --bg-secondary:    var(--bg-subtle);
    --bg-card:         #ffffff;
    --border-color:    var(--border);
    --border-radius:   var(--radius-md);
    --border-radius-sm:var(--radius-sm);
    --text-primary:    #0f172a;
    --text-secondary:  #475569;
    --text-muted:      #94a3b8;
    --success-color:   var(--success);
    --danger-color:    var(--danger);
    --warning-color:   var(--warning);
    --info-color:      var(--info);
    --success-light:   rgba(34,197,94,.12);
    --danger-light:    rgba(239,68,68,.12);
    --warning-light:   rgba(245,158,11,.12);
}
</style>