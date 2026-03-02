<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Tag model for integration testing.
 *
 * @property int $id
 * @property string $name
 * @property int|null $elasticSchemaId
 * @property string|null $_extras
 *
 * Elastic properties (from schema "tagging"):
 * @property string $color
 * @property string|null $description
 */
class Tag extends ActiveRecord
{
    use MagicComposeActiveRecordTrait, ElasticTrait;

    protected int $id;
    protected string $name = '';

    public function tableName(): string
    {
        return '{{%tags}}';
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
