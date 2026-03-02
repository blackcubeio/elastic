<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\ElasticSchema;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Blackcube\Elastic\Tests\Support\ElasticSchemaTester;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

final class ElasticSchemaCest
{
    public function _before(ElasticSchemaTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        // Set connection provider for ActiveRecord
        ConnectionProvider::set($db);

        // Drop tables if exist (tables have FK to elasticSchemas)
        $db->createCommand('DROP TABLE IF EXISTS `products`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `articles`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `tags`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `testModels`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `elasticSchemas`')->execute();

        // Run migration up
        $migration = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());
        $migration->up($builder);
    }

    public function testCreateElasticSchema(ElasticSchemaTester $I): void
    {
        $schema = new ElasticSchema();
        $schema->setName('TestSchema');
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
            ],
        ]));
        $schema->save();

        $I->assertNotNull($schema->getId());
        $I->assertEquals('TestSchema', $schema->getName());
    }

    public function testReadElasticSchema(ElasticSchemaTester $I): void
    {
        // Create
        $schema = new ElasticSchema();
        $schema->setName('ReadTest');
        $schema->setSchema('{"type":"object"}');
        $schema->setView('custom_view');
        $schema->save();

        $id = $schema->getId();

        // Read back
        $found = ElasticSchema::query()->where(['id' => $id])->one();

        $I->assertNotNull($found);
        $I->assertEquals('ReadTest', $found->getName());
        $I->assertEquals('{"type":"object"}', $found->getSchema());
        $I->assertEquals('custom_view', $found->getView());
        $I->assertNotNull($found->getDateCreate());
    }

    public function testUpdateElasticSchema(ElasticSchemaTester $I): void
    {
        // Create
        $schema = new ElasticSchema();
        $schema->setName('UpdateTest');
        $schema->setSchema('{"type":"object"}');
        $schema->save();

        $id = $schema->getId();

        // Update
        $found = ElasticSchema::query()->where(['id' => $id])->one();
        $found->setName('UpdatedName');
        $found->save();

        // Read again
        $updated = ElasticSchema::query()->where(['id' => $id])->one();

        $I->assertEquals('UpdatedName', $updated->getName());
    }

    public function testDeleteElasticSchema(ElasticSchemaTester $I): void
    {
        // Create
        $schema = new ElasticSchema();
        $schema->setName('DeleteTest');
        $schema->save();

        $id = $schema->getId();

        // Delete
        $found = ElasticSchema::query()->where(['id' => $id])->one();
        $found->delete();

        // Check deleted
        $deleted = ElasticSchema::query()->where(['id' => $id])->one();

        $I->assertNull($deleted);
    }

    public function testGetDisplayViewWithNullBasePath(ElasticSchemaTester $I): void
    {
        $schema = new ElasticSchema();
        $schema->setName('TestSchema');

        $I->assertFalse($schema->getDisplayView(null));
    }

    public function testGetDisplayViewWithCustomView(ElasticSchemaTester $I): void
    {
        $schema = new ElasticSchema();
        $schema->setName('TestSchema');
        $schema->setView('my_custom_view');

        $result = $schema->getDisplayView('/tmp', true);

        $I->assertEquals('/tmp/my_custom_view.php', $result);
    }

    public function testGetDisplayViewFromName(ElasticSchemaTester $I): void
    {
        $schema = new ElasticSchema();
        $schema->setName('MyTestSchema');

        $result = $schema->getDisplayView('/tmp', true);

        $I->assertEquals('/tmp/my_test_schema.php', $result);
    }

    public function testGetDisplayViewFileNotExists(ElasticSchemaTester $I): void
    {
        $schema = new ElasticSchema();
        $schema->setName('NonExistent');

        $result = $schema->getDisplayView('/tmp');

        $I->assertFalse($result);
    }

    public function testGetDisplayViewFileExists(ElasticSchemaTester $I): void
    {
        // Create temp file
        $tempDir = sys_get_temp_dir() . '/elastic_test_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/existing_view.php', '<?php // test');

        $schema = new ElasticSchema();
        $schema->setName('ExistingView');

        $result = $schema->getDisplayView($tempDir);

        $I->assertEquals($tempDir . '/existing_view.php', $result);

        // Cleanup
        unlink($tempDir . '/existing_view.php');
        rmdir($tempDir);
    }
}
