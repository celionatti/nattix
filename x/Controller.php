<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X;

use X\HTTP\Response;
use X\Middleware\Middleware;
use X\View\Xview;

class Controller
{
    public Xview $view;
    public string $action = '';
    protected string|null|int $currentUser;

    /**
     * @var Middleware[]
     */
    protected array $middlewares = [];

    public function __construct()
    {
        $this->view = new Xview();
        $this->onConstruct();
    }

    public function setCurrentUser($user): void
    {
        // Allow the developer to set the current user
        $this->currentUser = $user;
    }

    public function registerMiddleware(Middleware $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * @return Middleware[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function json_response($data, $statusCode = 200, $headers = [], $options = JSON_PRETTY_PRINT, $enableCompression = true): void
    {
        // Allow for additional custom headers
        $defaultHeaders = [
            "Access-Control-Allow-Origin" => "*",
            "Content-Type" => "application/json",
        ];

        // Merge custom headers with default headers
        $mergedHeaders = array_merge($defaultHeaders, $headers);

        // CORS headers
        $mergedHeaders['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
        $mergedHeaders['Access-Control-Allow-Headers'] = 'Content-Type';

        // Enable Gzip Compression if specified
        if ($enableCompression) {
            // Compression: Enable Gzip Compression
            ob_start("ob_gzhandler");

            // Compression: Set Content-Encoding Header
            header('Content-Encoding: gzip');
        }

        http_response_code($statusCode);

        foreach ($mergedHeaders as $name => $value) {
            header("$name: $value");
        }

        $json = json_encode($data, $options);

        if ($json === false) {
            $this->json_error_response('Error encoding JSON', 500);
            return;
        }

        // JSONP support
        $callback = $_GET['callback'] ?? null;

        if (!empty($callback)) {
            echo $callback . '(' . $json . ');';
        } else {
            echo $json;
        }

        die;
    }

    /**
     * Recursively applies htmlspecialchars to data if sanitization is enabled.
     *
     * @param mixed $data
     * @return string|array
     */
    private function sanitizeData(mixed $data): string|array
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeData'], $data);
        } else {
            return htmlspecialchars($data);
        }
    }

    public function json_error_response($message, $statusCode = 500): void
    {
        $errorResponse = [
            'error' => true,
            'message' => $message,
        ];

        $this->json_response($errorResponse, $statusCode);
    }

    public function onConstruct(): void
    {
    }
}