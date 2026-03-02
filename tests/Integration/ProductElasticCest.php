<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Integration;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\IntegrationTester;
use Blackcube\Elastic\Tests\Support\Migrations\M241205140000CreateProducts;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Blackcube\Elastic\Tests\Support\Product;
use Blackcube\Elastic\Validator\ElasticRuleResolver;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Validator\Validator;

/**
 * Integration tests for ElasticTrait with fully custom column names:
 * - 'data' instead of '_extras'
 * - 'sid' instead of 'elasticSchemaId'
 */
class ProductElasticCest
{
    private int $schemaId;
    private ElasticRuleResolver $resolver;

    public function _before(IntegrationTester $I): void
    {
        Product::clearSchemaCache();

        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        ConnectionProvider::set($db);

        $db->createCommand('DROP TABLE IF EXISTS `products`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `articles`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `tags`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `elasticSchemas`')->execute();

        $migrationElasticSchemas = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());
        $migrationElasticSchemas->up($builder);

        $migrationProducts = new M241205140000CreateProducts();
        $migrationProducts->up($builder);

        // Create schema with sku (required), price and inStock (optional)
        $schema = new ElasticSchema();
        $schema->setName('product-info');
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'sku' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 20,
                ],
                'price' => [
                    'type' => 'number',
                    'minimum' => 0,
                ],
                'inStock' => [
                    'type' => 'boolean',
                ],
            ],
            'required' => ['sku'],
        ]));
        $schema->save();
        $this->schemaId = $schema->getId();

        $config = ContainerConfig::create()
            ->withDefinitions([
                ElasticRuleResolver::class => ElasticRuleResolver::class,
            ]);
        $container = new Container($config);
        $this->resolver = $container->get(ElasticRuleResolver::class);
    }

    // ==================== COLUMN NAMES ====================

    public function testElasticColumnNameIsData(IntegrationTester $I): void
    {
        $I->wantTo('verify that the elastic column name is data');

        $product = new Product();

        $I->assertEquals('data', $product->elasticColumn());
    }

    public function testElasticSchemaColumnNameIsSid(IntegrationTester $I): void
    {
        $I->wantTo('verify that the schema column name is sid');

        $product = new Product();

        $I->assertEquals('sid', $product->elasticSchemaColumn());
    }

    // ==================== BASIC CRUD ====================

    public function testCreateProductWithElasticProperties(IntegrationTester $I): void
    {
        $I->wantTo('create a product with elastic properties using custom column names');

        $product = new Product();
        $product->setName('Test Product');
        $product->sid = $this->schemaId;
        $product->sku = 'PROD-001';
        $product->price = 29.99;
        $product->inStock = true;

        $product->insert();

        $loaded = Product::query()->where(['id' => $product->getId()])->one();

        $I->assertNotNull($loaded);
        $I->assertEquals('Test Product', $loaded->getName());
        $I->assertEquals($this->schemaId, $loaded->sid);
        $I->assertEquals('PROD-001', $loaded->sku);
        $I->assertEquals(29.99, $loaded->price);
        $I->assertTrue($loaded->inStock);
    }

    public function testCreateProductWithOnlyRequiredProperty(IntegrationTester $I): void
    {
        $I->wantTo('create a product with only required elastic property');

        $product = new Product();
        $product->setName('Minimal Product');
        $product->sid = $this->schemaId;
        $product->sku = 'MIN-001';

        $product->insert();

        $loaded = Product::query()->where(['id' => $product->getId()])->one();

        $I->assertNotNull($loaded);
        $I->assertEquals('MIN-001', $loaded->sku);
        $I->assertNull($loaded->price);
        $I->assertNull($loaded->inStock);
    }

    public function testUpdateElasticProperty(IntegrationTester $I): void
    {
        $I->wantTo('update an elastic property and verify persistence');

        $product = new Product();
        $product->setName('Update Test');
        $product->sid = $this->schemaId;
        $product->sku = 'UPD-001';
        $product->price = 19.99;
        $product->insert();

        $loaded = Product::query()->where(['id' => $product->getId()])->one();
        $loaded->sku = 'UPD-002';
        $loaded->price = 24.99;
        $loaded->inStock = false;
        $loaded->update();

        $reloaded = Product::query()->where(['id' => $product->getId()])->one();
        $I->assertEquals('UPD-002', $reloaded->sku);
        $I->assertEquals(24.99, $reloaded->price);
        $I->assertFalse($reloaded->inStock);
    }

    // ==================== MAGIC METHODS ====================

    public function testGetDataReturnsJsonString(IntegrationTester $I): void
    {
        $I->wantTo('verify getData() returns JSON string');

        $product = new Product();
        $product->sid = $this->schemaId;
        $product->sku = 'JSON-001';
        $product->price = 9.99;

        $data = $product->getData();

        $I->assertIsString($data);
        $decoded = json_decode($data, true);
        $I->assertEquals('JSON-001', $decoded['sku']);
        $I->assertEquals(9.99, $decoded['price']);
    }

    public function testSetDataAcceptsJsonString(IntegrationTester $I): void
    {
        $I->wantTo('verify setData() accepts JSON string with protection disabled');

        $product = new Product();
        $product->sid = $this->schemaId;
        $product->protectElastic(false);
        $product->setData(json_encode(['sku' => 'SET-001', 'price' => 14.99]));

        $I->assertEquals('SET-001', $product->sku);
        $I->assertEquals(14.99, $product->price);
    }

    public function testGetSidReturnSchemaId(IntegrationTester $I): void
    {
        $I->wantTo('verify getSid() returns schema ID');

        $product = new Product();
        $product->sid = $this->schemaId;

        $I->assertEquals($this->schemaId, $product->getSid());
    }

    public function testSetSidSetsSchemaId(IntegrationTester $I): void
    {
        $I->wantTo('verify setSid() sets schema ID');

        $product = new Product();
        $product->setSid($this->schemaId);

        $I->assertEquals($this->schemaId, $product->sid);
    }

    // ==================== VALIDATION ====================

    public function testValidationPassesWithValidData(IntegrationTester $I): void
    {
        $I->wantTo('verify validation passes with valid elastic data');

        $product = new Product();
        $product->setName('Valid Product');
        $product->sid = $this->schemaId;
        $product->sku = 'VAL-001';
        $product->price = 49.99;
        $product->inStock = true;
        $product->insert();

        $loaded = Product::query()->where(['id' => $product->getId()])->one();
        $rules = $this->resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertTrue($result->isValid());
    }

    public function testValidationFailsWithSkuTooShort(IntegrationTester $I): void
    {
        $I->wantTo('verify validation fails when sku is too short');

        $product = new Product();
        $product->setName('Invalid SKU Product');
        $product->sid = $this->schemaId;
        $product->sku = 'AB'; // minLength is 3
        $product->insert();

        $loaded = Product::query()->where(['id' => $product->getId()])->one();
        $rules = $this->resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
    }

    public function testValidationFailsWithNegativePrice(IntegrationTester $I): void
    {
        $I->wantTo('verify validation fails when price is negative');

        $product = new Product();
        $product->setName('Negative Price Product');
        $product->sid = $this->schemaId;
        $product->sku = 'NEG-001';
        $product->price = -10.00; // minimum is 0
        $product->insert();

        $loaded = Product::query()->where(['id' => $product->getId()])->one();
        $rules = $this->resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
    }

    public function testValidationFailsWithMissingRequiredSku(IntegrationTester $I): void
    {
        $I->wantTo('verify validation fails when required sku is missing');

        $product = new Product();
        $product->setName('No SKU Product');
        $product->sid = $this->schemaId;
        $product->insert();

        $loaded = Product::query()->where(['id' => $product->getId()])->one();
        $rules = $this->resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== QUERY ====================

    public function testQueryWhereOnVirtualColumn(IntegrationTester $I): void
    {
        $I->wantTo('query products using where on virtual column (sku)');

        $product1 = new Product();
        $product1->setName('Product 1');
        $product1->sid = $this->schemaId;
        $product1->sku = 'SKU-AAA';
        $product1->insert();

        $product2 = new Product();
        $product2->setName('Product 2');
        $product2->sid = $this->schemaId;
        $product2->sku = 'SKU-BBB';
        $product2->insert();

        $results = Product::query()->where(['sku' => 'SKU-AAA'])->all();

        $I->assertCount(1, $results);
        $I->assertEquals('SKU-AAA', $results[0]->sku);
    }

    public function testQueryOrderByVirtualColumn(IntegrationTester $I): void
    {
        $I->wantTo('query products ordered by virtual column (price)');

        $product1 = new Product();
        $product1->setName('Cheap Product');
        $product1->sid = $this->schemaId;
        $product1->sku = 'CHEAP-001';
        $product1->price = 9.99;
        $product1->insert();

        $product2 = new Product();
        $product2->setName('Expensive Product');
        $product2->sid = $this->schemaId;
        $product2->sku = 'EXP-001';
        $product2->price = 99.99;
        $product2->insert();

        $results = Product::query()->orderBy(['price' => SORT_DESC])->all();

        $I->assertCount(2, $results);
        $I->assertEquals(99.99, $results[0]->price);
        $I->assertEquals(9.99, $results[1]->price);
    }

    public function testQueryWithBooleanVirtualColumn(IntegrationTester $I): void
    {
        $I->wantTo('query products filtering by boolean virtual column (inStock)');

        $product1 = new Product();
        $product1->setName('In Stock Product');
        $product1->sid = $this->schemaId;
        $product1->sku = 'STOCK-001';
        $product1->inStock = true;
        $product1->insert();

        $product2 = new Product();
        $product2->setName('Out of Stock Product');
        $product2->sid = $this->schemaId;
        $product2->sku = 'STOCK-002';
        $product2->inStock = false;
        $product2->insert();

        $inStockResults = Product::query()->where(['inStock' => true])->all();
        $outOfStockResults = Product::query()->where(['inStock' => false])->all();

        $I->assertCount(1, $inStockResults);
        $I->assertEquals('STOCK-001', $inStockResults[0]->sku);

        $I->assertCount(1, $outOfStockResults);
        $I->assertEquals('STOCK-002', $outOfStockResults[0]->sku);
    }
}
