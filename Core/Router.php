<?php
declare(strict_types=1);

/**
 * Router simple compatible con Windows y Linux.
 * Normaliza los separadores de ruta para evitar mixed-separator errors en XAMPP.
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $path, mixed $handler): void
    {
        $this->routes[] = compact('method', 'path', 'handler');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            $pattern = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if ($route['method'] === $method && preg_match($pattern, $path, $matches)) {
                foreach ($matches as $k => $v) {
                    if (is_string($k)) $_GET[$k] = $v;
                }
                $this->run($route['handler']);
                return;
            }
        }

        http_response_code(404);
        require $this->normalizePath(__DIR__ . '/../App/Views/errors/404.php');
    }

    private function run(mixed $handler): void
    {
        if (is_callable($handler)) {
            $handler();
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            (new $class())->$method();
            return;
        }

        if (is_string($handler) && class_exists($handler)) {
            $handler::handle();
            return;
        }

        throw new \RuntimeException("Handler inválido: " . print_r($handler, true));
    }

    /**
     * Normaliza separadores de ruta — soluciona el problema de XAMPP en Windows
     * forzando el uso de barras estándar (/) y limpiando duplicados.
     */
    public static function normalizePath(string $path): string
    {
        // 1. Convertir todas las barras invertidas de Windows (\) a barras normales (/)
        $path = str_replace('\\', '/', $path);
        
        // 2. Limpiar barras dobles consecutivas por si acaso (ej. // -> /)
        return preg_replace('#/+#', '/', $path);
    }
}
