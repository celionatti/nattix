<?php

namespace X\Resolver;

class PathResolver
{
    public function __construct(public string $basePath = "")
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public function resolve(string ...$paths): string
    {
        $resolvedPaths = array_map(function ($path) {
            return ltrim($path, '/');
        }, $paths);

        return $this->basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $resolvedPaths);
    }

    public function routesPath(): string
    {
        return $this->resolve('routes');
    }

    public function pluginRoutes(): ?array
    {
        $pluginDir = $this->resolve('plugins');

        $plugins = glob($pluginDir . '/*', GLOB_ONLYDIR);
        $routes = [];

        foreach ($plugins as $plugin) {
            $installJsonFile = $plugin . '/install.json';
            $pluginRoutesFile = $plugin . '/route.php';

            // Check if install.json exists and is readable
            if (file_exists($installJsonFile) && is_readable($installJsonFile)) {
                $installData = json_decode(file_get_contents($installJsonFile), true);

                // Check if the package is marked as active in install.json
                if (isset($installData['active']) && $installData['active'] === true) {
                    // Check if routes.php exists and is readable
                    if (file_exists($pluginRoutesFile) && is_readable($pluginRoutesFile)) {
                        // Add the routes file to the array
                        $routes[] = $pluginRoutesFile;
                    }
                }
            }
        }
        // No active plugin with routes found, return null or the array of routes
        return empty($routes) ? null : $routes;
    }

    public function assetsPath(): string
    {
        return $this->resolve('public', 'assets');
    }
}