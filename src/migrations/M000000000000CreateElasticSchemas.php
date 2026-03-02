<?php

declare(strict_types=1);

/**
 * M000000000000CreateElasticSchemas.php
 *
 * PHP version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Elastic\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the elasticSchemas table.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class M000000000000CreateElasticSchemas implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%elasticSchemas}}', [
            'id' => ColumnBuilder::primaryKey(),
            'name' => ColumnBuilder::string(255)->notNull()->unique(),
            'schema' => ColumnBuilder::text(),
            'view' => ColumnBuilder::string(255),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%elasticSchemas}}');
    }
}
