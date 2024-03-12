<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\Router;

use Exception;
use X\HTTP\Request;
use X\HTTP\Response;
use X\X;

class Router
{
    private static array $routeMap;
//    private array $routeMap = [];
    private string $controllerNamespace = "\\Nattix\\controllers\\"; // Set your default namespace here

    public function __construct(public Request $request, public Response $response)
    {
    }

    public static function get(string $url, $callback): void
    {
        self::$routeMap['GET'][$url] = $callback;
    }

    public static function post(string $url, $callback): void
    {
        self::$routeMap['POST'][$url] = $callback;
    }

    public function put(string $url, $callback): void
    {
        self::$routeMap['PUT'][$url] = $callback;
    }

    public function delete(string $url, $callback): void
    {
        self::$routeMap['DELETE'][$url] = $callback;
    }

    public function patch(string $url, $callback): void
    {
        self::$routeMap['PATCH'][$url] = $callback;
    }

    public function resource(string $url, string $controller): void
    {
        // Implement resource controllers for CRUD routes
        self::get("$url", "$controller@index");
        self::get("$url/create", "$controller@create");
        self::post("$url", "$controller@store");
        self::get("$url/{id}", "$controller@show");
        self::get("$url/{id}/edit", "$controller@edit");
        self::put("$url/{id}", "$controller@update");
        self::delete("$url/{id}", "$controller@destroy");
    }

    /**
     * @param $method
     * @return array
     */
    public function getRouteMap($method): array
    {
        return self::$routeMap[$method] ?? [];
    }

    public function setControllerNamespace(string $namespace): void
    {
        $this->controllerNamespace = $namespace;
    }

    public function getCallback()
    {
        $method = $this->request->method();
        $url = $this->request->getPath();
        // Trim slashes
        $url = trim($url, '/');

        // Get all routes for current request method
        $routes = $this->getRouteMap($method);

        $routeParams = false;

        // Start iterating register routes
        foreach ($routes as $route => $callback) {
            // Trim slashes
            $route = trim($route, '/');
            $routeNames = [];

            if (!$route) {
                continue;
            }

            // Find all route names from route and save in $routeNames
            if (preg_match_all('/\{(\w+)(:[^}]+)?}/', $route, $matches)) {
                $routeNames = $matches[1];
            }

            // Convert route name into regex pattern
            // $routeRegex = "@^" . preg_replace_callback('/\{\w+(:([^}]+))?}/', fn ($m) => isset($m[2]) ? "({$m[2]})" : '(\w+)', $route) . "$@";
            $routeRegex = "@^" . preg_replace_callback('/\{(\w+)(:([^}]+))?}/', function ($m) {
                    $paramName = $m[1];
                    $paramPattern = $m[3] ?? '\w+';
                    return "($paramPattern)";
                }, $route) . "$@";

            // Test and match current route against $routeRegex
            if (preg_match_all($routeRegex, $url, $valueMatches)) {
                $values = [];
                for ($i = 1; $i < count($valueMatches); $i++) {
                    $values[] = $valueMatches[$i][0];
                }
                $routeParams = array_combine($routeNames, $values);

                $this->request->setParameters($routeParams);
                return $callback;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function resolve()
    {
        $method = $this->request->method();
        $url = $this->request->getPath();
        $callback = self::$routeMap[$method][$url] ?? false;
        if (!$callback) {

            $callback = $this->getCallback();

            if ($callback === false) {
                throw new Exception("Callback - [ Method: {$method}, Path: {$url} ] - Not Found", 404, 'info');
            }
        }
        if (is_string($callback)) {
            // Split the string based on the "@" symbol
            $callbackParts = explode('@', $callback);

            // Ensure we have both controller and action parts
            if (count($callbackParts) === 2) {
                $controllerName = $callbackParts[0];
                $actionName = $callbackParts[1];

                if (!method_exists($controllerName, $actionName)) {
                    throw new Exception("[{$controllerName}] - [{$actionName}] Method Not Found");
                }

                // Create the controller instance
                $controllerClass = "$this->controllerNamespace . $controllerName";
                $controller = new $controllerClass();
                $controller->action = $actionName;

                // Set the controller in your application (you'll need to modify this according to your application's structure)
                X::$x->controller = $controller;

                // Execute any middlewares
                $middlewares = $controller->getMiddlewares();
                foreach ($middlewares as $middleware) {
                    $middleware->execute();
                }

                // Replace the $callback variable with the controller and action
                $callback = [$controller, $actionName];
            }
        }
        if (is_array($callback)) {
            /**
             * @var $controller
             */
            $controller = new $callback[0];
            $controller->action = $callback[1];
            X::$x->controller = $controller;
            $middlewares = $controller->getMiddlewares();
            foreach ($middlewares as $middleware) {
                $middleware->execute();
            }
            $callback[0] = $controller;
        }
        return call_user_func($callback, $this->request, $this->response);
    }
}