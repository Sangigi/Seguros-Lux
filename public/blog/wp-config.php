<?php
// Begin Really Simple Security key
define('RSSSL_KEY', 'YAjwn330eZ7yWUiVdQWbR8a5PUAlLADXLCQmqDWI8ojwZkVPZWR3KSU8iHsJ4l81');
// End Really Simple Security key

define('WP_CACHE', true); // Added by SpeedyCache

// ^=^t^r Forzar HTTPS (NO hack, forma oficial WordPress)
define('FORCE_SSL_ADMIN', true);
define('FORCE_SSL_LOGIN', true);

define('WP_HOME', 'https://blog.seguroslux.mx');
define('WP_SITEURL', 'https://blog.seguroslux.mx');

/**
 * The base configuration for WordPress
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings ** //
define('DB_NAME', 'grupoide_wp628');
define('DB_USER', 'grupoide_wp628');
define('DB_PASSWORD', '6-p2S@J772');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 */
define('AUTH_KEY',         'zg1z5zii8ljdifizjemgrvzt2hivr5z4qlhqqfal626c5cmig53gaubnnu0uyimc');
define('SECURE_AUTH_KEY',  'xs95os6dco6d0crk9pw4el4w9n65sfxxnpzdqitrycpicodwoxhekootlgf8svr2');
define('LOGGED_IN_KEY',    '5sucstqtb3itm3941kxgiusikxxzqvpp02cthdq8lnmqqwufuxqhjjez1nuctria');
define('NONCE_KEY',        '4gylpmbo9y8kfoma0v99alqxfzqeckincnkeanad9efdmolh9xp4m9s8hkve43ba');
define('AUTH_SALT',        'ycim9jttllq1ccp0q2lmlevkfavfsgjfnkp1zqpjh3rngsmiczxbux5bkook6dtx');
define('SECURE_AUTH_SALT', 'hlocyzv7bgenadpt0pvy1my88pt8flekwamrl9bsmhtbyrsztfk7nfg8uzt6ea6y');
define('LOGGED_IN_SALT',   'y2wl6relucfvuw0fhcjukh5qgpnqhbktrgbanepejcw4rofoxik7rhzdzmrilqbs');
define('NONCE_SALT',       'e8ek69k7h7nztteefdujl8lqs5tnnhqj9cmp0tuyspx9peosidkcmveh6if2jtsr');
/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wpyo_';

/**
 * Debug mode.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
