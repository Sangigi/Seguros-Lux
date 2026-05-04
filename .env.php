<?php

/**
 * ASSET_PREFIX: déjalo vacío si el virtual host apunta directamente a la carpeta public/.
 * Si los estáticos se sirven bajo un subpath (p. ej. /public/js/...), define: putenv('ASSET_PREFIX=/public');
 */
declare(strict_types=1);

putenv('APP_ENV=production');

putenv('DB_HOST=seguroslux.mx');
putenv('DB_USER=grupoide_leonel');
putenv('DB_PASS=M4imvdG#O&NQ');
putenv('DB_NAME=grupoide_seguroslux');

putenv('TELEGRAM_BOT_TOKEN=8644833821:AAEDTc9remirUvHXAy-t3VLuxCmLZR1r4ic');
putenv('TELEGRAM_WEBHOOK_URL=https://seguroslux.mx/telegram/webhook');

putenv('WHATSAPP_TOKEN=EAAbrD8owjq8BRQVyYmKvajev9pxKIzwVoDout8gFkMmJ6a98Rn1Li9mHVZAvL3IcnpsIVaaiRzwGoy30D1n5ob98CUZCjyISMmPWhBV9ZCCJ8KgyrEMuiwRdaUOZCiRnXo27LQt4fTKrrXx0RFlLnDwSN4kVSngiqyBnhRUiMxXcSyxkswA8zZBCcXJV1ZAGwcQgZDZD');
putenv('WHATSAPP_PHONE_ID=1089960074196593');  
putenv('WHATSAPP_BUSINESS_ID=1644305643387302');
putenv('WHATSAPP_VERIFY_TOKEN=siyolecontestosedesespera');
putenv('WHATSAPP_BUSINESS_NUMBER=525514605959'); 

$_ENV['WHATSAPP_TOKEN']           = getenv('WHATSAPP_TOKEN');
$_ENV['WHATSAPP_PHONE_ID']        = getenv('WHATSAPP_PHONE_ID');
$_ENV['WHATSAPP_BUSINESS_ID']     = getenv('WHATSAPP_BUSINESS_ID');
$_ENV['WHATSAPP_VERIFY_TOKEN']    = getenv('WHATSAPP_VERIFY_TOKEN');
$_ENV['WHATSAPP_BUSINESS_NUMBER'] = '525514605959';

putenv('WEBHOOK_DEBUG_LOG=0');

putenv('HEALTH_CHECK_TOKEN=');

putenv('CORS_ALLOWED_ORIGINS=https://seguroslux.mx,https://www.seguroslux.mx');

putenv('SMTP_HOST=mail.seguroslux.mx');
putenv('SMTP_USER=contacto@seguroslux.mx');
putenv('SMTP_PASS=S3guro$1ux2026');
putenv('SMTP_PORT=465');

putenv('IDRIVE_ENDPOINT=s3.us-west-2.idrivee2.com');
putenv('IDRIVE_BUCKET=crmseguros');
putenv('IDRIVE_REGION=us-west-2');
putenv('IDRIVE_KEY_ID=LpEJcTtB5eCfhGOaNrbH');
putenv('IDRIVE_KEY_SECRET=2NhgciTLiiNAqOe5sITi1VAXh9O6q8e0Cbls8rXK');

/** Vacío = URLs /js/... y /css/... (DocumentRoot = public/). Ejemplo: /public */
putenv('ASSET_PREFIX=');