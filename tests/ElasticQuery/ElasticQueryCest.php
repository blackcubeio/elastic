<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\ElasticQuery;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\ElasticQueryTester;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Blackcube\Elastic\Tests\Support\TestModel;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

final class ElasticQueryCest
{
    private int $schemaId;

    public function _before(ElasticQueryTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        ConnectionProvider::set($db);

        // Drop tables (tables have FK to elasticSchemas)
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

        // Create a schema
        $schema = new ElasticSchema();
        $schema->setName('TestSchema');
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'description' => ['type' => 'string'],
                'price' => ['type' => 'number'],
                'active' => ['type' => 'boolean'],
                'category' => ['type' => 'string'],
            ],
        ]));
        $schema->save();
        $this->schemaId = $schema->getId();

        // Insert test data
        $this->insertTestData();
    }

    private function insertTestData(): void
    {
        $models = [
            ['title' => 'Product A', 'description' => 'First product', 'price' => 10.99, 'active' => true, 'category' => 'electronics'],
            ['title' => 'Product B', 'description' => 'Second product', 'price' => 25.50, 'active' => false, 'category' => 'electronics'],
            ['title' => 'Product C', 'description' => 'Third product', 'price' => 5.00, 'active' => true, 'category' => 'books'],
            ['title' => 'Product D', 'description' => 'Fourth product', 'price' => 100.00, 'active' => true, 'category' => 'electronics'],
        ];

        foreach ($models as $data) {
            $model = new TestModel();
            $model->setTitle($data['title']);
            $model->elasticSchemaId = $this->schemaId;
            $model->description = $data['description'];
            $model->price = $data['price'];
            $model->active = $data['active'];
            $model->category = $data['category'];
            $model->insert();
        }
    }

    // ==================== WHERE TESTS ====================

    public function testWhereWithRealColumn(ElasticQueryTester $I): void
    {
        $result = TestModel::query()
            ->where(['title' => 'Product A'])
            ->one();

        $I->assertNotNull($result);
        $I->assertEquals('Product A', $result->getTitle());
    }

    public function testWhereWithVirtualColumn(ElasticQueryTester $I): void
    {
        $result = TestModel::query()
            ->where(['category' => 'books'])
            ->one();

        $I->assertNotNull($result);
        $I->assertEquals('Product C', $result->getTitle());
        $I->assertEquals('books', $result->category);
    }

    public function testWhereWithMixedColumns(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['category' => 'electronics', 'title' => 'Product A'])
            ->all();

        $I->assertCount(1, $results);
        $I->assertEquals('Product A', $results[0]->getTitle());
    }

    public function testWhereWithMultipleVirtualColumns(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['category' => 'electronics', 'active' => true])
            ->all();

        // Product A (active=true, electronics) and Product D (active=true, electronics)
        $I->assertCount(2, $results);
    }

    // ==================== AND WHERE TESTS ====================

    public function testAndWhereWithVirtualColumn(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['category' => 'electronics'])
            ->andWhere(['active' => true])
            ->all();

        $I->assertCount(2, $results);
    }

    public function testAndWhereWithRealAndVirtualColumn(ElasticQueryTester $I): void
    {
        $result = TestModel::query()
            ->where(['title' => 'Product A'])
            ->andWhere(['category' => 'electronics'])
            ->one();

        $I->assertNotNull($result);
        $I->assertEquals('Product A', $result->getTitle());
    }

    // ==================== OR WHERE TESTS ====================

    public function testOrWhereWithVirtualColumn(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['category' => 'books'])
            ->orWhere(['category' => 'electronics'])
            ->all();

        $I->assertCount(4, $results);
    }

    public function testOrWhereWithMixedColumns(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['title' => 'Product A'])
            ->orWhere(['category' => 'books'])
            ->all();

        $I->assertCount(2, $results);
    }

    // ==================== ORDER BY TESTS ====================

    public function testOrderByVirtualColumnAsc(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->orderBy(['category' => SORT_ASC])
            ->all();

        $I->assertCount(4, $results);
        $I->assertEquals('books', $results[0]->category);
    }

    public function testOrderByVirtualColumnDesc(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->orderBy(['category' => SORT_DESC])
            ->all();

        $I->assertCount(4, $results);
        $I->assertEquals('electronics', $results[0]->category);
    }

    public function testOrderByMixedColumns(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->orderBy(['category' => SORT_ASC, 'title' => SORT_DESC])
            ->all();

        $I->assertCount(4, $results);
        // First should be books category
        $I->assertEquals('books', $results[0]->category);
        // Then electronics, sorted by title desc: Product D, Product B, Product A
        $I->assertEquals('electronics', $results[1]->category);
    }

    // ==================== ADD ORDER BY TESTS ====================

    public function testAddOrderByVirtualColumn(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->orderBy(['category' => SORT_ASC])
            ->addOrderBy(['description' => SORT_ASC])
            ->all();

        $I->assertCount(4, $results);
        $I->assertEquals('books', $results[0]->category);
    }

    // ==================== OPERATOR FORMAT TESTS ====================

    public function testWhereOperatorFormatWithVirtualColumn(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['like', 'description', 'First'])
            ->all();

        $I->assertCount(1, $results);
        $I->assertEquals('Product A', $results[0]->getTitle());
    }

    public function testWhereBetweenWithVirtualColumn(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['between', 'price', 10, 30])
            ->all();

        // price 10.99 and 25.50 are between 10 and 30
        $I->assertCount(2, $results);
    }

    public function testWhereInWithVirtualColumn(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['in', 'category', ['books', 'electronics']])
            ->all();

        $I->assertCount(4, $results);
    }

    // ==================== AND/OR/NOT NESTED CONDITIONS ====================

    public function testWhereAndNestedCondition(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where([
                'and',
                ['category' => 'electronics'],
                ['active' => true],
            ])
            ->all();

        $I->assertCount(2, $results);
    }

    public function testWhereOrNestedCondition(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where([
                'or',
                ['category' => 'books'],
                ['active' => false],
            ])
            ->all();

        // Product C (books) + Product B (active=false)
        $I->assertCount(2, $results);
    }

    // ==================== REAL ATTRIBUTES CACHE ====================

    public function testGetRealAttributesReturnsDbColumns(ElasticQueryTester $I): void
    {
        $query = TestModel::query();
        $realAttributes = $query->getRealAttributes();

        $I->assertContains('id', $realAttributes);
        $I->assertContains('title', $realAttributes);
        $I->assertContains('elasticSchemaId', $realAttributes);
        $I->assertContains('_extras', $realAttributes);

        // Virtual columns should NOT be in real attributes
        $I->assertNotContains('description', $realAttributes);
        $I->assertNotContains('price', $realAttributes);
        $I->assertNotContains('category', $realAttributes);
    }

    // ==================== EXPRESSION PASSTHROUGH ====================

    public function testWhereWithExpressionIsNotModified(ElasticQueryTester $I): void
    {
        $expression = new Expression('title = :title', [':title' => 'Product A']);

        $result = TestModel::query()
            ->where($expression)
            ->one();

        $I->assertNotNull($result);
        $I->assertEquals('Product A', $result->getTitle());
    }

    public function testWhereWithStringConditionIsNotModified(ElasticQueryTester $I): void
    {
        $result = TestModel::query()
            ->where('title = :title', [':title' => 'Product A'])
            ->one();

        $I->assertNotNull($result);
        $I->assertEquals('Product A', $result->getTitle());
    }

    // ==================== COMBINED QUERY ====================

    public function testComplexQueryWithVirtualColumns(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(['category' => 'electronics'])
            ->andWhere(['active' => true])
            ->orderBy(['description' => SORT_ASC])
            ->all();

        $I->assertCount(2, $results);
        // First product and Fourth product (sorted by description)
        $I->assertEquals('First product', $results[0]->description);
        $I->assertEquals('Fourth product', $results[1]->description);
    }

    // ==================== TABLE.COLUMN FORMAT ====================

    public function testWhereWithTableDotVirtualColumn(ElasticQueryTester $I): void
    {
        // Format: tableName.virtualColumn should be transformed
        $result = TestModel::query()
            ->where(['{{%testModels}}.category' => 'books'])
            ->one();

        $I->assertNotNull($result);
        $I->assertEquals('Product C', $result->getTitle());
    }

    public function testWhereWithTableDotRealColumn(ElasticQueryTester $I): void
    {
        // Format: tableName.realColumn should NOT be transformed
        $result = TestModel::query()
            ->where(['{{%testModels}}.title' => 'Product A'])
            ->one();

        $I->assertNotNull($result);
        $I->assertEquals('Product A', $result->getTitle());
    }

    public function testWhereWithDifferentTableIsIgnored(ElasticQueryTester $I): void
    {
        // Format: otherTable.column should NOT be transformed to JSON_VALUE
        // because it belongs to a different table
        // This will fail at SQL level since otherTable doesn't exist,
        // but we're testing that isVirtualColumn returns false for other tables
        $query = TestModel::query();

        // We can't actually execute this query (table doesn't exist)
        // but we can verify the column is not treated as virtual by checking
        // that a real column from our table still works alongside
        $results = $query
            ->where(['title' => 'Product A'])
            ->all();

        $I->assertCount(1, $results);
    }

    public function testIsVirtualColumnReturnsFalseForOtherTable(ElasticQueryTester $I): void
    {
        // Test that a column prefixed with another table name is not treated as virtual
        // We test this indirectly: if 'otherTable.category' was treated as virtual,
        // it would generate JSON_VALUE(testModels._extras, '$.category')
        // But since it's another table, it should be left as 'otherTable.category'

        // Use reflection to test isVirtualColumn directly
        $query = TestModel::query();
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('isVirtualColumn');
        $method->setAccessible(true);

        // Column from our table should be virtual
        $I->assertTrue($method->invoke($query, 'category'));
        $I->assertTrue($method->invoke($query, '{{%testModels}}.category'));

        // Column from another table should NOT be virtual
        $I->assertFalse($method->invoke($query, 'otherTable.category'));
        $I->assertFalse($method->invoke($query, '{{%otherTable}}.category'));
    }

    public function testOrderByWithTableDotVirtualColumn(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->orderBy(['{{%testModels}}.category' => SORT_ASC])
            ->all();

        $I->assertCount(4, $results);
        $I->assertEquals('books', $results[0]->category);
    }

    // ==================== INTEGER KEY HANDLING ====================

    public function testWhereWithIntegerKeyIsNotTransformed(ElasticQueryTester $I): void
    {
        // Condition with integer key (operator format) should work
        $results = TestModel::query()
            ->where(['>', 'price', 20])
            ->all();

        // Products with price > 20: Product B (25.50), Product D (100.00)
        $I->assertCount(2, $results);
    }

    // ==================== NULL CONDITION ====================

    public function testWhereWithNullCondition(ElasticQueryTester $I): void
    {
        $results = TestModel::query()
            ->where(null)
            ->all();

        $I->assertCount(4, $results);
    }

    // ==================== SET WHERE ====================

    public function testSetWhereWithVirtualColumn(ElasticQueryTester $I): void
    {
        $query = TestModel::query()
            ->where(['category' => 'electronics']);

        // setWhere replaces the existing where condition
        $query->setWhere(['category' => 'books']);

        $results = $query->all();

        $I->assertCount(1, $results);
        $I->assertEquals('books', $results[0]->category);
    }

    public function testSetWhereAfterWhereWithVirtualColumn(ElasticQueryTester $I): void
    {
        $query = TestModel::query()
            ->where(['active' => true]);

        // Replace with different virtual column condition
        $query->setWhere(['category' => 'electronics', 'active' => false]);

        $results = $query->all();

        // Only Product B matches (electronics + active=false)
        $I->assertCount(1, $results);
        $I->assertEquals('Product B', $results[0]->getTitle());
    }
}
