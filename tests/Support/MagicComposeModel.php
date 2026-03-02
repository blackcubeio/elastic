<?php

declare(strict_types=1);

/**
 * MagicComposeModel.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Test model combining MagicComposeActiveRecordTrait + MockHazeltreeTrait + ElasticTrait.
 * Uses MagicCompose - no manual conflict resolution needed.
 *
 * @property int|null $mockLeft
 * @property int|null $mockRight
 * @property int|null $mockLevel
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class MagicComposeModel extends ActiveRecord
{
    use MagicComposeActiveRecordTrait, MockHazeltreeTrait, ElasticTrait;

    protected int $id;
    protected string $name = '';
    protected ?string $_extras = null;

    public function tableName(): string
    {
        return '{{%magicComposeModels}}';
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
