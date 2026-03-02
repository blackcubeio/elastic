<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Product model for integration testing with custom column names.
 * - data instead of _extras
 * - sid instead of elasticSchemaId
 *
 * @property int $id
 * @property string $name
 * @property int|null $sid
 * @property string|null $data
 *
 * Elastic properties (from schema):
 * @property string $sku
 * @property float|null $price
 * @property bool|null $inStock
 */
class Product extends ActiveRecord
{
    use MagicComposeActiveRecordTrait, ElasticTrait;

    protected int $id;
    protected string $name = '';

    public function elasticColumn(): string
    {
        return 'data';
    }

    public function elasticSchemaColumn(): string
    {
        return 'sid';
    }

    public function tableName(): string
    {
        return '{{%products}}';
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
