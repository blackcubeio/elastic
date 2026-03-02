<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Validator;

use Blackcube\Elastic\Tests\Support\ValidatorTester;
use Blackcube\Elastic\Validator\JsonSchemaRuleMapper;
use Swaggest\JsonSchema\Schema;
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
use Yiisoft\Validator\Validator;

class JsonSchemaRuleMapperCest
{
    private JsonSchemaRuleMapper $mapper;

    public function _before(ValidatorTester $I): void
    {
        $this->mapper = new JsonSchemaRuleMapper();
    }

    public function testRequiredPropertyGeneratesRequiredRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that required property generates Required rule');

        $schema = Schema::import(json_decode('{
            "type": "object",
            "properties": {
                "name": {"type": "string"}
            },
            "required": ["name"]
        }'));

        $properties = $schema->getProperties();
        $required = $schema->required ?? [];

        $rules = $this->mapper->map('name', $properties['name'], $required);

        $I->assertNotEmpty($rules);
        $I->assertInstanceOf(Required::class, $rules[0]);
    }

    public function testNonRequiredPropertyDoesNotGenerateRequiredRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that non-required property does not generate Required rule');

        $schema = Schema::import(json_decode('{
            "type": "object",
            "properties": {
                "name": {"type": "string"}
            }
        }'));

        $properties = $schema->getProperties();
        $required = $schema->required ?? [];

        $rules = $this->mapper->map('name', $properties['name'], $required);

        $I->assertNotEmpty($rules);
        foreach ($rules as $rule) {
            $I->assertNotInstanceOf(Required::class, $rule);
        }
    }

    public function testValidatorValidatesRequiredFieldWithValue(ValidatorTester $I): void
    {
        $I->wantTo('verify that Yii3 Validator passes when required field has value');

        $schema = Schema::import(json_decode('{
            "type": "object",
            "properties": {
                "name": {"type": "string"}
            },
            "required": ["name"]
        }'));

        $properties = $schema->getProperties();
        $required = $schema->required ?? [];

        $rules = [
            'name' => $this->mapper->map('name', $properties['name'], $required),
        ];

        $data = ['name' => 'John'];

        $validator = new Validator();
        $result = $validator->validate($data, $rules);

        $I->assertTrue($result->isValid());
    }

    public function testValidatorFailsWhenRequiredFieldIsEmpty(ValidatorTester $I): void
    {
        $I->wantTo('verify that Yii3 Validator fails when required field is empty');

        $schema = Schema::import(json_decode('{
            "type": "object",
            "properties": {
                "name": {"type": "string"}
            },
            "required": ["name"]
        }'));

        $properties = $schema->getProperties();
        $required = $schema->required ?? [];

        $rules = [
            'name' => $this->mapper->map('name', $properties['name'], $required),
        ];

        $data = ['name' => ''];

        $validator = new Validator();
        $result = $validator->validate($data, $rules);

        $I->assertFalse($result->isValid());
        $I->assertNotEmpty($result->getErrors());
    }

    public function testValidatorFailsWhenRequiredFieldIsMissing(ValidatorTester $I): void
    {
        $I->wantTo('verify that Yii3 Validator fails when required field is missing');

        $schema = Schema::import(json_decode('{
            "type": "object",
            "properties": {
                "name": {"type": "string"}
            },
            "required": ["name"]
        }'));

        $properties = $schema->getProperties();
        $required = $schema->required ?? [];

        $rules = [
            'name' => $this->mapper->map('name', $properties['name'], $required),
        ];

        $data = [];

        $validator = new Validator();
        $result = $validator->validate($data, $rules);

        $I->assertFalse($result->isValid());
        $I->assertNotEmpty($result->getErrors());
    }

    // ==================== STRING TYPE ====================

    public function testStringTypeGeneratesStringValueRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that string type generates StringValue rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"name": {"type": "string"}}}'));
        $rules = $this->mapper->map('name', $schema->getProperties()['name'], []);

        $I->assertNotEmpty($rules);
        $hasStringValue = false;
        foreach ($rules as $rule) {
            if ($rule instanceof StringValue) {
                $hasStringValue = true;
                break;
            }
        }
        $I->assertTrue($hasStringValue);
    }

