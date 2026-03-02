<?php

declare(strict_types=1);

/**
 * JsonSchemaRuleMapper.php
 *
 * PHP version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Elastic\Validator;

use Swaggest\JsonSchema\SchemaContract;
use Yiisoft\Validator\Rule\BooleanValue;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Ip;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Number;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\StringValue;
use Yiisoft\Validator\Rule\Url;
use Yiisoft\Validator\RuleInterface;

use function in_array;
use function str_replace;

/**
 * Maps JSON Schema property definitions to Yii3 validation rules.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class JsonSchemaRuleMapper
{
    /**
     * Build Yii3 validation rules for a JSON Schema property.
     *
     * @param string $propertyName Property name
     * @param SchemaContract $property Property schema definition
     * @param list<string> $requiredProperties List of required property names
     * @return list<RuleInterface>
     */
    public function map(string $propertyName, SchemaContract $property, array $requiredProperties = []): array
    {
        $rules = [];
        $isRequired = in_array($propertyName, $requiredProperties, true);

        if ($isRequired) {
            $rules[] = new Required();
        }

        $type = $property->type ?? null;
        $skipOnEmpty = !$isRequired;

        if ($type === 'string') {
            $rules = [...$rules, ...$this->mapStringRules($property, $skipOnEmpty)];
        } elseif ($type === 'integer') {
            $rules[] = new Integer(
                min: $property->minimum ?? null,
                max: $property->maximum ?? null,
                skipOnEmpty: $skipOnEmpty
            );
        } elseif ($type === 'number') {
            $rules[] = new Number(
                min: $property->minimum ?? null,
                max: $property->maximum ?? null,
                skipOnEmpty: $skipOnEmpty
            );
        } elseif ($type === 'boolean') {
            $rules[] = new BooleanValue(skipOnEmpty: $skipOnEmpty);
        }

        if ($property->enum !== null) {
            $rules[] = new In($property->enum, skipOnEmpty: $skipOnEmpty);
        }

        return $rules;
    }

    /**
     * @return list<RuleInterface>
     */
    private function mapStringRules(SchemaContract $property, bool $skipOnEmpty): array
    {
        $rules = [];
        $format = $property->format ?? null;

        if ($format === 'email') {
            $rules[] = new Email(skipOnEmpty: $skipOnEmpty);
        } elseif ($format === 'idn-email') {
            $rules[] = new Email(enableIdn: true, skipOnEmpty: $skipOnEmpty);
        } elseif ($format === 'uri' || $format === 'url') {
            $rules[] = new Url(skipOnEmpty: $skipOnEmpty);
        } elseif ($format === 'ipv4') {
            $rules[] = new Ip(allowIpv4: true, allowIpv6: false, skipOnEmpty: $skipOnEmpty);
        } elseif ($format === 'ipv6') {
            $rules[] = new Ip(allowIpv4: false, allowIpv6: true, skipOnEmpty: $skipOnEmpty);
        } else {
            $rules[] = new StringValue(skipOnEmpty: $skipOnEmpty);
        }

        $min = $property->minLength ?? null;
        $max = $property->maxLength ?? null;
        if ($min !== null || $max !== null) {
            $rules[] = new Length(min: $min, max: $max, skipOnEmpty: $skipOnEmpty);
        }

        if ($property->pattern !== null) {
            $pattern = '/' . str_replace('/', '\/', $property->pattern) . '/';
            $rules[] = new Regex($pattern, skipOnEmpty: $skipOnEmpty);
        }

        return $rules;
    }
}
