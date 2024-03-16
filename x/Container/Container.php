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

    public function bind(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * @throws Exception
     */
    public function make(string $abstract, array $parameters = [])
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];

            if ($concrete instanceof Closure) {
                $instance = $this->build($concrete, $parameters);
            } else {
                $instance = $this->build($concrete, $parameters);
            }

            $this->instances[$abstract] = $instance;

            return $instance;
        }

        throw new Exception("Binding for '{$abstract}' not found.");
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
            $reflector = new \ReflectionClass($concrete);

            if (!$reflector->isInstantiable()) {
                throw new Exception("Class '{$concrete}' is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return new $concrete;
            }

            $dependencies = $this->resolveDependencies($constructor, $parameters);

            return $reflector->newInstanceArgs($dependencies);
        } catch (\ReflectionException $e) {
            throw new Exception("Error resolving '{$concrete}': " . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function resolveDependencies(\ReflectionMethod $method, array $parameters): array
    {
        $dependencies = [];

        foreach ($method->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            if (array_key_exists($paramName, $parameters)) {
                $dependencies[] = $parameters[$paramName];
            } elseif ($parameter->getClass()) {
                $dependencies[] = $this->make($parameter->getClass()->getName());
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
        });
    }
}