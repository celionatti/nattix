<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X Session
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X;

class Session
{
    private static ?Session $instance = null;

    private function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            $this->configureSession();
            session_start();
        }
    }

    private function __clone() {}

    public static function getInstance(): Session
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function set($key, $value): void
    {
        self::getInstance();
        $_SESSION[$key] = $value;
    }

    public static function get($key)
    {
        self::getInstance();
        return $_SESSION[$key] ?? null;
    }

    public static function remove($key): void
    {
        self::getInstance();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::getInstance();
        session_destroy();
        self::$instance = null;
    }

    public static function isSessionStarted(): bool
    {
        return session_status() == PHP_SESSION_ACTIVE;
    }

    private function configureSession(): void
    {
        // Set session cookie parameters
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; // Check if the connection is secure
        //$httpOnly = true; // Set to true to prevent JavaScript access to the session cookie
        $sameSite = 'Lax'; // Set SameSite attribute to 'Strict' for maximum security

        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => 0, // Session cookie expires when the browser is closed
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite
        ]);
    }
}