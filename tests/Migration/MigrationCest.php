<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Migration;

use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\MigrationTester;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

final class MigrationCest
{
    public function _before(MigrationTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        // Clean state (tables have FK to elasticSchemas)
        $db->createCommand('DROP TABLE IF EXISTS `products`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `articles`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `tags`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `testModels`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `elasticSchemas`')->execute();
    }

    public function testMigrationUp(MigrationTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        $migration = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());

        $migration->up($builder);

        // Check table exists
        $tableSchema = $db->getTableSchema('elasticSchemas');
        $I->assertNotNull($tableSchema);

        // Check columns
        $columns = $tableSchema->getColumns();
        $I->assertArrayHasKey('id', $columns);
        $I->assertArrayHasKey('name', $columns);
        $I->assertArrayHasKey('schema', $columns);
        $I->assertArrayHasKey('view', $columns);
        $I->assertArrayHasKey('dateCreate', $columns);
        $I->assertArrayHasKey('dateUpdate', $columns);
    }

    public function testMigrationDown(MigrationTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        $migration = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());

        // Up then down
        $migration->up($builder);
        $migration->down($builder);

        // Check table does not exist
        $tableSchema = $db->getTableSchema('elasticSchemas', true);
        $I->assertNull($tableSchema);
    }

    public function testMigrationUpDown(MigrationTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        $migration = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());

        // Up
        $migration->up($builder);
        $I->assertNotNull($db->getTableSchema('elasticSchemas'));

        // Down
        $migration->down($builder);
        $I->assertNull($db->getTableSchema('elasticSchemas', true));

        // Up again
        $migration->up($builder);
        $I->assertNotNull($db->getTableSchema('elasticSchemas', true));
    }
}
