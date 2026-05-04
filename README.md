CRM Seguros LUX

Aplicación PHP 8.1+ (enrutador propio, MySQL, integraciones Telegram/WhatsApp, almacenamiento S3 compatible).


Requisitos

PHP 8.1 o superior con extensiones pdo_mysql, mbstring, curl, json

MySQL/MariaDB

Composer


Instalación

Clonar el repositorio y entrar al directorio del proyecto.

Instalar dependencias:

 composer install --no-dev --optimize-autoloader

 Para desarrollo (incluye PHPUnit): composer install


Configurar entorno: no pegues formato dotenv (KEY=valor) en .env.php. Copia config/env.php.example a la raíz como .env.php (debe ser PHP con <?php y putenv(...)). Si el contenido no es PHP válido, PHP puede mostrar ese texto en el HTML del CRM al hacer require.

Estáticos (CSS/JS) y 404: lo ideal es que el DocumentRoot apunte a la carpeta public/. Si tu URL real de JS/CSS es https://tu-dominio/public/js/..., define en .env.php: putenv('ASSET_PREFIX=/public'); (ajusta el prefijo real). Las vistas usan asset_url().

Permisos de escritura en uploads/, logs/ y storage/ según corresponda.

Virtual host apuntando a public/ cuando el hosting lo permita.


Variables importantes

Ver /env.php.example y comentarios Destacadas:

CORS_ALLOWED_ORIGINS: orígenes permitidos para la API de horas ocupadas (no uses * en producción).

WEBHOOK_DEBUG_LOG: solo 1 en depuración; evita registrar payloads sensibles en disco.

Credenciales SMTP, iDrive, Telegram y WhatsApp nunca deben quedar en el código.


Salud y monitoreo

GET /api/health — liveness (JSON, no consulta base de datos; útil para balanceadores).

GET /api/health?deep=1&token=TU_TOKEN — readiness: verifica BD. Define HEALTH_CHECK_TOKEN en el entorno y usa el mismo valor en token (comparación con hash_equals).


Pruebas y CI

vendor/bin/phpunit

En GitHub, el workflow .github/workflows/ci.yml ejecuta composer install, composer audit --no-dev, PHPUnit y comprobación de sintaxis PHP (php -l).


Seguridad post-despliegue

Rotar cualquier secreto que haya existido antes en el repositorio o en logs.

No versionar .env.php, logs ni archivos bajo storage/ratelimit/.

