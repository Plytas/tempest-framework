<?php

namespace Tempest\Container;

use Exception;
use ReflectionClass;
use ReflectionParameter;
use Tempest\Interfaces\CanInitialize;
use Tempest\Interfaces\Container;
use Tempest\Interfaces\Initializer;
use Throwable;

final class GenericContainer implements Container
{
    use HasInstance;
    
    private array $definitions = [];

    private array $singletons = [];

    /** @var CanInitialize[] */
    private array $initializers = [];

    public function register(string $className, callable $definition): self
    {
        $this->definitions[$className] = $definition;

        return $this;
    }

    public function singleton(string $className, callable $definition): self
    {
        $this->definitions[$className] = function () use ($definition, $className) {
            $instance = $definition($this);

            $this->singletons[$className] = $instance;

            return $instance;
        };

        return $this;
    }

    public function config(object $config): self
    {
        $this->singleton($config::class, fn () => $config);

        return $this;
    }

    public function get(string $className): object
    {
        if ($instance = $this->singletons[$className] ?? null) {
            return $instance;
        }

        $log = new ContainerLog();

        try {
            return $this->resolve($className, $log);
        } catch (Throwable $throwable) {
            throw new Exception(
                message: "Could not resolve {$log}",
                previous: $throwable,
            );
        }
    }

    public function call(object $object, string $methodName, ...$params): mixed
    {
        $reflectionMethod = (new ReflectionClass($object))->getMethod($methodName);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $className = $parameter->getName();

            if (array_key_exists($className, $params)) {
                continue;
            }

            $params[$className] = $this->get($parameter->getType()->getName());
        }

        return $object->{$methodName}(...$params);
    }

    public function addInitializer(CanInitialize $initializer): Container
    {
        $this->initializers = [$initializer, ...$this->initializers];

        return $this;
    }

    private function resolve(string $className, ContainerLog $log): object
    {
        $log->add($className);

        foreach ($this->initializers as $initializer) {
            if ($initializer->canInitialize($className)) {
                $log->add($initializer::class);

                return $initializer->initialize($className, $this);
            }
        }

        $reflectionClass = new ReflectionClass($className);

        if ($initializedBy = ($reflectionClass->getAttributes(InitializedBy::class)[0] ?? null)) {
            $initializerClassName = $initializedBy->newInstance()->className;

            $initializer = $this->get($initializerClassName);

            if (! $initializer instanceof Initializer) {
                throw new Exception("Initializers must be implement Initializer, {$initializerClassName} does not.");
            }

            return $initializer->initialize($className, $this);
        }

        $definition = $this->definitions[$className] ?? null;

        if ($definition) {
            return $definition($this);
        }

        return $this->autowire($reflectionClass, $log);
    }

    private function autowire(ReflectionClass $reflectionClass, ContainerLog $log): object
    {
        $parameters = array_map(
            fn (ReflectionParameter $parameter) => $this->resolve($parameter->getType()->getName(), $log),
            $reflectionClass->getConstructor()?->getParameters() ?? [],
        );

        return $reflectionClass->newInstance(...$parameters);
    }
}
