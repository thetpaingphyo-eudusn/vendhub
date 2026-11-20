<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

final class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        $class = new ReflectionClass($id);
        $constructor = $class->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $this->instances[$id] = $class->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new RuntimeException('Cannot resolve ' . $id . '::$' . $parameter->getName());
            }
            $arguments[] = $this->get($type->getName());
        }

        return $this->instances[$id] = $class->newInstanceArgs($arguments);
    }
}
