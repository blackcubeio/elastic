<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\ElasticTrait;

use Blackcube\Elastic\ElasticSchema;
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;
use Blackcube\Elastic\Tests\Support\ElasticTraitTester;
use Blackcube\Elastic\Tests\Support\MysqlHelper;
use Blackcube\Elastic\Tests\Support\TestModel;
use Blackcube\Elastic\Tests\Support\TestModelWithLabels;
use Blackcube\Elastic\Tests\Support\TestModelWithMagicParent;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

final class ElasticTraitCest
{
    private int $schemaId;
    private int $schemaWithMetadataId;

    public function _before(ElasticTraitTester $I): void
    {
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

        // Create a basic schema
        $schema = new ElasticSchema();
        $schema->setName('TestSchema');
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'description' => ['type' => 'string'],
                'price' => ['type' => 'number'],
                'active' => ['type' => 'boolean'],
            ],
        ]));
        $schema->save();
        $this->schemaId = $schema->getId();

        // Create a schema with metadata (title, description, examples)
        $schemaWithMetadata = new ElasticSchema();
        $schemaWithMetadata->setName('TestSchemaWithMetadata');
        $schemaWithMetadata->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'subject' => [
                    'type' => 'string',
                    'title' => 'Message subject',
                    'description' => 'Choose a relevant subject',
                    'placeholder' => 'Information request',
                ],
                'message' => [
                    'type' => 'string',
                    'title' => 'Your message',
                    'description' => 'Describe your request in detail',
                    'placeholder' => 'Hello, I would like...',
                ],
                'priority' => [
                    'type' => 'integer',
                    'title' => 'Priority',
                    // No description
                    'placeholder' => '1',
                ],
                'internal' => [
                    'type' => 'boolean',
                    // No title, no description, no examples
                ],
            ],
        ]));
        $schemaWithMetadata->save();
        $this->schemaWithMetadataId = $schemaWithMetadata->getId();
    }

    public function testModelWithoutSchemaHasEmptyExtras(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->setTitle('No Schema');

        $extras = $model->getElasticValues();

        $I->assertIsArray($extras);
        $I->assertEmpty($extras);
    }

    public function testModelWithSchemaLoadsAttributes(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->setTitle('With Schema');
        $model->elasticSchemaId = $this->schemaId;

        $extras = $model->getElasticValues();

        $I->assertIsArray($extras);
        $I->assertArrayHasKey('description', $extras);
        $I->assertArrayHasKey('price', $extras);
        $I->assertArrayHasKey('active', $extras);
    }

    public function testSetExtrasOnlyAcceptsSchemaAttributes(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $model->protectElastic(false);
        $model->_extras = json_encode([
            'description' => 'Test',
            'price' => 19.99,
            'unknown' => 'ignored',
        ]);

        $extras = $model->getElasticValues();

        $I->assertEquals('Test', $extras['description']);
        $I->assertEquals(19.99, $extras['price']);
        $I->assertArrayNotHasKey('unknown', $extras);
    }

    public function testElasticSchemaIdCanBeSet(ElasticTraitTester $I): void
    {
        $model = new TestModel();

        $I->assertNull($model->elasticSchemaId);

        $model->elasticSchemaId = $this->schemaId;

        $I->assertEquals($this->schemaId, $model->elasticSchemaId);
    }

    public function testSetSchemaPropertyViaMagicSet(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        // Set via $model->propertyName = 'value'
        $model->description = 'Test description';

        $extras = $model->getElasticValues();
        $I->assertEquals('Test description', $extras['description']);
    }

    public function testSetSchemaPropertyViaSetExtras(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        // Set via $model->set_extras(['propertyName' => 'value']) - requires protection disabled
        $model->protectElastic(false);
        $model->set_extras(['description' => 'Via set_extras']);

        $extras = $model->getElasticValues();
        $I->assertEquals('Via set_extras', $extras['description']);
    }

    public function testSetSchemaPropertyViaExtrasArray(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        // Set via $model->_extras = json_encode(['propertyName' => 'value']) - requires protection disabled
        $model->protectElastic(false);
        $model->_extras = json_encode(['description' => 'Via extras array']);

        $extras = $model->getElasticValues();
        $I->assertEquals('Via extras array', $extras['description']);
    }

    public function testGetSchemaPropertyViaMagicGet(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;
        $model->protectElastic(false);
        $model->_extras = json_encode(['description' => 'Test magic get']);

        // Get via $model->propertyName
        $I->assertEquals('Test magic get', $model->description);
    }

    public function testGetSchemaPropertyViaGetterMethod(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;
        $model->protectElastic(false);
        $model->_extras = json_encode(['description' => 'Test getter method']);

        // Get via $model->getPropertyName()
        $I->assertEquals('Test getter method', $model->getDescription());
    }

    public function testSetSchemaPropertyViaSetterMethod(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        // Set via $model->setPropertyName($value)
        $model->setDescription('Via setter method');

        $I->assertEquals('Via setter method', $model->description);
        $I->assertEquals('Via setter method', $model->getDescription());
    }

    public function testPropertyAccessIsCaseInsensitive(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        // Set via different case variations
        $model->setDeScrIpTiOn('Case insensitive value');

        // Get via different case variations - all should return same value
        $I->assertEquals('Case insensitive value', $model->description);
        $I->assertEquals('Case insensitive value', $model->DESCRIPTION);
        $I->assertEquals('Case insensitive value', $model->Description);
        $I->assertEquals('Case insensitive value', $model->getDescription());
        $I->assertEquals('Case insensitive value', $model->getDESCRIPTION());

        // extras should use original case from schema
        $extras = $model->getElasticValues();
        $I->assertArrayHasKey('description', $extras);
        $I->assertEquals('Case insensitive value', $extras['description']);
    }

    public function testSaveModelWithElasticProperties(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->setTitle('Test Save');
        $model->elasticSchemaId = $this->schemaId;
        $model->description = 'Saved description';
        $model->price = 29.99;
        $model->active = true;

        $model->insert();

        $I->assertNotNull($model->getId());

        // Verify elastic properties are saved
        $extras = $model->getElasticValues();
        $I->assertEquals('Saved description', $extras['description']);
        $I->assertEquals(29.99, $extras['price']);
        $I->assertTrue($extras['active']);
    }

    public function testGetUnknownPropertyThrowsError(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $I->expectThrowable(\Throwable::class, function () use ($model) {
            $value = $model->unknownProperty;
        });
    }

    public function testSetUnknownPropertyThrowsError(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $I->expectThrowable(\Throwable::class, function () use ($model) {
            $model->unknownProperty = 'value';
        });
    }

    public function testCallUnknownMethodThrowsError(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $I->expectThrowable(\Throwable::class, function () use ($model) {
            $model->getUnknownProperty();
        });
    }

    public function testParentMagicGetIsCalled(ElasticTraitTester $I): void
    {
        $model = new TestModelWithMagicParent();
        $model->elasticSchemaId = $this->schemaId;

        // Set via parent's __set (unknownProperty is not in schema)
        $model->unknownProperty = 'parent value';

        // Get via parent's __get
        $I->assertEquals('parent value', $model->unknownProperty);
    }

    public function testParentMagicIssetIsCalled(ElasticTraitTester $I): void
    {
        $model = new TestModelWithMagicParent();
        $model->elasticSchemaId = $this->schemaId;

        $I->assertFalse(isset($model->unknownProperty));

        $model->unknownProperty = 'value';

        $I->assertTrue(isset($model->unknownProperty));
    }

    public function testParentMagicCallIsCalled(ElasticTraitTester $I): void
    {
        $model = new TestModelWithMagicParent();
        $model->elasticSchemaId = $this->schemaId;

        // getMagicTest() is handled by parent's __call
        $I->assertEquals('magic_Test', $model->getMagicTest());
    }

    public function testLoadModelWithElasticProperties(ElasticTraitTester $I): void
    {
        // Save a model first
        $model = new TestModel();
        $model->setTitle('Test Load');
        $model->elasticSchemaId = $this->schemaId;
        $model->description = 'Loaded description';
        $model->price = 49.99;
        $model->active = false;
        $model->insert();

        $savedId = $model->getId();

        // Load from DB
        $loaded = TestModel::query()->where(['id' => $savedId])->one();

        $I->assertNotNull($loaded);
        $I->assertEquals('Test Load', $loaded->getTitle());
        $I->assertEquals($this->schemaId, $loaded->elasticSchemaId);
        $I->assertEquals('Loaded description', $loaded->description);
        $I->assertEquals(49.99, $loaded->price);
        $I->assertFalse($loaded->active);
    }

    public function testSetExtrasColumnThrowsWhenProtected(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $I->expectThrowable(\Error::class, function () use ($model) {
            $model->_extras = json_encode(['description' => 'Should fail']);
        });
    }

    public function testProtectElasticAllowsWriteWhenDisabled(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $model->protectElastic(false);
        $model->_extras = json_encode(['description' => 'Should work']);

        $I->assertEquals('Should work', $model->description);
    }

    public function testGetElasticLabelsReturnsEmptyWithoutSchema(ElasticTraitTester $I): void
    {
        $model = new TestModel();

        $labels = $model->getElasticLabels();

        $I->assertIsArray($labels);
        $I->assertEmpty($labels);
    }

    public function testGetElasticLabelsReturnsEmptyForSchemaWithoutTitles(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $labels = $model->getElasticLabels();

        $I->assertIsArray($labels);
        $I->assertEmpty($labels);
    }

    public function testGetElasticLabelsReturnsTitlesFromSchema(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        $labels = $model->getElasticLabels();

        $I->assertIsArray($labels);
        $I->assertArrayHasKey('subject', $labels);
        $I->assertArrayHasKey('message', $labels);
        $I->assertArrayHasKey('priority', $labels);
        $I->assertArrayNotHasKey('internal', $labels);

        $I->assertEquals('Message subject', $labels['subject']);
        $I->assertEquals('Your message', $labels['message']);
        $I->assertEquals('Priority', $labels['priority']);
    }

    public function testGetElasticHintsReturnsEmptyWithoutSchema(ElasticTraitTester $I): void
    {
        $model = new TestModel();

        $hints = $model->getElasticHints();

        $I->assertIsArray($hints);
        $I->assertEmpty($hints);
    }

    public function testGetElasticHintsReturnsEmptyForSchemaWithoutDescriptions(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $hints = $model->getElasticHints();

        $I->assertIsArray($hints);
        $I->assertEmpty($hints);
    }

    public function testGetElasticHintsReturnsDescriptionsFromSchema(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        $hints = $model->getElasticHints();

        $I->assertIsArray($hints);
        $I->assertArrayHasKey('subject', $hints);
        $I->assertArrayHasKey('message', $hints);
        $I->assertArrayNotHasKey('priority', $hints);
        $I->assertArrayNotHasKey('internal', $hints);

        $I->assertEquals('Choose a relevant subject', $hints['subject']);
        $I->assertEquals('Describe your request in detail', $hints['message']);
    }

    public function testGetElasticPlaceholdersReturnsEmptyWithoutSchema(ElasticTraitTester $I): void
    {
        $model = new TestModel();

        $placeholders = $model->getElasticPlaceholders();

        $I->assertIsArray($placeholders);
        $I->assertEmpty($placeholders);
    }

    public function testGetElasticPlaceholdersReturnsEmptyForSchemaWithoutPlaceholder(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaId;

        $placeholders = $model->getElasticPlaceholders();

        $I->assertIsArray($placeholders);
        $I->assertEmpty($placeholders);
    }

    public function testGetElasticPlaceholdersReturnsFirstExampleFromSchema(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        $placeholders = $model->getElasticPlaceholders();

        $I->assertIsArray($placeholders);
        $I->assertArrayHasKey('subject', $placeholders);
        $I->assertArrayHasKey('message', $placeholders);
        $I->assertArrayHasKey('priority', $placeholders);
        $I->assertArrayNotHasKey('internal', $placeholders);

        $I->assertEquals('Information request', $placeholders['subject']);
        $I->assertEquals('Hello, I would like...', $placeholders['message']);
        $I->assertEquals('1', $placeholders['priority']);
    }

    public function testGetPropertyLabelReturnsElasticLabel(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        $I->assertEquals('Message subject', $model->getPropertyLabel('subject'));
        $I->assertEquals('Your message', $model->getPropertyLabel('message'));
        $I->assertEquals('Priority', $model->getPropertyLabel('priority'));
    }

    public function testGetPropertyLabelFallsBackToGenerated(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        // 'internal' has no title in schema, should generate from property name
        $I->assertEquals('Internal', $model->getPropertyLabel('internal'));
    }

    public function testGetPropertyHintReturnsElasticHint(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        $I->assertEquals('Choose a relevant subject', $model->getPropertyHint('subject'));
        $I->assertEquals('Describe your request in detail', $model->getPropertyHint('message'));
    }

    public function testGetPropertyHintReturnsEmptyForMissingHint(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        // 'priority' has no description in schema
        $I->assertEquals('', $model->getPropertyHint('priority'));
    }

    public function testGetPropertyPlaceholderReturnsElasticPlaceholder(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        $I->assertEquals('Information request', $model->getPropertyPlaceholder('subject'));
        $I->assertEquals('Hello, I would like...', $model->getPropertyPlaceholder('message'));
        $I->assertEquals('1', $model->getPropertyPlaceholder('priority'));
    }

    public function testGetPropertyPlaceholderReturnsEmptyForMissingPlaceholder(ElasticTraitTester $I): void
    {
        $model = new TestModel();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        // 'internal' has no examples in schema
        $I->assertEquals('', $model->getPropertyPlaceholder('internal'));
    }

    // ========================================
    // File format property test (debug scenario)
    // ========================================

    public function testLoadModelWithFileFormatProperty(ElasticTraitTester $I): void
    {
        // Create schema with file format property (exactly as in production)
        $fileSchema = new ElasticSchema();
        $fileSchema->setName('FileTestSchema');
        $fileSchema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'lang' => ['type' => 'string', 'title' => 'Contenu associé'],
                'surtitle' => ['type' => 'string', 'title' => 'Surtitre'],
                'title' => ['type' => 'string', 'title' => 'Titre'],
                'description' => ['type' => 'string', 'format' => 'wysiwyg', 'title' => 'Description'],
                'image' => ['type' => 'string', 'format' => 'file', 'fileType' => 'png,jpg,svg', 'title' => 'Image'],
                'imageAlt' => ['type' => 'string', 'title' => 'Alternate'],
                'cta' => ['type' => 'string', 'title' => 'Cta'],
                'ctaRoute' => ['type' => 'string', 'title' => 'Cible'],
                'ctaUrl' => ['type' => 'string', 'title' => 'Cible (URL)'],
            ],
            'required' => ['title', 'description'],
        ]));
        $fileSchema->save();
        $fileSchemaId = $fileSchema->getId();

        // Insert model with EXACT _extras data from production
        $extrasData = json_encode([
            'lang' => 'azertyu yolo2',
            'surtitle' => '',
            'title' => 'wxcvbn,n',
            'description' => 'azertyu',
            'image' => '@blfs/tags/3/blocs/16/bcr.png',
            'imageAlt' => '',
            'cta' => '',
            'ctaRoute' => '',
            'ctaUrl' => 'qsdfghj',
        ]);

        $model = new TestModel();
        $model->setTitle('File Test');
        $model->elasticSchemaId = $fileSchemaId;
        $model->protectElastic(false);
        $model->_extras = $extrasData;
        $model->insert();

        $savedId = $model->getId();

        // Load from DB (fresh instance)
        TestModel::clearSchemaCache();
        $loaded = TestModel::query()->where(['id' => $savedId])->one();

        // CRITICAL ASSERTIONS - must match EXACTLY
        $I->assertNotNull($loaded);
        $I->assertSame('@blfs/tags/3/blocs/16/bcr.png', $loaded->image);
        $I->assertSame('wxcvbn,n', $loaded->title);
        $I->assertSame('azertyu yolo2', $loaded->lang);
        $I->assertSame('qsdfghj', $loaded->ctaUrl);

        // Also test getElasticValues
        $values = $loaded->getElasticValues();
        $I->assertSame('@blfs/tags/3/blocs/16/bcr.png', $values['image']);
    }

    // ========================================
    // Priority tests: model > elastic > generated
    // ========================================

    public function testModelLabelTakesPriorityOverElasticLabel(ElasticTraitTester $I): void
    {
        $model = new TestModelWithLabels();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        // 'subject' is defined in both model (getPropertyLabels) and elastic schema (title)
        // Model should win: "Model-defined label" vs "Message subject"
        $I->assertEquals('Model-defined label', $model->getPropertyLabel('subject'));

        // 'message' is only in elastic schema, should use elastic
        $I->assertEquals('Your message', $model->getPropertyLabel('message'));

        // 'internal' has no title anywhere, should generate from property name
        $I->assertEquals('Internal', $model->getPropertyLabel('internal'));
    }

    public function testModelHintTakesPriorityOverElasticHint(ElasticTraitTester $I): void
    {
        $model = new TestModelWithLabels();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        // 'subject' is defined in both model and elastic schema
        // Model should win
        $I->assertEquals('Model-defined hint', $model->getPropertyHint('subject'));

        // 'message' is only in elastic schema, should use elastic
        $I->assertEquals('Describe your request in detail', $model->getPropertyHint('message'));

        // 'priority' has no description anywhere, should return empty
        $I->assertEquals('', $model->getPropertyHint('priority'));
    }

    public function testModelPlaceholderTakesPriorityOverElasticPlaceholder(ElasticTraitTester $I): void
    {
        $model = new TestModelWithLabels();
        $model->elasticSchemaId = $this->schemaWithMetadataId;

        // 'subject' is defined in both model and elastic schema
        // Model should win
        $I->assertEquals('Model-defined placeholder', $model->getPropertyPlaceholder('subject'));

        // 'message' is only in elastic schema, should use elastic
        $I->assertEquals('Hello, I would like...', $model->getPropertyPlaceholder('message'));

        // 'internal' has no examples anywhere, should return empty
        $I->assertEquals('', $model->getPropertyPlaceholder('internal'));
    }
}
