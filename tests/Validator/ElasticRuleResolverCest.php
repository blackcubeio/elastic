<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Validator;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\ElasticTrait;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Blackcube\Elastic\Tests\Support\TestModel;
use Blackcube\Elastic\Tests\Support\ValidatorTester;
use Blackcube\Elastic\Validator\ElasticRuleResolver;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Validator\Validator;

class ElasticRuleResolverCest
{
    private int $schemaId;

    public function _before(ValidatorTester $I): void
    {
        // Clear static schema cache from previous tests
        TestModel::clearSchemaCache();

        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        ConnectionProvider::set($db);

        // Drop tables (including tables from Integration tests to handle FK constraint)
        $db->createCommand('DROP TABLE IF EXISTS `products`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `articles`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `tags`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `testModels`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `elasticSchemas`')->execute();

        // Create elasticSchemas via migration
        $migration = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());
        $migration->up($builder);

        // Create testModels table
        $db->createCommand('
            CREATE TABLE `testModels` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `elasticSchemaId` INT,
                `_extras` TEXT
            )
        ')->execute();

        // Create a schema with required field
        $schema = new ElasticSchema();
        $schema->setName('TestSchemaWithRequired');
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name', 'email'],
        ]));
        $schema->save();
        $this->schemaId = $schema->getId();
    }

    public function testValidationPassesWithRequiredFieldsFilled(ValidatorTester $I): void
    {
        $I->wantTo('verify validation passes when required fields are filled');

        // Create and save model
        $model = new TestModel();
        $model->setTitle('Test');
        $model->elasticSchemaId = $this->schemaId;
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->age = 30;
        $model->insert();

        // Load from DB
        $loaded = TestModel::query()->where(['id' => $model->getId()])->one();

        // Resolve rules and validate
        $resolver = new ElasticRuleResolver();
        $rules = $resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertTrue($result->isValid());
    }

    public function testValidationFailsWhenRequiredFieldIsNull(ValidatorTester $I): void
    {
        $I->wantTo('verify validation fails when required field is set to null');

        // Create and save model with valid data
        $model = new TestModel();
        $model->setTitle('Test');
        $model->elasticSchemaId = $this->schemaId;
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->insert();

        // Load from DB
        $loaded = TestModel::query()->where(['id' => $model->getId()])->one();

        // Set required field to null
        $loaded->name = null;

        // Resolve rules and validate
        $resolver = new ElasticRuleResolver();
        $rules = $resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
        $I->assertNotEmpty($result->getErrors());
    }

    public function testValidationFailsWhenRequiredFieldIsEmptyString(ValidatorTester $I): void
    {
        $I->wantTo('verify validation fails when required field is set to empty string');

        // Create and save model with valid data
        $model = new TestModel();
        $model->setTitle('Test');
        $model->elasticSchemaId = $this->schemaId;
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->insert();

        // Load from DB
        $loaded = TestModel::query()->where(['id' => $model->getId()])->one();

        // Set required field to empty string
        $loaded->name = '';

        // Resolve rules and validate
        $resolver = new ElasticRuleResolver();
        $rules = $resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
        $I->assertNotEmpty($result->getErrors());
    }

    public function testValidationFailsWhenEmailFormatIsInvalid(ValidatorTester $I): void
    {
        $I->wantTo('verify validation fails when email format is invalid');

        // Create model with invalid email
        $model = new TestModel();
        $model->setTitle('Test');
        $model->elasticSchemaId = $this->schemaId;
        $model->name = 'John Doe';
        $model->email = 'not-an-email';
        $model->insert();

        // Load from DB
        $loaded = TestModel::query()->where(['id' => $model->getId()])->one();

        // Resolve rules and validate
        $resolver = new ElasticRuleResolver();
        $rules = $resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
        $I->assertNotEmpty($result->getErrors());
    }
}
