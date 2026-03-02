<?php

declare(strict_types=1);

/**
 * MockHazeltreeTrait.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\MagicCompose\Attributes\MagicCall;
use Blackcube\MagicCompose\Attributes\MagicGetter;
use Blackcube\MagicCompose\Attributes\MagicIsset;
use Blackcube\MagicCompose\Attributes\MagicSetter;
use Blackcube\MagicCompose\Attributes\Priority;
use Blackcube\MagicCompose\Exceptions\MagicNotHandledException;

/**
 * Mock trait simulating HazeltreeTrait for testing trait composition.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
trait MockHazeltreeTrait
{
    private ?float $mockLeft = null;
    private ?float $mockRight = null;
    private ?int $mockLevel = null;

    private function isMockHazeltreeProperty(string $name): bool
    {
        return in_array($name, ['mockLeft', 'mockRight', 'mockLevel'], true);
    }

    #[MagicGetter(Priority::NORMAL)]
    protected function mockHazeltreeGet(string $name): mixed
    {
        if (!$this->isMockHazeltreeProperty($name)) {
            throw new MagicNotHandledException();
        }
        return match ($name) {
            'mockLeft' => $this->mockLeft,
            'mockRight' => $this->mockRight,
            'mockLevel' => $this->mockLevel,
            default => throw new MagicNotHandledException(),
        };
    }

    #[MagicSetter(Priority::NORMAL)]
    protected function mockHazeltreeSet(string $name, mixed $value): void
    {
        if (!$this->isMockHazeltreeProperty($name)) {
            throw new MagicNotHandledException();
        }
        match ($name) {
            'mockLeft' => $this->mockLeft = $value,
            'mockRight' => $this->mockRight = $value,
            'mockLevel' => $this->mockLevel = $value,
        };
    }

    #[MagicIsset(Priority::NORMAL)]
    protected function mockHazeltreeIsset(string $name): bool
    {
        if (!$this->isMockHazeltreeProperty($name)) {
            throw new MagicNotHandledException();
        }
        return match ($name) {
            'mockLeft' => $this->mockLeft !== null,
            'mockRight' => $this->mockRight !== null,
            'mockLevel' => $this->mockLevel !== null,
            default => false,
        };
    }

    #[MagicCall(Priority::NORMAL)]
    protected function mockHazeltreeCall(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));
            if ($this->isMockHazeltreeProperty($property)) {
                return $this->mockHazeltreeGet($property);
            }
        }

        if (str_starts_with($name, 'set') && strlen($name) > 3 && count($arguments) === 1) {
            $property = lcfirst(substr($name, 3));
            if ($this->isMockHazeltreeProperty($property)) {
                $this->mockHazeltreeSet($property, $arguments[0]);
                return null;
            }
        }

        // isLeaf() method
        if ($name === 'isLeaf') {
            return (int)($this->mockRight - $this->mockLeft) === 1;
        }

        throw new MagicNotHandledException();
    }
}
