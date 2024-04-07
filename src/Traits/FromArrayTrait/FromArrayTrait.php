<?php

declare(strict_types=1);

namespace Puzlplace\PuzlLibTest\Traits\FromArrayTrait;

trait FromArrayTrait
{
    /**
     * Carregar dados através de objetos array|model|BaseDto.
     * Estrutura dos dados precisa ser compatível com a classe.
     *
     * @param  mixed  $values  array|model|stdclass|BaseDto
     */
    public static function from(mixed $values): self
    {
        $content = [];
        if (is_array($values)) {
            $content = $values;
        }
        if (is_object($values)) {
            if (method_exists($values, 'toArray')) {
                $content = $values->toArray();
            } else {
                $content = (array) $values;
            }
        }

        return self::create($content);
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this), true);
    }

    /**
     * Instanciar a própria classe e tratar atributos de classe
     */
    public static function create(?array $values): self
    {
        /** @phpstan-ignore-next-line */
        $instance = new self();
        $class = new \ReflectionClass(static::class);
        foreach ($class->getProperties() as $prop) {
            $field = $prop->getName();
            $instance->$field = self::getFieldValue($prop, $values[$field] ?? null);
        }

        return $instance;
    }

    private static function getFieldValue(\ReflectionProperty $prop, mixed $value): mixed
    {
        $annotations = $prop->getAttributes();

        return match (count($annotations) > 0) {
            true => self::handleAnnotations($prop, $value, $annotations[0]),
            false => self::handleBasicTypes($prop, $value),
        };
    }

    private static function handleAnnotations(\ReflectionProperty $prop, mixed $value, mixed $annotation): mixed
    {
        return match ($annotation->getName()) {
            EnumAttribute::class => self::handleEnumAttValue($prop, $annotation, $value),
            DtoAttribute::class => self::handleDtoAttValue($prop, $annotation, $value),
        };
    }

    private static function handleBasicTypes(\ReflectionProperty $prop, mixed $value): mixed
    {
        $propTypeName = self::getPropTypeName($prop, $value);

        return match ($propTypeName) {
            'int', 'integer' => self::handleIntValue($prop, $value),
            'string' => self::handleStringValue($prop, $value),
            'float' => self::handleFloatValue($prop, $value),
            'bool' => self::handleBoolValue($prop, $value),
            'DateTime' => self::handleDateTimeValue($prop, $value),
            'array' => self::handleArrayValue($prop, $value),
            'mixed' => self::handleMixedValue($prop, $value),
            default => self::handleOtherTypes($prop, $value),
        };
    }

    private static function getPropTypeName(\ReflectionProperty $prop, mixed $value): string
    {
        return match ($prop->getType() instanceof \ReflectionUnionType) {
            true => match (is_null($value)) {
                true => $prop->getType()->getTypes()[0]->getName(),
                false => gettype($value),
            },
            false => $prop->getType()->getName(),
        };
    }

    private static function handleEnumAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value): \UnitEnum|array|null
    {
        $attInstance = $att->newInstance();
        $namespace = $attInstance->namespace
            ? $attInstance->namespace
            : $prop->getType()->getName();
        $class = new \ReflectionClass($namespace);
        $execute = function ($class, $prop, $item) {
            if (is_null($item)) {
                if ($prop->getDefaultValue()) {
                    return $prop->getDefaultValue();
                }
                if ($prop->getType()->allowsNull()) {
                    return null;
                }
            }
            $consts = $class->getConstants();
            $const = array_filter($consts, fn ($const) => $item == $const->value);
            if (! $const && $item instanceof \UnitEnum) {
                $const = array_filter($consts, fn ($const) => $item->value == $const->value);
            }

            return $const ? reset($const) : reset($consts);
        };
        if ($prop->getType()->getName() === 'array') {
            $result = [];
            foreach ($value ?? [] as $current) {
                $result[] = $execute($class, $prop, $current);
            }

            return $result;
        }

        return $execute($class, $prop, $value);
    }

    private static function handleDtoAttValue(\ReflectionProperty $prop, \ReflectionAttribute $att, mixed $value): Object|array|null
    {
        $attInstance = $att->newInstance();
        $namespace = $attInstance->namespace
            ? $attInstance->namespace
            : $prop->getType()->getName();
        $execute = function ($namespace, $prop, $item) {
            if (is_null($item)) {
                if ($prop->getDefaultValue()) {
                    return $prop->getDefaultValue();
                }
                if ($prop->getType()->allowsNull()) {
                    return null;
                }
            }

            return $namespace::from($item ?? []);
        };
        if ($prop->getType()->getName() === 'array') {
            $result = [];
            foreach ($value ?? [] as $current) {
                $result[] = $execute($namespace, $prop, $current);
            }

            return $result;
        }

        return $execute($namespace, $prop, $value);
    }

    private static function handleIntValue(\ReflectionProperty $prop, mixed $value): ?int
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return (int) $value;
        }
        if ($defaultValue) {
            return (int) $defaultValue;
        }

        return $allowsNull ? null : 0;
    }

    private static function handleStringValue(\ReflectionProperty $prop, mixed $value): ?string
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (! is_null($value)) {
            return (string) $value;
        }
        if ($defaultValue) {
            return (string) $defaultValue;
        }

        return $allowsNull ? null : '';
    }

    private static function handleFloatValue(\ReflectionProperty $prop, mixed $value): ?float
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return (float) $value;
        }
        if ($defaultValue) {
            return (float) $defaultValue;
        }

        return $allowsNull ? null : 0;
    }

    private static function handleBoolValue(\ReflectionProperty $prop, mixed $value): ?bool
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (! is_null($value)) {
            return ($value === true) || ($value === 'true') || ($value === 1) || ($value === '1');
        }
        if ($defaultValue) {
            return (bool) $defaultValue;
        }

        return $allowsNull ? null : false;
    }

    private static function handleDateTimeValue(\ReflectionProperty $prop, mixed $value): ?\DateTime
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return new \DateTime($value);
        }
        if ($defaultValue) {
            return $defaultValue;
        }

        return $allowsNull ? null : new \DateTime('0000-00-00 00:00:00');
    }

    private static function handleArrayValue(\ReflectionProperty $prop, mixed $value): ?array
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if ($value) {
            return (array) $value;
        }
        if ($defaultValue) {
            return (array) $defaultValue;
        }

        return $allowsNull ? null : [];
    }

    private static function handleMixedValue(\ReflectionProperty $prop, mixed $value): mixed
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (is_null($value)) {
            if ($defaultValue) {
                return $defaultValue;
            }
            if ($allowsNull) {
                return null;
            }
        }

        return $value;
    }

    private static function handleOtherTypes(\ReflectionProperty $prop, mixed $value): mixed
    {
        $defaultValue = $prop->getDefaultValue();
        $allowsNull = $prop->getType()->allowsNull();
        if (is_null($value)) {
            if ($defaultValue) {
                return $defaultValue;
            }
            if ($allowsNull) {
                return null;
            }
        }
        // Instanciar Enumerator/DTO
        $propTypeName = $prop->getType()->getName();
        if (class_exists($propTypeName)) {
            $class = new \ReflectionClass($propTypeName);
            if ($class->isEnum()) {
                $consts = $class->getConstants();
                $const = array_filter($consts, fn ($const) => $value == $const->value);
                if (! $const && $value instanceof \UnitEnum) {
                    $const = array_filter($consts, fn ($const) => $value->value == $const->value);
                }

                return $const
                    ? reset($const)
                    : reset($consts);
            }

            return $prop->getType()->getName()::from($value ?? []);
        }

        return null;
    }
}

#[\Attribute]
class EnumAttribute extends SimpleObject
{
}

#[\Attribute]
class DtoAttribute extends SimpleObject
{
}

class SimpleObject
{
    public function __construct(
        public string $namespace = '',
    ) {
    }
}
