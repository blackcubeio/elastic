<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\MagicComposeTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Base class with magic methods to test parent delegation.
 */
class MagicActiveRecord extends ActiveRecord
{
    private array $magicProperties = [];

    public function __get(string $name): mixed
    {
        return $this->magicProperties[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->magicProperties[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->magicProperties[$name]);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'getMagic')) {
            return 'magic_' . substr($name, 8);
        }
        throw new \Error("Call to undefined method " . static::class . "::$name()");
    }

    public function tableName(): string
    {
        return '{{%testModels}}';
    }
}

/**
 * Test model using ElasticTrait with a parent that has __get/__set.
 * Uses MagicComposeTrait for magic method dispatch.
 */
class TestModelWithMagicParent extends MagicActiveRecord
{
    use MagicComposeTrait, ElasticTrait;

    protected int $id;
    protected string $title = '';

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
