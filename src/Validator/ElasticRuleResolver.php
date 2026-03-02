<?php

declare(strict_types=1);

/**
 * ElasticRuleResolver.php
 *
 * PHP version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Elastic\Validator;

use Swaggest\JsonSchema\Schema;
use Yiisoft\Validator\RuleInterface;

/**
 * Resolves Yii3 validation rules from a model using ElasticTrait.
 *
 * This resolver extracts the JSON Schema from the model and uses
 * JsonSchemaRuleMapper to generate validation rules for each property.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class ElasticRuleResolver
{
    /**
     * Resolve validation rules for a model.
     *
     * @param object $model Model with getSchema() and getExtras() methods
     * @return array<string, list<RuleInterface>>
     */
    public function resolve(object $model): array
    {
        if (!method_exists($model, 'getSchema')) {
            return [];
        }

        $schema = $model->getSchema();
        if (!$schema instanceof Schema) {
            return [];
        }

        $properties = $schema->getProperties();
        if ($properties === null) {
            return [];
        }

        $required = $schema->required ?? [];
        $rules = [];
        $mapper = new JsonSchemaRuleMapper();

        foreach ($properties as $name => $property) {
            $propertyRules = $mapper->map($name, $property, $required);
            if ($propertyRules !== []) {
                $rules[$name] = $propertyRules;
            }
        }

        return $rules;
    }
}
