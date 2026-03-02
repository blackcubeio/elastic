<?php

declare(strict_types=1);

/**
 * DI container configuration for yii3-elastic package.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\Elastic\Validator\ElasticRuleResolver;
use Blackcube\Elastic\Validator\JsonSchemaRuleMapper;

return [
    ElasticRuleResolver::class => ElasticRuleResolver::class,
];
