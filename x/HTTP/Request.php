<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\HTTP;

class Request
{
    private array $parameters = [];

    public function getPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        if ($position === false) {
            return htmlspecialchars($path);
        }
        return htmlspecialchars(substr($path, 0, $position));
    }

    public function method(): string
    {
        return $_POST['_method'] ?? strtoupper($_SERVER['REQUEST_METHOD']);
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    public function isPatch(): bool
    {
        return $this->method() === 'PATCH';
    }

    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }

    public function get(string $key = '', mixed $default = ''): mixed
    {
        $source = $this->isGet() ? $_GET : $_POST;
        return $this->getEscapedValue($source, $key, $default);
    }

    public function post(string $key = '', mixed $default = ''): mixed
    {
        return $this->getEscapedValue($_POST, $key, $default);
    }

    public function files(string $key = '', mixed $default = ''): mixed
    {
        return $_FILES[$key] ?? $default;
    }

    public function getBody(): array
    {
        $body = [];

        foreach ($_REQUEST as $key => $value) {
            $body[$key] = $this->sanitize($value);
        }

        return $body;
    }

    public function getData($input = false): false|array|string
    {
        if (!$input) {
            return array_map([$this, 'sanitize'], $_REQUEST);
        }

        return isset($_REQUEST[$input]) ? $this->sanitize($_REQUEST[$input]) : false;
    }

    public function sanitize($dirty): string
    {
        return htmlspecialchars($dirty, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function parameter($parameter, $default = null)
    {
        return $this->parameters[$parameter] ?? $default;
    }

    private function getEscapedValue(array $source, string $key, mixed $default): mixed
    {
        if (empty($key)) {
            return array_map([$this, 'sanitize'], $source);
        }

        return isset($source[$key]) ? $this->sanitize($source[$key]) : $this->sanitize($default);
    }
}