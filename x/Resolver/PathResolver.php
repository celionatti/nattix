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

    public function assetsPath(): string
    {
        return $this->resolve('public', 'assets');
    }
}