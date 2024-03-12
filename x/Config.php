<?php

namespace X;

use Exception;
use X\Resolver\PathResolver;

class Config
{
    private static ?Config $instance = null;
    private array|false $config;

    private PathResolver $path;

    /**
     * @throws Exception
     */
    private function __construct()
    {
        $this->path = new PathResolver(get_root_dir());
        // Load configuration from both JSON and .env files
        $jsonConfig = $this->loadFromJson();
        $envConfig = $this->loadFromEnv();

        // Merge the configurations
        $this->config = array_merge($jsonConfig, $envConfig);
    }

    private function loadFromJson()
    {
        if (file_exists($this->path->resolve() . 'configs' . DIRECTORY_SEPARATOR . 'config.json')) {
            $jsonConfig = file_get_contents($this->path->resolve() . 'configs' . DIRECTORY_SEPARATOR . 'config.json');
            return json_decode($jsonConfig, true);
        }

        return [];
    }

    private function loadFromEnv(): false|array
    {
        if (file_exists($this->path->resolve() . '.env')) {
            return parse_ini_file($this->path->resolve() . '.env', true);
        }

        return [];
    }

    private function __clone()
    {
        // Prevent cloning of the instance
    }

    public static function getInstance(): Config
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get($key)
    {
        // Return the value for the specified key from the configuration
        return $this->config[$key] ?? null;
    }
}