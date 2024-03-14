<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\Container;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];
    private array $resolvedAliases = [];

    public function bind(string $abstract, $concrete, bool $singleton = false): void
    {
        if ($singleton) {
            $this->singleton($abstract, $concrete);
        } else {
            $this->bindings[$abstract] = $concrete;
        }
    }

    /**
     * @throws Exception
     */
    public function make(string $abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->resolvedAliases[$abstract])) {
            return $this->resolvedAliases[$abstract];
        }

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new Exception("Binding for '{$abstract}' not found.");
        }

        $concrete = $this->bindings[$abstract];

        $instance = $this->build($concrete, $parameters);

        if (!isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    private function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * @throws Exception
     */
    private function build($concrete, array $parameters)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        try {
            $reflector = new ReflectionClass($concrete);

            if (!$reflector->isInstantiable()) {
                throw new Exception("Class '{$concrete}' is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return new $concrete;
            }

            $dependencies = $this->resolveDependencies($constructor, $parameters);

            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new Exception("Error resolving '{$concrete}': " . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function resolveDependencies(ReflectionMethod $method, array $parameters): array
    {
        $dependencies = [];

        foreach ($method->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            if ($paramType !== null && !$paramType->isBuiltin()) {
                $dependencies[] = $this->make($paramType->getName());
            } elseif (array_key_exists($paramName, $parameters)) {
                $dependencies[] = $parameters[$paramName];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new Exception("Unable to resolve dependency: {$paramName}");
            }
        }

        return $dependencies;
    }

    public function singleton(string $abstract, $concrete): void
    {
        $this->bind($abstract, function () use ($concrete) {
            static $instance;

            if ($instance === null) {
                $instance = $this->build($concrete, []);
            }

            return $instance;
        }, true);
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function extend(string $abstract, Closure $closure)
    {
        $original = $this->instances[$abstract] ?? $this->bindings[$abstract];

        $this->bind($abstract, function () use ($original, $closure) {
            return $closure($original, $this);
        });
    }

    public function lazy(string $abstract, Closure $resolver): Closure
    {
        return function () use ($abstract, $resolver) {
            return $this->make($abstract);
        };
    }
}