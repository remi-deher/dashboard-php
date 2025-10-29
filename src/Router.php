<?php
// Fichier: /src/Router.php

namespace App;

class Router {
    private array $routes = [];

    /**
     * Ajoute une route au routeur.
     *
     * @param string $method Méthode HTTP (GET, POST, etc.)
     * @param string $path Chemin de l'URL (peut contenir des paramètres comme {id})
     * @param array $handler Tableau contenant l'instance du contrôleur et le nom de la méthode.
     */
    public function add(string $method, string $path, array $handler): void {
        // Nettoyer le chemin pour éviter les doubles slashes, sauf au début
        $path = '/' . trim(str_replace('//', '/', $path), '/');

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    /**
     * Exécute le routeur pour trouver et appeler le bon handler.
     */
    public function run(): void {
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            // Convertir le chemin de la route en regex pour capturer les paramètres
            // Ex: /service/edit/{id} devient #^/service/edit/(?P<id>[a-zA-Z0-9_]+)$#
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            // Vérifier si la méthode HTTP et le chemin correspondent
            if ($route['method'] === $requestMethod && preg_match($pattern, $requestUri, $matches)) {
                // Récupérer les paramètres capturés par la regex (ex: ['id' => '123'])
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Vérifier que le handler est valide (objet contrôleur et méthode existante)
                [$controller, $method] = $route['handler'];
                if (is_object($controller) && method_exists($controller, $method)) {
                    try {
                        // Appeler la méthode du contrôleur en passant les paramètres par position
                        // array_values() assure que les paramètres sont passés dans l'ordre attendu par la méthode
                        call_user_func_array([$controller, $method], array_values($params));
                    } catch (\ArgumentCountError $e) {
                         // Gérer les erreurs de nombre d'arguments (ex: route mal définie)
                         http_response_code(500);
                         error_log("Router Error: Argument count mismatch for route {$route['path']} - " . $e->getMessage());
                         echo "Erreur interne du serveur (Router).";
                    } catch (\Exception $e) {
                        // Gérer les autres exceptions levées par le contrôleur
                        http_response_code(500);
                        error_log("Controller Error: Route {$route['path']} - " . $e->getMessage());
                        // Ne pas afficher $e->getMessage() en production
                        echo "Erreur interne du serveur.";
                    }
                    return; // Arrêter le routeur dès qu'une route correspond
                } else {
                    // Si le handler n'est pas valide (erreur de configuration)
                    http_response_code(500);
                    error_log("Router Configuration Error: Invalid handler for route {$route['path']}");
                    echo "Erreur interne de configuration du serveur.";
                    return;
                }
            }
        }

        // Si aucune route n'a correspondu
        http_response_code(404);
        echo "404 Not Found";
    }
}
