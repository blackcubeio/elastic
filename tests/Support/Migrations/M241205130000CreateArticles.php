<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the articles table for testing custom elastic column name.
 */
final class M241205130000CreateArticles implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%articles}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'title' => ColumnBuilder::string(255)->notNull(),
            'elasticSchemaId' => ColumnBuilder::integer(),
            '_data' => ColumnBuilder::text(),
        ]);

        $b->addForeignKey(
            '{{%articles}}',
            'fk-articles-elasticSchemaId',
            ['elasticSchemaId'],
            '{{%elasticSchemas}}',
            ['id'],
            'SET NULL',
            'CASCADE'
        );
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropForeignKey('{{%articles}}', 'fk-articles-elasticSchemaId');
        $b->dropTable('{{%articles}}');
    }
}
