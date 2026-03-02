# Blackcube Elastic

> **⚠️ Blackcube Warning**
>
> This is not EAV. If you want Entity-Attribute-Value with JOIN hell, look elsewhere.
>
> Elastic stores JSON, validates with JSON Schema, and lets you query virtual columns.
> You manipulate PHP properties. You never see the JSON.

Dynamic model attributes from JSON Schema.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/elastic.svg)](https://packagist.org/packages/blackcube/elastic)
[![Warning](https://img.shields.io/badge/Blackcube-Warning-orange)](BLACKCUBE_WARNING.md)

## Installation

```bash
composer require blackcube/elastic
```

## Requirements

- MySQL/MariaDB (for JSON column support)

## Why Elastic?

| Approach | Problem |
|----------|---------|
| One table per type | 20 types = 20 tables, duplicated code |
| Catch-all columns | "field23 is what again?" |
| Raw HTML | Not validatable, not queryable, XSS |
| EAV | JOIN on JOIN on JOIN |
| **Elastic** | None of the above |

**You manipulate PHP properties.** Elastic handles JSON underneath.

**Validation is automatic.** JSON Schema → Yii3 Validator rules.

**Queries are transparent.** `->where(['virtualColumn' => 'value'])` just works.

**Evolution without migration.** Add a field to the schema. No SQL migration needed.

## How It Works

### Storage

| Column | Purpose |
|--------|---------|
| `elasticSchemaId` | FK to `elasticSchemas` table |
| `_extras` | JSON data storage |

The developer never touches `_extras` directly. Properties are accessed like regular PHP properties.

### Column names can be tuned

Override these methods in your model to use different column names:

```php
public function elasticColumn(): string       { return 'data'; }        // Default: '_extras'
public function elasticSchemaColumn(): string { return 'schemaId'; }    // Default: 'elasticSchemaId'
```

## Database Setup

### 1. Create the schemas table

Run the provided migration:

```php
use Blackcube\Elastic\Migrations\M000000000000CreateElasticSchemas;

$migration = new M000000000000CreateElasticSchemas();
$migration->up($builder);
```

### 2. Add columns to your table

```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    elasticSchemaId INT,
    _extras TEXT,
    FOREIGN KEY (elasticSchemaId) REFERENCES elasticSchemas(id)
);
```

## Quick Start

### 1. Create a JSON Schema

```php
use Blackcube\Elastic\ElasticSchema;

$schema = new ElasticSchema();
$schema->setName('ProductAttributes');
$schema->setSchema(json_encode([
    'type' => 'object',
    'properties' => [
        'sku' => ['type' => 'string', 'minLength' => 3],
        'price' => ['type' => 'number', 'minimum' => 0],
        'inStock' => ['type' => 'boolean'],
    ],
    'required' => ['sku'],
]));
$schema->save();
```

### 2. Create your ActiveRecord model

```php
<?php

declare(strict_types=1);

namespace App\Model;

use Blackcube\Elastic\ElasticInterface;
use Blackcube\Elastic\ElasticTrait;
use Blackcube\MagicCompose\ActiveRecord\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

class Product extends ActiveRecord implements ElasticInterface
{
    use MagicComposeActiveRecordTrait;
    use ElasticTrait;

    protected string $name = '';

    public function tableName(): string
    {
        return 'products';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
```

## Usage

### Working with dynamic attributes

```php
// Create — properties are PHP, not JSON
$product = new Product();
$product->setName('Laptop');
$product->elasticSchemaId = $schemaId;
$product->sku = 'LAP-001';      // Virtual property
$product->price = 999.99;        // Virtual property
$product->inStock = true;        // Virtual property
$product->insert();

// Read — same thing
$loaded = Product::query()->where(['id' => $product->id])->one();
echo $loaded->sku;       // 'LAP-001'
echo $loaded->price;     // 999.99
echo $loaded->inStock;   // true

// Update — still PHP
$loaded->price = 899.99;
$loaded->update();
```

### Querying virtual columns

`ElasticQuery` transforms virtual columns to `JSON_VALUE()` expressions automatically:

```php
// Filter by virtual column
$products = Product::query()
    ->where(['sku' => 'LAP-001'])
    ->all();

// Multiple conditions
$products = Product::query()
    ->where(['inStock' => true])
    ->andWhere(['>', 'price', 500])
    ->all();

// Order by virtual column
$products = Product::query()
    ->orderBy(['price' => SORT_DESC])
    ->all();

// Mix real and virtual columns
$products = Product::query()
    ->where(['name' => 'Laptop', 'inStock' => true])
    ->orderBy(['price' => SORT_ASC])
    ->all();
```

### Validating elastic attributes

```php
use Blackcube\Elastic\Validator\ElasticRuleResolver;
use Yiisoft\Validator\Validator;

$resolver = new ElasticRuleResolver();
$rules = $resolver->resolve($product);

$validator = new Validator();
$result = $validator->validate($product->getElasticValues(), $rules);

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage();
    }
}
```

### Supported JSON Schema features

| JSON Schema | Yii3 Validator Rule |
|-------------|---------------------|
| `type: string` | `StringValue` |
| `type: integer` | `Integer` |
| `type: number` | `Number` |
| `type: boolean` | `BooleanValue` |
| `minimum`, `maximum` | `Integer`/`Number` with constraints |
| `minLength`, `maxLength` | `Length` |
| `pattern` | `Regex` |
| `enum` | `In` |
| `format: email` | `Email` |
| `format: idn-email` | `Email` with IDN |
| `format: url` | `Url` |
| `format: ipv4`, `format: ipv6` | `Ip` |
| `required` | `Required` |

### Labels, hints, placeholders from schema

JSON Schema metadata is extracted automatically:

| JSON Schema field | Method |
|-------------------|--------|
| `title` | `getPropertyLabel($property)` |
| `description` | `getPropertyHint($property)` |
| `placeholder` | `getPropertyPlaceholder($property)` |

## Let's be honest

**Performance on complex queries**

`JSON_VALUE()` is slower than a native indexed column. Filtering 100,000 rows on a JSON field will be slow.

**In practice:** A CMS with a few thousand contents? No problem. A search engine on millions of rows? Use Elasticsearch or a real column.

**No foreign keys in JSON**

You can't JOIN on a JSON value. If you need relations, use real columns.

**One-way compatibility**

Adding optional fields: ✓ works, old data returns `null`.

Removing fields: data stays in database, but property is no longer accessible.

## Rules

1. **Never modify `_extras` directly** — use dynamic properties
2. **Link your model to a schema** — set `elasticSchemaId` before using elastic attributes
3. **Use `ElasticQuery`** — the `query()` method returns it automatically via the trait

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>