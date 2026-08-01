<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Support;

use Illuminate\Contracts\Container\BindingResolutionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Filament's closure evaluator injects dependencies by parameter name and
 * type without checking the declared type, so a first-class callable like
 * UserResource::getIndexUrl(...) would receive the current Panel object in
 * its ?string $panel parameter, or try to instantiate Model for a ?Model
 * $tenant. This guard falls back to the parameter's default when an
 * injection cannot satisfy the declared type, so such callables are safe to
 * pass wherever a command accepts a closure.
 */
trait MatchesClosureDependencyTypes
{
    /**
     * @param  array<string, mixed>  $namedInjections
     * @param  array<string, mixed>  $typedInjections
     */
    protected function resolveClosureDependencyForEvaluation(ReflectionParameter $parameter, array $namedInjections, array $typedInjections): mixed
    {
        try {
            $dependency = parent::resolveClosureDependencyForEvaluation($parameter, $namedInjections, $typedInjections);
        } catch (BindingResolutionException $exception) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->allowsNull()) {
                return null;
            }

            throw $exception;
        }

        if ($this->dependencySatisfiesParameterType($dependency, $parameter)) {
            return $dependency;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        return $dependency;
    }

    protected function dependencySatisfiesParameterType(mixed $dependency, ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if ($type === null) {
            return true;
        }

        if ($dependency === null) {
            return $type->allowsNull();
        }

        return $this->dependencySatisfiesType($dependency, $type);
    }

    protected function dependencySatisfiesType(mixed $dependency, ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $candidate) {
                if ($this->dependencySatisfiesType($dependency, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $candidate) {
                if (! $this->dependencySatisfiesType($dependency, $candidate)) {
                    return false;
                }
            }

            return true;
        }

        if (! $type instanceof ReflectionNamedType) {
            return true;
        }

        if ($type->isBuiltin()) {
            return match ($type->getName()) {
                'string' => is_string($dependency),
                'int' => is_int($dependency),
                'float' => is_float($dependency) || is_int($dependency),
                'bool' => is_bool($dependency),
                'array' => is_array($dependency),
                'iterable' => is_iterable($dependency),
                'callable' => is_callable($dependency),
                'object' => is_object($dependency),
                'null' => $dependency === null,
                'true' => $dependency === true,
                'false' => $dependency === false,
                default => true,
            };
        }

        $name = $type->getName();

        // self/static/parent are rare here and resolving them needs the
        // declaring class; stay permissive rather than second-guess.
        if (in_array($name, ['self', 'static', 'parent'], true)) {
            return true;
        }

        return $dependency instanceof $name;
    }
}
