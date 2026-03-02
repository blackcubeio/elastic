<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Test model that combines ElasticTrait with MockMagicTrait.
 * Uses MagicComposeActiveRecordTrait - no manual conflict resolution needed.
 *
 * @property int|null $mockLeft
 * @property int|null $mockRight
 * @property int|null $mockLevel
 */
class CombinedTraitsModel extends ActiveRecord
{
    use MagicComposeActiveRecordTrait, MockMagicTrait, ElasticTrait;

    protected int $id;
    protected string $name = '';
    protected ?string $_extras = null;

    public function tableName(): string
    {
        return '{{%combinedModels}}';
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
