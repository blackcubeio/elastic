<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Test model using ElasticTrait with model-defined labels.
 * Used to test priority: model labels > elastic labels > generated.
 */
class TestModelWithLabels extends ActiveRecord
{
    use MagicComposeActiveRecordTrait, ElasticTrait;

    protected int $id;
    protected string $title = '';

    public function tableName(): string
    {
        return '{{%testModels}}';
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

    /**
     * Model-defined labels - these should take priority over elastic labels.
     */
    public function getPropertyLabels(): array
    {
        return [
            'subject' => 'Model-defined label',
        ];
    }

    /**
     * Model-defined hints - these should take priority over elastic hints.
     */
    public function getPropertyHints(): array
    {
        return [
            'subject' => 'Model-defined hint',
        ];
    }

    /**
     * Model-defined placeholders - these should take priority over elastic placeholders.
     */
    public function getPropertyPlaceholders(): array
    {
        return [
            'subject' => 'Model-defined placeholder',
        ];
    }
}
