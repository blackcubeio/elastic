<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\MagicCompose\Attributes\MagicCall;
use Blackcube\MagicCompose\Attributes\MagicGetter;
use Blackcube\MagicCompose\Attributes\MagicIsset;
use Blackcube\MagicCompose\Attributes\MagicSetter;
use Blackcube\MagicCompose\Attributes\Priority;
use Blackcube\MagicCompose\Exceptions\MagicNotHandledException;

/**
 * Mock trait that implements magic methods for testing trait composition.
 * Simulates another trait (like HazeltreeTrait) that would need to coexist with ElasticTrait.
 * Uses MagicCompose attributes.
 */
trait MockMagicTrait
{
    private ?int $mockLeft = null;
    private ?int $mockRight = null;
    private ?int $mockLevel = null;

    private static array $mockProperties = ['mockLeft', 'mockRight', 'mockLevel'];

    private function isMockProperty(string $name): bool
    {
        return in_array($name, self::$mockProperties, true);
    }

    #[MagicGetter(Priority::NORMAL)]
    protected function mockMagicGet(string $name): mixed
    {
        if (!$this->isMockProperty($name)) {
            throw new MagicNotHandledException();
        }
        return $this->$name;
    }

    #[MagicSetter(Priority::NORMAL)]
    protected function mockMagicSet(string $name, mixed $value): void
    {
        if (!$this->isMockProperty($name)) {
            throw new MagicNotHandledException();
        }
        $this->$name = $value !== null ? (int) $value : null;
    }

    #[MagicIsset(Priority::NORMAL)]
    protected function mockMagicIsset(string $name): bool
    {
        if (!$this->isMockProperty($name)) {
            throw new MagicNotHandledException();
        }
        return $this->$name !== null;
    }

    #[MagicCall(Priority::NORMAL)]
    protected function mockMagicCall(string $name, array $arguments): mixed
    {
        // Handle getPropertyName()
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));
            if ($this->isMockProperty($property)) {
                return $this->$property;
            }
        }

        // Handle setPropertyName($value)
        if (str_starts_with($name, 'set') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));
            if ($this->isMockProperty($property)) {
                $value = $arguments[0] ?? null;
                $this->$property = $value !== null ? (int) $value : null;
                return null;
            }
        }

        // Handle custom method isLeaf()
        if ($name === 'isLeaf') {
            return ($this->mockRight - $this->mockLeft) === 1;
        }

        throw new MagicNotHandledException();
    }
}