    public function testStringValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify string validation passes with valid string');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"name": {"type": "string"}}}'));
        $rules = ['name' => $this->mapper->map('name', $schema->getProperties()['name'], [])];

        $validator = new Validator();
        $result = $validator->validate(['name' => 'John'], $rules);

        $I->assertTrue($result->isValid());
    }

    // ==================== INTEGER TYPE ====================

    public function testIntegerTypeGeneratesIntegerRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that integer type generates Integer rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"age": {"type": "integer"}}}'));
        $rules = $this->mapper->map('age', $schema->getProperties()['age'], []);

        $I->assertNotEmpty($rules);
        $I->assertInstanceOf(Integer::class, $rules[0]);
    }

    public function testIntegerWithMinMaxGeneratesConstrainedRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that integer with min/max generates constrained Integer rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"age": {"type": "integer", "minimum": 0, "maximum": 150}}}'));
        $rules = $this->mapper->map('age', $schema->getProperties()['age'], []);

        $I->assertInstanceOf(Integer::class, $rules[0]);
        $I->assertEquals(0, $rules[0]->getMin());
        $I->assertEquals(150, $rules[0]->getMax());
    }

    public function testIntegerValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify integer validation passes with valid integer');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"age": {"type": "integer", "minimum": 0, "maximum": 150}}}'));
        $rules = ['age' => $this->mapper->map('age', $schema->getProperties()['age'], [])];

        $validator = new Validator();
        $result = $validator->validate(['age' => 25], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testIntegerValidationFailsBelowMin(ValidatorTester $I): void
    {
        $I->wantTo('verify integer validation fails below minimum');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"age": {"type": "integer", "minimum": 0}}}'));
        $rules = ['age' => $this->mapper->map('age', $schema->getProperties()['age'], [])];

        $validator = new Validator();
        $result = $validator->validate(['age' => -5], $rules);

        $I->assertFalse($result->isValid());
    }

    public function testIntegerValidationFailsAboveMax(ValidatorTester $I): void
    {
        $I->wantTo('verify integer validation fails above maximum');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"age": {"type": "integer", "maximum": 150}}}'));
        $rules = ['age' => $this->mapper->map('age', $schema->getProperties()['age'], [])];

        $validator = new Validator();
        $result = $validator->validate(['age' => 200], $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== NUMBER TYPE ====================

    public function testNumberTypeGeneratesNumberRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that number type generates Number rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"price": {"type": "number"}}}'));
        $rules = $this->mapper->map('price', $schema->getProperties()['price'], []);

        $I->assertNotEmpty($rules);
        $I->assertInstanceOf(Number::class, $rules[0]);
    }

    public function testNumberWithMinMaxGeneratesConstrainedRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that number with min/max generates constrained Number rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"price": {"type": "number", "minimum": 0.01, "maximum": 9999.99}}}'));
        $rules = $this->mapper->map('price', $schema->getProperties()['price'], []);

        $I->assertInstanceOf(Number::class, $rules[0]);
        $I->assertEquals(0.01, $rules[0]->getMin());
        $I->assertEquals(9999.99, $rules[0]->getMax());
    }

    public function testNumberValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify number validation passes with valid number');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"price": {"type": "number", "minimum": 0}}}'));
        $rules = ['price' => $this->mapper->map('price', $schema->getProperties()['price'], [])];

        $validator = new Validator();
        $result = $validator->validate(['price' => 19.99], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testNumberValidationFailsBelowMin(ValidatorTester $I): void
    {
        $I->wantTo('verify number validation fails below minimum');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"price": {"type": "number", "minimum": 0}}}'));
        $rules = ['price' => $this->mapper->map('price', $schema->getProperties()['price'], [])];

        $validator = new Validator();
        $result = $validator->validate(['price' => -10.5], $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== BOOLEAN TYPE ====================

    public function testBooleanTypeGeneratesBooleanValueRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that boolean type generates BooleanValue rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"active": {"type": "boolean"}}}'));
        $rules = $this->mapper->map('active', $schema->getProperties()['active'], []);

        $I->assertNotEmpty($rules);
        $I->assertInstanceOf(BooleanValue::class, $rules[0]);
    }

    public function testBooleanValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify boolean validation passes with valid boolean');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"active": {"type": "boolean"}}}'));
        $rules = ['active' => $this->mapper->map('active', $schema->getProperties()['active'], [])];

        $validator = new Validator();
        $I->assertTrue($validator->validate(['active' => true], $rules)->isValid());
        $I->assertTrue($validator->validate(['active' => false], $rules)->isValid());
    }

    // ==================== ENUM ====================

    public function testEnumGeneratesInRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that enum generates In rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"status": {"type": "string", "enum": ["draft", "published", "archived"]}}}'));
        $rules = $this->mapper->map('status', $schema->getProperties()['status'], []);

        $hasInRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof In) {
                $hasInRule = true;
                break;
            }
        }
        $I->assertTrue($hasInRule);
    }

    public function testEnumValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify enum validation passes with valid value');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"status": {"type": "string", "enum": ["draft", "published", "archived"]}}}'));
        $rules = ['status' => $this->mapper->map('status', $schema->getProperties()['status'], [])];

        $validator = new Validator();
        $result = $validator->validate(['status' => 'published'], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testEnumValidationFails(ValidatorTester $I): void
    {
        $I->wantTo('verify enum validation fails with invalid value');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"status": {"type": "string", "enum": ["draft", "published", "archived"]}}}'));
        $rules = ['status' => $this->mapper->map('status', $schema->getProperties()['status'], [])];

        $validator = new Validator();
        $result = $validator->validate(['status' => 'invalid'], $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== EMAIL FORMAT ====================

    public function testEmailFormatGeneratesEmailRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that email format generates Email rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"email": {"type": "string", "format": "email"}}}'));
        $rules = $this->mapper->map('email', $schema->getProperties()['email'], []);

        $hasEmailRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Email) {
                $hasEmailRule = true;
                break;
            }
        }
        $I->assertTrue($hasEmailRule);
    }

    public function testEmailValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify email validation passes with valid email');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"email": {"type": "string", "format": "email"}}}'));
        $rules = ['email' => $this->mapper->map('email', $schema->getProperties()['email'], [])];

        $validator = new Validator();
        $result = $validator->validate(['email' => 'test@example.com'], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testEmailValidationFails(ValidatorTester $I): void
    {
        $I->wantTo('verify email validation fails with invalid email');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"email": {"type": "string", "format": "email"}}}'));
        $rules = ['email' => $this->mapper->map('email', $schema->getProperties()['email'], [])];

        $validator = new Validator();
        $result = $validator->validate(['email' => 'not-an-email'], $rules);

        $I->assertFalse($result->isValid());
    }

    public function testIdnEmailFormatGeneratesEmailRuleWithIdn(ValidatorTester $I): void
    {
        $I->wantTo('verify that idn-email format generates Email rule with IDN enabled');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"email": {"type": "string", "format": "idn-email"}}}'));
        $rules = $this->mapper->map('email', $schema->getProperties()['email'], []);

        $hasEmailRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Email) {
                $hasEmailRule = true;
                $I->assertTrue($rule->isIdnEnabled());
                break;
            }
        }
        $I->assertTrue($hasEmailRule);
    }

    // ==================== URL FORMAT ====================

    public function testUrlFormatGeneratesUrlRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that url format generates Url rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"website": {"type": "string", "format": "uri"}}}'));
        $rules = $this->mapper->map('website', $schema->getProperties()['website'], []);

        $hasUrlRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Url) {
                $hasUrlRule = true;
                break;
            }
        }
        $I->assertTrue($hasUrlRule);
    }

    public function testUrlValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify url validation passes with valid url');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"website": {"type": "string", "format": "uri"}}}'));
        $rules = ['website' => $this->mapper->map('website', $schema->getProperties()['website'], [])];

        $validator = new Validator();
        $result = $validator->validate(['website' => 'https://example.com'], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testUrlValidationFails(ValidatorTester $I): void
    {
        $I->wantTo('verify url validation fails with invalid url');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"website": {"type": "string", "format": "uri"}}}'));
        $rules = ['website' => $this->mapper->map('website', $schema->getProperties()['website'], [])];

        $validator = new Validator();
        $result = $validator->validate(['website' => 'not-a-url'], $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== IPV4 FORMAT ====================

    public function testIpv4FormatGeneratesIpRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that ipv4 format generates Ip rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"ip": {"type": "string", "format": "ipv4"}}}'));
        $rules = $this->mapper->map('ip', $schema->getProperties()['ip'], []);

        $hasIpRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Ip) {
                $hasIpRule = true;
                $I->assertTrue($rule->isIpv4Allowed());
                $I->assertFalse($rule->isIpv6Allowed());
                break;
            }
        }
        $I->assertTrue($hasIpRule);
    }

    public function testIpv4ValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify ipv4 validation passes with valid ipv4');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"ip": {"type": "string", "format": "ipv4"}}}'));
        $rules = ['ip' => $this->mapper->map('ip', $schema->getProperties()['ip'], [])];

        $validator = new Validator();
        $result = $validator->validate(['ip' => '192.168.1.1'], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testIpv4ValidationFailsWithIpv6(ValidatorTester $I): void
    {
        $I->wantTo('verify ipv4 validation fails with ipv6 address');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"ip": {"type": "string", "format": "ipv4"}}}'));
        $rules = ['ip' => $this->mapper->map('ip', $schema->getProperties()['ip'], [])];

        $validator = new Validator();
        $result = $validator->validate(['ip' => '::1'], $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== IPV6 FORMAT ====================

    public function testIpv6FormatGeneratesIpRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that ipv6 format generates Ip rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"ip": {"type": "string", "format": "ipv6"}}}'));
        $rules = $this->mapper->map('ip', $schema->getProperties()['ip'], []);

        $hasIpRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Ip) {
                $hasIpRule = true;
                $I->assertFalse($rule->isIpv4Allowed());
                $I->assertTrue($rule->isIpv6Allowed());
                break;
            }
        }
        $I->assertTrue($hasIpRule);
    }

    public function testIpv6ValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify ipv6 validation passes with valid ipv6');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"ip": {"type": "string", "format": "ipv6"}}}'));
        $rules = ['ip' => $this->mapper->map('ip', $schema->getProperties()['ip'], [])];

        $validator = new Validator();
        $result = $validator->validate(['ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334'], $rules);

        $I->assertTrue($result->isValid());
    }

    // ==================== LENGTH CONSTRAINTS ====================

    public function testMinLengthGeneratesLengthRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that minLength generates Length rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"name": {"type": "string", "minLength": 3}}}'));
        $rules = $this->mapper->map('name', $schema->getProperties()['name'], []);

        $hasLengthRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Length) {
                $hasLengthRule = true;
                $I->assertEquals(3, $rule->getMin());
                break;
            }
        }
        $I->assertTrue($hasLengthRule);
    }

    public function testMaxLengthGeneratesLengthRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that maxLength generates Length rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"name": {"type": "string", "maxLength": 50}}}'));
        $rules = $this->mapper->map('name', $schema->getProperties()['name'], []);

        $hasLengthRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Length) {
                $hasLengthRule = true;
                $I->assertEquals(50, $rule->getMax());
                break;
            }
        }
        $I->assertTrue($hasLengthRule);
    }

    public function testLengthValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify length validation passes with valid length');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"name": {"type": "string", "minLength": 3, "maxLength": 10}}}'));
        $rules = ['name' => $this->mapper->map('name', $schema->getProperties()['name'], [])];

        $validator = new Validator();
        $result = $validator->validate(['name' => 'John'], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testLengthValidationFailsTooShort(ValidatorTester $I): void
    {
        $I->wantTo('verify length validation fails when too short');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"name": {"type": "string", "minLength": 3}}}'));
        $rules = ['name' => $this->mapper->map('name', $schema->getProperties()['name'], [])];

        $validator = new Validator();
        $result = $validator->validate(['name' => 'AB'], $rules);

        $I->assertFalse($result->isValid());
    }

    public function testLengthValidationFailsTooLong(ValidatorTester $I): void
    {
        $I->wantTo('verify length validation fails when too long');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"name": {"type": "string", "maxLength": 5}}}'));
        $rules = ['name' => $this->mapper->map('name', $schema->getProperties()['name'], [])];

        $validator = new Validator();
        $result = $validator->validate(['name' => 'TooLongName'], $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== PATTERN ====================

    public function testPatternGeneratesRegexRule(ValidatorTester $I): void
    {
        $I->wantTo('verify that pattern generates Regex rule');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"code": {"type": "string", "pattern": "^[A-Z]{3}[0-9]{3}$"}}}'));
        $rules = $this->mapper->map('code', $schema->getProperties()['code'], []);

        $hasRegexRule = false;
        foreach ($rules as $rule) {
            if ($rule instanceof Regex) {
                $hasRegexRule = true;
                break;
            }
        }
        $I->assertTrue($hasRegexRule);
    }

    public function testPatternValidationPasses(ValidatorTester $I): void
    {
        $I->wantTo('verify pattern validation passes with matching value');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"code": {"type": "string", "pattern": "^[A-Z]{3}[0-9]{3}$"}}}'));
        $rules = ['code' => $this->mapper->map('code', $schema->getProperties()['code'], [])];

        $validator = new Validator();
        $result = $validator->validate(['code' => 'ABC123'], $rules);

        $I->assertTrue($result->isValid());
    }

    public function testPatternValidationFails(ValidatorTester $I): void
    {
        $I->wantTo('verify pattern validation fails with non-matching value');

        $schema = Schema::import(json_decode('{"type": "object", "properties": {"code": {"type": "string", "pattern": "^[A-Z]{3}[0-9]{3}$"}}}'));
        $rules = ['code' => $this->mapper->map('code', $schema->getProperties()['code'], [])];

        $validator = new Validator();
        $result = $validator->validate(['code' => 'invalid'], $rules);

        $I->assertFalse($result->isValid());
    }
}
