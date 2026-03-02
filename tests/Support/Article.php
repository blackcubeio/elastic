<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Article model for integration testing with custom elastic column name.
 *
 * @property int $id
 * @property string $title
 * @property int|null $elasticSchemaId
 * @property string|null $_data
 *
 * Elastic properties (from schema):
 * @property string $author
 * @property int|null $rating
 */
class Article extends ActiveRecord
{
    use MagicComposeActiveRecordTrait, ElasticTrait;

    protected int $id;
    protected string $title = '';

    public function elasticColumn(): string
    {
        return'_data';
    }

    public function tableName(): string
    {
        return '{{%articles}}';
    }

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
