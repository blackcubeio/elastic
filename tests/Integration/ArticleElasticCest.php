<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Integration;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\Article;
use Blackcube\Elastic\Tests\Support\IntegrationTester;
use Blackcube\Elastic\Tests\Support\Migrations\M241205130000CreateArticles;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Blackcube\Elastic\Validator\ElasticRuleResolver;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Validator\Validator;

/**
 * Integration tests for ElasticTrait with custom column name (_data instead of _extras).
 */
class ArticleElasticCest
{
    private int $schemaId;
    private ElasticRuleResolver $resolver;

    public function _before(IntegrationTester $I): void
    {
        Article::clearSchemaCache();

        $helper = new MysqlHelper();
        $db = $helper->createConnection();

        ConnectionProvider::set($db);

        $db->createCommand('DROP TABLE IF EXISTS `articles`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `tags`')->execute();
        $db->createCommand('DROP TABLE IF EXISTS `elasticSchemas`')->execute();

        $migrationElasticSchemas = new M000000000000CreateElasticSchemas();
        $builder = new MigrationBuilder($db, new NullMigrationInformer());
        $migrationElasticSchemas->up($builder);

        $migrationArticles = new M241205130000CreateArticles();
        $migrationArticles->up($builder);

        // Create schema with author (required) and rating (optional)
        $schema = new ElasticSchema();
        $schema->setName('article-meta');
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'author' => [
                    'type' => 'string',
                    'minLength' => 2,
                ],
                'rating' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 5,
                ],
            ],
            'required' => ['author'],
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

    // ==================== BASIC CRUD ====================

    public function testCreateArticleWithElasticProperties(IntegrationTester $I): void
    {
        $I->wantTo('create an article with elastic properties using _data column');

        $article = new Article();
        $article->setTitle('Test Article');
        $article->elasticSchemaId = $this->schemaId;
        $article->author = 'John Doe';
        $article->rating = 4;

        $article->insert();

        $loaded = Article::query()->where(['id' => $article->getId()])->one();

        $I->assertNotNull($loaded);
        $I->assertEquals('Test Article', $loaded->getTitle());
        $I->assertEquals('John Doe', $loaded->author);
        $I->assertEquals(4, $loaded->rating);
    }

    public function testCreateArticleWithOnlyRequiredProperty(IntegrationTester $I): void
    {
        $I->wantTo('create an article with only required elastic property');

        $article = new Article();
        $article->setTitle('Minimal Article');
        $article->elasticSchemaId = $this->schemaId;
        $article->author = 'Jane Doe';

        $article->insert();

        $loaded = Article::query()->where(['id' => $article->getId()])->one();

        $I->assertNotNull($loaded);
        $I->assertEquals('Jane Doe', $loaded->author);
        $I->assertNull($loaded->rating);
    }

    public function testUpdateElasticProperty(IntegrationTester $I): void
    {
        $I->wantTo('update an elastic property and verify persistence');

        $article = new Article();
        $article->setTitle('Update Test');
        $article->elasticSchemaId = $this->schemaId;
        $article->author = 'Original Author';
        $article->rating = 3;
        $article->insert();

        $loaded = Article::query()->where(['id' => $article->getId()])->one();
        $loaded->author = 'Updated Author';
        $loaded->rating = 5;
        $loaded->update();

        $reloaded = Article::query()->where(['id' => $article->getId()])->one();
        $I->assertEquals('Updated Author', $reloaded->author);
        $I->assertEquals(5, $reloaded->rating);
    }

    // ==================== VALIDATION ====================

    public function testValidationPassesWithValidData(IntegrationTester $I): void
    {
        $I->wantTo('verify validation passes with valid elastic data');

        $article = new Article();
        $article->setTitle('Valid Article');
        $article->elasticSchemaId = $this->schemaId;
        $article->author = 'Valid Author';
        $article->rating = 5;
        $article->insert();

        $loaded = Article::query()->where(['id' => $article->getId()])->one();
        $rules = $this->resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertTrue($result->isValid());
    }

    public function testValidationFailsWithInvalidRating(IntegrationTester $I): void
    {
        $I->wantTo('verify validation fails when rating is out of range');

        $article = new Article();
        $article->setTitle('Invalid Rating Article');
        $article->elasticSchemaId = $this->schemaId;
        $article->author = 'Author';
        $article->rating = 10; // Max is 5
        $article->insert();

        $loaded = Article::query()->where(['id' => $article->getId()])->one();
        $rules = $this->resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
    }

    public function testValidationFailsWithMissingRequiredAuthor(IntegrationTester $I): void
    {
        $I->wantTo('verify validation fails when required author is missing');

        $article = new Article();
        $article->setTitle('No Author Article');
        $article->elasticSchemaId = $this->schemaId;
        $article->insert();

        $loaded = Article::query()->where(['id' => $article->getId()])->one();
        $rules = $this->resolver->resolve($loaded);
        $validator = new Validator();
        $result = $validator->validate($loaded->getElasticValues(), $rules);

        $I->assertFalse($result->isValid());
    }

    // ==================== QUERY ====================

    public function testQueryWhereOnVirtualColumn(IntegrationTester $I): void
    {
        $I->wantTo('query articles using where on virtual column (author)');

        $article1 = new Article();
        $article1->setTitle('Article 1');
        $article1->elasticSchemaId = $this->schemaId;
        $article1->author = 'Alice';
        $article1->insert();

        $article2 = new Article();
        $article2->setTitle('Article 2');
        $article2->elasticSchemaId = $this->schemaId;
        $article2->author = 'Bob';
        $article2->insert();

        $results = Article::query()->where(['author' => 'Alice'])->all();

        $I->assertCount(1, $results);
        $I->assertEquals('Alice', $results[0]->author);
    }

    public function testQueryOrderByVirtualColumn(IntegrationTester $I): void
    {
        $I->wantTo('query articles ordered by virtual column (rating)');

        $article1 = new Article();
        $article1->setTitle('Low Rating');
        $article1->elasticSchemaId = $this->schemaId;
        $article1->author = 'Author 1';
        $article1->rating = 2;
        $article1->insert();

        $article2 = new Article();
        $article2->setTitle('High Rating');
        $article2->elasticSchemaId = $this->schemaId;
        $article2->author = 'Author 2';
        $article2->rating = 5;
        $article2->insert();

        $results = Article::query()->orderBy(['rating' => SORT_DESC])->all();

        $I->assertCount(2, $results);
        $I->assertEquals(5, $results[0]->rating);
        $I->assertEquals(2, $results[1]->rating);
    }

    // ==================== ELASTIC COLUMN NAME ====================

    public function testElasticColumnNameIsData(IntegrationTester $I): void
    {
        $I->wantTo('verify that the elastic column name is _data');

        $article = new Article();

        $I->assertEquals('_data', $article->elasticColumn());
    }
}
