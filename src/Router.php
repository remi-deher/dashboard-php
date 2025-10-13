<?php
// Fichier: /src/Router.php

namespace App;

class Router {
    private array $routes = [];

    public function add(string $method, string $path, array $handler): void {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function run(): void {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[a-zA-Z0-9_]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if ($route['method'] === $requestMethod && preg_match($pattern, $requestUri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$controller, $method] = $route['handler'];
                
                if (is_object($controller) && method_exists($controller, $method)) {
                    // --- CORRECTION CI-DESSOUS ---
                    // On utilise array_values() pour passer les paramètres par position et non par nom.
                    call_user_func_array([$controller, $method], array_values($params));
                    // --- FIN DE LA CORRECTION ---
                    return;
                }
            }
        }

        http_response_code(404);
        echo "404 Not Found";
    }
}
