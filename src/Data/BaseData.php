<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use HassanDomeDenea\HddLaravelHelpers\Traits\HasTranslatableAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\Traits\Macroable;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\WithoutValidation;
use Spatie\LaravelData\Data;
use Str;

class BaseData extends Data
{
    use HasTranslatableAttributes;
    use Macroable;

    protected ?array $originalPayload = null;

    /**
     * Overwrite original method, so that original payload is saved.
     */
    public static function validateAndCreate(Arrayable|array $payload): static
    {
        $object = static::factory()->alwaysValidate()->from($payload);
        $object->setOriginalPayload($payload);

        return $object;
    }

    /**
     * Set original payload when data class is usually created from it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setOriginalPayload(array $payload): void
    {
        $this->originalPayload = $payload;
    }

    /**
     * Generate a fluent array representation of the data.
     */
    public function toFluent(): Fluent
    {
        return fluent($this->toArray());
    }

    public function toValidated(): array
    {
        $reflection = new ReflectionClass(static::class);

        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $excludePropertyAttribute = WithoutValidation::class;

        $validatedProperties = array_filter($properties, function (ReflectionProperty $property) use ($excludePropertyAttribute) {
            return empty($property->getAttributes($excludePropertyAttribute));
        });

        return array_reduce(array: $validatedProperties, callback: function (array $carry, ReflectionProperty $property) {
            $carry[Str::snake($property->getName())] = $this->{$property->getName()};

            return $carry;
        }, initial: []);
    }

    public function toFluentValidated()
    {
        return fluent($this->toValidated());
    }

    /**
     * Return the original payload the data class was created from.
     */
    public function safe(string|array|null $keys = null, mixed $default = null): Fluent|array|null
    {
        $object = is_null($this->originalPayload) ? null : fluent(Arr::only($this->originalPayload, static::getPayloadAttributeNames()));
        if (blank($object)) {
            return null;
        }
        if (blank($keys)) {
            return $object;
        }
        if (is_string($keys)) {
            return $object->get($keys, $default);
        }

        return $object->only($keys);
    }

    /**
     * Return the original payload the data class was created from, for validated properties only.
     */
    public function safeValidated(): Fluent|array|null
    {
        $object = is_null($this->originalPayload) ? null : fluent(Arr::only($this->originalPayload, static::getPayloadAttributeNames()));
        if (blank($object)) {
            return null;
        }
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $excludePropertyAttribute = WithoutValidation::class;
        $validatedProperties = array_filter($properties, function (ReflectionProperty $property) use ($excludePropertyAttribute) {
            return empty($property->getAttributes($excludePropertyAttribute));
        });

        $result = array_reduce(array: $validatedProperties, callback: function (array $carry, ReflectionProperty $property) use ($object) {
            $snakeName = Str::snake($property->getName());
            if ($object->has($snakeName)) {
                $carry[Str::snake($property->getName())] = $object->get($snakeName);
            }

            return $carry;
        }, initial: []);

        return fluent($result);
    }
}
