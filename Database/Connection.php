<?php
declare(strict_types=1);

namespace Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    private static ?PDO $conn = null;

    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$conn === null) {
            $host   = getenv('DB_HOST') ?: 'localhost';
            $user   = getenv('DB_USER') ?: 'root';
            $pass   = getenv('DB_PASS') ?: '';
            $dbname = getenv('DB_NAME') ?: 'grupoide_seguroslux';

            try {
                self::$conn = new PDO(
                    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                    $user,
                    $pass,
                    [PDO::ATTR_PERSISTENT => true]
                );
                self::$conn->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
                self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);
            } catch (PDOException $e) {
                throw new RuntimeException('Error en la conexión: ' . $e->getMessage());
            }
        }

        return self::$conn;
    }

    /**
     * Inyecta una conexión PDO externa (útil para tests con SQLite en memoria).
     *
     * Solo se puede llamar si APP_ENV=testing para evitar que código
     * de producción sobreescriba accidentalmente la conexión real.
     *
     * @internal Usar únicamente desde tests/bootstrap.php.
     */
    public static function setTestConnection(PDO $pdo): void
    {
        if (getenv('APP_ENV') !== 'testing') {
            throw new RuntimeException(
                'setTestConnection() solo puede llamarse en entorno de testing.'
            );
        }
        self::$conn = $pdo;
    }

    /**
     * Resetea la conexión. Útil entre tests para forzar reconexión.
     *
     * @internal Usar únicamente desde tests.
     */
    public static function reset(): void
    {
        if (getenv('APP_ENV') !== 'testing') {
            throw new RuntimeException('reset() solo puede llamarse en entorno de testing.');
        }
        self::$conn = null;
    }
}