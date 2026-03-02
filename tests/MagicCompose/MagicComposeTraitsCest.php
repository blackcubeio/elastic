<?php

declare(strict_types=1);

/**
 * MagicComposeTraitsCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Elastic\Tests\MagicCompose;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\MagicComposeModel;
use Blackcube\Elastic\Tests\Support\MagicComposeTester;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * Integration tests for ElasticTrait with MagicCompose.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class MagicComposeTraitsCest
{
    private ConnectionInterface $db;
    private ?int $schemaId = null;

    public function _before(MagicComposeTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();
        ConnectionProvider::set($this->db);

        $this->db->createCommand('DROP TABLE IF EXISTS `magicComposeModels`')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS `combinedModels`')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS `products`')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS `articles`')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS `tags`')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS `testModels`')->execute();
        $this->db->createCommand('DROP TABLE IF EXISTS `elasticSchemas`')->execute();

        // Run migration for elasticSchemas
        $migration = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $migration->up($builder);

        $this->db->createCommand('
            CREATE TABLE `magicComposeModels` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `elasticSchemaId` INT NULL,
                `_extras` JSON NULL,
                CONSTRAINT `fk_magicComposeModels_elasticSchemaId` FOREIGN KEY (`elasticSchemaId`) REFERENCES `elasticSchemas`(`id`) ON DELETE SET NULL
            )
        ')->execute();

        // Create a schema for testing
        $schema = new ElasticSchema();
        $schema->setName('MagicComposeTestSchema');
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

        MagicComposeModel::clearSchemaCache();
    }

    public function _after(MagicComposeTester $I): void
    {
        $this->db->createCommand('DROP TABLE IF EXISTS `magicComposeModels`')->execute();
    }

    // ==================== MockHazeltreeTrait tests ====================

    public function testMockHazeltreeSetAndGet(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $model->mockLeft = 1;
        $model->mockRight = 10;
        $model->mockLevel = 2;

        $I->assertEquals(1, $model->mockLeft);
        $I->assertEquals(10, $model->mockRight);
        $I->assertEquals(2, $model->mockLevel);
    }

    public function testMockHazeltreeIsset(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $I->assertFalse(isset($model->mockLeft));

        $model->mockLeft = 5;

        $I->assertTrue(isset($model->mockLeft));
    }

    public function testMockHazeltreeCallGetter(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->mockLeft = 3;

        $I->assertEquals(3, $model->getMockLeft());
    }

    public function testMockHazeltreeCallSetter(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $model->setMockRight(15);

        $I->assertEquals(15, $model->mockRight);
    }

    public function testMockHazeltreeCallCustomMethod(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->mockLeft = 1;
        $model->mockRight = 2;

        $I->assertTrue($model->isLeaf());

        $model->mockRight = 10;
        $I->assertFalse($model->isLeaf());
    }

    // ==================== ElasticTrait tests ====================

    public function testElasticSetAndGet(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->elasticSchemaId = $this->schemaId;

        $model->title = 'Test Title';
        $model->count = 42;

        $I->assertEquals('Test Title', $model->title);
        $I->assertEquals(42, $model->count);
    }

    public function testElasticIsset(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->elasticSchemaId = $this->schemaId;

        $I->assertFalse(isset($model->title));

        $model->title = 'Hello';

        $I->assertTrue(isset($model->title));
    }

    public function testElasticCallGetter(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->elasticSchemaId = $this->schemaId;
        $model->title = 'Getter Test';

        $I->assertEquals('Getter Test', $model->getTitle());
    }

    public function testElasticCallSetter(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->elasticSchemaId = $this->schemaId;

        $model->setTitle('Setter Test');

        $I->assertEquals('Setter Test', $model->title);
    }

    // ==================== Combined usage tests ====================

    public function testBothTraitsWorkTogether(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->elasticSchemaId = $this->schemaId;

        $model->mockLeft = 1;
        $model->mockRight = 10;
        $model->mockLevel = 2;
        $model->title = 'Combined Test';
        $model->count = 100;

        $I->assertEquals(1, $model->mockLeft);
        $I->assertEquals(10, $model->mockRight);
        $I->assertEquals(2, $model->mockLevel);
        $I->assertEquals('Combined Test', $model->title);
        $I->assertEquals(100, $model->count);
    }

    public function testBothTraitsIssetWorkTogether(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->elasticSchemaId = $this->schemaId;

        $I->assertFalse(isset($model->mockLeft));
        $I->assertFalse(isset($model->title));

        $model->mockLeft = 5;
        $model->title = 'Test';

        $I->assertTrue(isset($model->mockLeft));
        $I->assertTrue(isset($model->title));

        $I->assertFalse(isset($model->mockRight));
        $I->assertFalse(isset($model->count));
    }

    public function testBothTraitsCallWorkTogether(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->elasticSchemaId = $this->schemaId;

        $model->setMockLeft(1);
        $model->setMockRight(2);
        $model->setTitle('Call Test');
        $model->setCount(50);

        $I->assertEquals(1, $model->getMockLeft());
        $I->assertEquals(2, $model->getMockRight());
        $I->assertEquals('Call Test', $model->getTitle());
        $I->assertEquals(50, $model->getCount());

        $I->assertTrue($model->isLeaf());
    }

    public function testNoConflictBetweenTraits(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $I->assertInstanceOf(MagicComposeModel::class, $model);

        $model->mockLeft = 1;
        $I->assertEquals(1, $model->mockLeft);

        $model->elasticSchemaId = $this->schemaId;
        $model->title = 'No Conflict';
        $I->assertEquals('No Conflict', $model->title);
    }

    // ==================== Save/Load tests ====================

    public function testSaveAndLoadWithElastic(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->setName('Save Test');
        $model->elasticSchemaId = $this->schemaId;
        $model->title = 'Saved Title';
        $model->count = 123;
        $model->save();

        $loaded = MagicComposeModel::query()->where(['name' => 'Save Test'])->one();

        $I->assertNotNull($loaded);
        $I->assertEquals('Save Test', $loaded->getName());
        $I->assertEquals('Saved Title', $loaded->title);
        $I->assertEquals(123, $loaded->count);
    }

    public function testRefreshPreservesElastic(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->setName('Refresh Test');
        $model->elasticSchemaId = $this->schemaId;
        $model->title = 'Original';
        $model->save();

        // Update directly in DB
        $this->db->createCommand('UPDATE `magicComposeModels` SET `_extras` = :extras WHERE `id` = :id')
            ->bindValue(':extras', json_encode(['title' => 'Updated', 'count' => 999]))
            ->bindValue(':id', $model->getId())
            ->execute();

        $model->refresh();

        $I->assertEquals('Updated', $model->title);
        $I->assertEquals(999, $model->count);
    }
}
