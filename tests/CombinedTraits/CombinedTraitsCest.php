<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\CombinedTraits;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\CombinedTraitsModel;
use Blackcube\Elastic\Tests\Support\CombinedTraitsTester;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * Integration tests for combining ElasticTrait with another trait that has magic methods.
 * This validates the "double exposition" pattern documented in magic-traits.md.
 */
final class CombinedTraitsCest
{
    private ?int $schemaId = null;

    public function _before(CombinedTraitsTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();
        ConnectionProvider::set($db);

        // Drop tables if exist (order matters due to FK constraints)
        $db->createCommand('DROP TABLE IF EXISTS `combinedModels`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `products`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `articles`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `tags`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `testModels`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `elasticSchemas`')->execute();

        // Run migration for elasticSchemas
        $migration = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());
        $migration->up($builder);

        // Create combinedModels table
        $db->createCommand('
            CREATE TABLE `combinedModels` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `elasticSchemaId` INT NULL,
                `_extras` JSON NULL,
                FOREIGN KEY (`elasticSchemaId`) REFERENCES `elasticSchemas`(`id`) ON DELETE SET NULL
            )
        ')->execute();

        // Create a schema for testing
        $schema = new ElasticSchema();
        $schema->setName('CombinedTestSchema');
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'count' => ['type' => 'integer'],
            ],
            'required' => ['title'],
        ]));
        $schema->save();
        $this->schemaId = $schema->getId();

        // Clear schema cache
        CombinedTraitsModel::clearSchemaCache();
    }

    public function _after(CombinedTraitsTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        // Clean up our table to avoid FK issues for other test suites
        $db->createCommand('DROP TABLE IF EXISTS `combinedModels`')->execute();
    }

    // ==================== MockMagicTrait tests ====================

    public function testMockMagicSetAndGet(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();

        // Set via magic __set
        $model->mockLeft = 1;
        $model->mockRight = 10;
        $model->mockLevel = 2;

        // Get via magic __get
        $I->assertEquals(1, $model->mockLeft);
        $I->assertEquals(10, $model->mockRight);
        $I->assertEquals(2, $model->mockLevel);
    }

    public function testMockMagicIsset(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();

        $I->assertFalse(isset($model->mockLeft));

        $model->mockLeft = 5;

        $I->assertTrue(isset($model->mockLeft));
    }

    public function testMockMagicCallGetter(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->mockLeft = 3;

        // Use getter method via __call
        $I->assertEquals(3, $model->getMockLeft());
    }

    public function testMockMagicCallSetter(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();

        // Use setter method via __call
        $model->setMockRight(15);

        $I->assertEquals(15, $model->mockRight);
    }

    public function testMockMagicCallCustomMethod(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->mockLeft = 1;
        $model->mockRight = 2;

        // isLeaf() returns true when right - left === 1
        $I->assertTrue($model->isLeaf());

        $model->mockRight = 10;
        $I->assertFalse($model->isLeaf());
    }

    // ==================== ElasticTrait tests ====================

    public function testElasticSetAndGet(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;

        // Set elastic property via magic __set
        $model->title = 'Test Title';
        $model->count = 42;

        // Get via magic __get
        $I->assertEquals('Test Title', $model->title);
        $I->assertEquals(42, $model->count);
    }

    public function testElasticIsset(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;

        $I->assertFalse(isset($model->title));

        $model->title = 'Hello';

        $I->assertTrue(isset($model->title));
    }

    public function testElasticCallGetter(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;
        $model->title = 'Getter Test';

        // Use getter method via __call
        $I->assertEquals('Getter Test', $model->getTitle());
    }

    public function testElasticCallSetter(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;

        // Use setter method via __call
        $model->setTitle('Setter Test');

        $I->assertEquals('Setter Test', $model->title);
    }

    // ==================== Combined usage tests ====================

    public function testBothTraitsWorkTogether(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;

        // Set properties from both traits
        $model->mockLeft = 1;
        $model->mockRight = 10;
        $model->mockLevel = 2;
        $model->title = 'Combined Test';
        $model->count = 100;

        // Verify both work
        $I->assertEquals(1, $model->mockLeft);
        $I->assertEquals(10, $model->mockRight);
        $I->assertEquals(2, $model->mockLevel);
        $I->assertEquals('Combined Test', $model->title);
        $I->assertEquals(100, $model->count);
    }

    public function testBothTraitsIssetWorkTogether(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;

        // Initially nothing is set
        $I->assertFalse(isset($model->mockLeft));
        $I->assertFalse(isset($model->title));

        // Set one from each
        $model->mockLeft = 5;
        $model->title = 'Test';

        // Now they should be set
        $I->assertTrue(isset($model->mockLeft));
        $I->assertTrue(isset($model->title));

        // But others still not set
        $I->assertFalse(isset($model->mockRight));
        $I->assertFalse(isset($model->count));
    }

    public function testBothTraitsCallWorkTogether(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;

        // Use setters from both traits
        $model->setMockLeft(1);
        $model->setMockRight(2);
        $model->setTitle('Call Test');
        $model->setCount(50);

        // Use getters from both traits
        $I->assertEquals(1, $model->getMockLeft());
        $I->assertEquals(2, $model->getMockRight());
        $I->assertEquals('Call Test', $model->getTitle());
        $I->assertEquals(50, $model->getCount());

        // Use custom method from MockMagicTrait
        $I->assertTrue($model->isLeaf());
    }

    public function testPriorityMockMagicOverElastic(CombinedTraitsTester $I): void
    {
        // This test verifies that MockMagicTrait (simulating HazeltreeTrait)
        // has priority over ElasticTrait when property names don't overlap
        $model = new CombinedTraitsModel();
        $model->elasticSchemaId = $this->schemaId;

        // mockLeft is handled by MockMagicTrait
        $model->mockLeft = 42;
        $I->assertEquals(42, $model->mockLeft);

        // title is handled by ElasticTrait (since MockMagicTrait doesn't know it)
        $model->title = 'Priority Test';
        $I->assertEquals('Priority Test', $model->title);
    }

    public function testNoConflictBetweenTraits(CombinedTraitsTester $I): void
    {
        // Verify there's no PHP fatal error about trait method collision
        // If we got here, the traits were combined successfully
        $model = new CombinedTraitsModel();

        $I->assertInstanceOf(CombinedTraitsModel::class, $model);

        // Verify both traits are functional
        $model->mockLeft = 1;
        $I->assertEquals(1, $model->mockLeft);

        $model->elasticSchemaId = $this->schemaId;
        $model->title = 'No Conflict';
        $I->assertEquals('No Conflict', $model->title);
    }
}
