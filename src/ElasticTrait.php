<?php

declare(strict_types=1);

/**
 * ElasticTrait.php
 *
 * PHP version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Elastic;

use Blackcube\MagicCompose\Attributes\MagicCall;
use Blackcube\MagicCompose\Attributes\MagicExtend;
use Blackcube\MagicCompose\Attributes\MagicGetter;
use Blackcube\MagicCompose\Attributes\MagicIsset;
use Blackcube\MagicCompose\Attributes\MagicSetter;
use Blackcube\MagicCompose\Attributes\Priority;
use Blackcube\MagicCompose\Exceptions\MagicNotHandledException;
use Swaggest\JsonSchema\Schema;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

use function is_array;
use function json_decode;
use function json_encode;

/**
 * Trait to add dynamic schema-driven attributes to ActiveRecord models.
 *
 * The model using this trait must have:
 * - A FK column pointing to elasticSchemas table (default: 'elasticSchemaId')
 * - A TEXT/JSON column named '_extras' to store the JSON data
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
trait ElasticTrait
{
    /**
     * @var array<int, string|null> Cached schemas to avoid multiple DB calls
     */
    private static array $elasticSchemas = [];

    /**
     * @var array<string, mixed> Raw elastic values from _extras (all data, not filtered)
     */
    private array $elasticAttributeValues = [];

    /**
     * @var array<string, string> Defined attribute names from schema (lowercase => original)
     */
    private array $elasticAttributeNames = [];

    /**
     * @var bool Whether elastic schema has been parsed
     */
    private bool $elasticInitialized = false;

    /**
     * @var bool Whether _extras has been deserialized
     */
    private bool $elasticDeserialized = false;

    /**
     * @var bool Whether any elastic property has been explicitly set
     */
    private bool $elasticDirty = false;

    /**
     * @var string|null JSON storage column
     */
    protected ?string $elasticData = null;

    /**
     * @var bool System-level protection flag (toggled by populateRecord/refresh)
     */
    private bool $elasticSystemProtected = true;

    /**
     * @var bool Developer override flag (toggled by protectElastic)
     */
    private bool $elasticDevOverride = false;

    public function elasticColumn(): string
    {
        return '_extras';
    }

    public function elasticSchemaColumn(): string
    {
        return 'elasticSchemaId';
    }

    /**
     * Check if elastic data column is currently protected.
     */
    private function isElasticProtected(): bool
    {
        if ($this->elasticDevOverride) {
            return false;
        }
        return $this->elasticSystemProtected;
    }

    /**
     * Allow developer to temporarily disable elastic column protection.
     *
     * @param bool $protect True to enable protection, false to disable
     */
    public function protectElastic(bool $protect): void
    {
        $this->elasticDevOverride = !$protect;
    }

    /**
     * Internal method to toggle system protection.
     */
    private function protectElasticInternal(bool $protect): void
    {
        $this->elasticSystemProtected = $protect;
    }

    /**
     * @var mixed FK to elasticSchemas table
     */
    protected mixed $elasticSchemaId = null;

    /**
     * @var string Default JSON Schema when no schema is found
     */
    public string $defaultJsonSchema = '{"type": "object","properties": {},"required": []}';

    /**
     * Creates an ElasticQuery instance for query purpose.
     *
     * @param \Yiisoft\ActiveRecord\ActiveRecordInterface|string|null $modelClass
     * @return ElasticQuery The newly created ElasticQuery instance.
     */
    public static function query(\Yiisoft\ActiveRecord\ActiveRecordInterface|string|null $modelClass = null): ElasticQuery
    {
        return new ElasticQuery($modelClass ?? static::class);
    }

    /**
     * Get parsed Swaggest Schema object.
     */
    public function getSchema(): ?Schema
    {
        $this->ensureElasticInitialized();
        $jsonSchema = $this->loadJsonSchema();

        try {
            $decoded = json_decode($jsonSchema);
            if (!$decoded instanceof \stdClass) {
                return null;
            }
            return Schema::import($decoded);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getElasticAttributes(): array
    {
        $this->ensureElasticDeserialized();
        return array_keys($this->elasticAttributeNames);
    }

    /**
     * Get elastic attributes as an array for validation or external use.
     * Returns only values for properties defined in the current schema.
     *
     * @return array<string, mixed>
     */
    public function getElasticValues(): array
    {
        $this->ensureElasticDeserialized();
        $values = [];
        foreach ($this->elasticAttributeNames as $originalName) {
            $values[$originalName] = $this->elasticAttributeValues[$originalName] ?? null;
        }
        return $values;
    }

    private ?array $schemaMetadata = null;
    protected function getSchemaMetadata(): array
    {
        if ($this->schemaMetadata === null && ($schema = $this->getSchema()) !== null) {
            $this->schemaMetadata = [
                'labels' => [],
                'hints' => [],
                'placeholders' => [],
            ];
            $properties = $schema->getProperties();
            foreach ($properties as $name => $property) {
                if (isset($property->title)) {
                    $this->schemaMetadata['labels'][$name] = $property->title;
                }
                if (isset($property->description)) {
                    $this->schemaMetadata['hints'][$name] = $property->description;
                }
                if (isset($property->placeholder)) {
                    $this->schemaMetadata['placeholders'][$name] = $property->placeholder;
                }
            }
        }
        return $this->schemaMetadata;
    }

    /**
     * Get labels from JSON Schema title fields.
     *
     * @return array<string, string>
     */
    public function getElasticLabels(): array
    {
        $schemaMetadata = $this->getSchemaMetadata();
        return $schemaMetadata['labels'];
    }

    /**
     * Get hints from JSON Schema description fields.
     *
     * @return array<string, string>
     */
    public function getElasticHints(): array
    {
        $schemaMetadata = $this->getSchemaMetadata();
        return $schemaMetadata['hints'];
    }

    /**
     * Get placeholders from JSON Schema examples.
     *
     * @return array<string, string>
     */
    public function getElasticPlaceholders(): array
    {
        $schemaMetadata = $this->getSchemaMetadata();
        return $schemaMetadata['placeholders'];
    }

    /**
     * Get property label with fallback chain: model → elastic → generated.
     * Priority: 1. Model's getPropertyLabels() 2. Elastic JSON schema title 3. Generated from property name
     */
    public function getPropertyLabel(string $property): string
    {
        // 1. Check model's explicitly defined labels first
        if (method_exists($this, 'getPropertyLabels')) {
            $modelLabels = $this->getPropertyLabels();
            if (isset($modelLabels[$property])) {
                return $modelLabels[$property];
            }
        }

        // 2. Then check elastic labels from JSON schema
        $elasticLabels = $this->getElasticLabels();
        if (isset($elasticLabels[$property])) {
            return $elasticLabels[$property];
        }

        // 3. Finally fallback to generated label
        return $this->generatePropertyLabel($property);
    }

    /**
     * Get property hint with fallback chain: model → elastic → empty.
     * Priority: 1. Model's getPropertyHints() 2. Elastic JSON schema description 3. Empty string
     */
    public function getPropertyHint(string $property): string
    {
        // 1. Check model's explicitly defined hints first
        if (method_exists($this, 'getPropertyHints')) {
            $modelHints = $this->getPropertyHints();
            if (isset($modelHints[$property])) {
                return $modelHints[$property];
            }
        }

        // 2. Then check elastic hints from JSON schema
        $elasticHints = $this->getElasticHints();
        if (isset($elasticHints[$property])) {
            return $elasticHints[$property];
        }

        // 3. Finally fallback to empty string
        return '';
    }

    /**
     * Get property placeholder with fallback chain: model → elastic → empty.
     * Priority: 1. Model's getPropertyPlaceholders() 2. Elastic JSON schema examples 3. Empty string
     */
    public function getPropertyPlaceholder(string $property): string
    {
        // 1. Check model's explicitly defined placeholders first
        if (method_exists($this, 'getPropertyPlaceholders')) {
            $modelPlaceholders = $this->getPropertyPlaceholders();
            if (isset($modelPlaceholders[$property])) {
                return $modelPlaceholders[$property];
            }
        }

        // 2. Then check elastic placeholders from JSON schema
        $elasticPlaceholders = $this->getElasticPlaceholders();
        if (isset($elasticPlaceholders[$property])) {
            return $elasticPlaceholders[$property];
        }

        // 3. Finally fallback to empty string
        return '';
    }

    /**
     * Generate label from property name (fallback).
     */
    private function generatePropertyLabel(string $property): string
    {
        return ucfirst(
            preg_replace(
                '/(?<![A-Z])[A-Z]/',
                ' \0',
                $property
            ) ?? $property
        );
    }

    private function setElasticData(string|array|null $extras = null): void
    {
        if ($extras === null || is_string($extras)) {
            $this->elasticData = $extras;
            $this->elasticDeserialized = false;
            $this->elasticDirty = false;
            return;
        }
        // Array passed: set values directly
        $this->ensureElasticDeserialized();
        foreach ($extras as $name => $value) {
            $this->elasticAttributeValues[$name] = $value;
            $this->elasticDirty = true;
        }
    }

    /**
     * Resolve property name case-insensitively.
     * @return string|null Original property name or null if not found
     */
    private function resolveElasticProperty(string $name): ?string
    {
        return $this->elasticAttributeNames[strtolower($name)] ?? null;
    }

    // ========================================
    // MagicCompose handlers
    // ========================================

    /**
     * Handle getting elastic property values.
     */
    #[MagicGetter(Priority::NORMAL)]
    protected function elasticGet(string $name): mixed
    {
        if ($name === $this->elasticColumn()) {
            $this->ensureElasticDeserialized();
            return $this->serializeElastic();
        }
        if ($name === $this->elasticSchemaColumn()) {
            return $this->elasticSchemaId;
        }

        $this->ensureElasticDeserialized();
        $resolvedName = $this->resolveElasticProperty($name);
        if ($resolvedName !== null) {
            return $this->elasticAttributeValues[$resolvedName] ?? null;
        }

        throw new MagicNotHandledException();
    }

    /**
     * Handle setting elastic property values.
     */
    #[MagicSetter(Priority::NORMAL)]
    protected function elasticSet(string $name, mixed $value): void
    {
        if ($name === $this->elasticColumn()) {
            if ($this->isElasticProtected()) {
                throw new \Error(
                    sprintf('Cannot set read-only elastic column %s::$%s. Use individual properties instead.', static::class, $name)
                );
            }
            $this->setElasticData($value);
            return;
        }
        if ($name === $this->elasticSchemaColumn()) {
            $this->setElasticSchemaIdInternal($value);
            return;
        }

        // Ensure we have loaded the existing data first
        $this->ensureElasticDeserialized();

        // Check if property exists in current schema
        $this->ensureElasticInitialized();
        $resolvedName = $this->resolveElasticProperty($name);
        if ($resolvedName !== null) {
            // Set value in raw values array and mark dirty
            $this->elasticAttributeValues[$resolvedName] = $value;
            $this->elasticDirty = true;
            return;
        }

        throw new MagicNotHandledException();
    }

    /**
     * Handle checking if elastic property is set.
     */
    #[MagicIsset(Priority::NORMAL)]
    protected function elasticIsset(string $name): bool
    {
        if ($name === $this->elasticColumn()) {
            return $this->elasticData !== null;
        }
        if ($name === $this->elasticSchemaColumn()) {
            return $this->elasticSchemaId !== null;
        }

        $this->ensureElasticDeserialized();
        $resolvedName = $this->resolveElasticProperty($name);
        if ($resolvedName !== null) {
            return isset($this->elasticAttributeValues[$resolvedName]);
        }

        throw new MagicNotHandledException();
    }

    /**
     * Handle magic method calls for elastic properties.
     */
    #[MagicCall(Priority::NORMAL)]
    protected function elasticCall(string $name, array $arguments): mixed
    {
        // Handle getPropertyName()
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            if ($name === 'get' . ucfirst($this->elasticColumn())) {
                $this->ensureElasticDeserialized();
                return $this->serializeElastic();
            }
            if ($name === 'get' . ucfirst($this->elasticSchemaColumn())) {
                return $this->elasticSchemaId;
            }
            $property = lcfirst(substr($name, 3));
            $this->ensureElasticDeserialized();
            $resolvedName = $this->resolveElasticProperty($property);
            if ($resolvedName !== null) {
                return $this->elasticAttributeValues[$resolvedName] ?? null;
            }
        }

        // Handle setPropertyName($value)
        if (str_starts_with($name, 'set') && strlen($name) > 3) {
            if ($name === 'set' . ucfirst($this->elasticColumn())) {
                if ($this->isElasticProtected()) {
                    throw new \Error(
                        sprintf('Cannot set read-only elastic column %s::$%s. Use individual properties instead.', static::class, $this->elasticColumn())
                    );
                }
                if (!is_string($arguments[0])) {
                    $this->elasticData = json_encode($arguments[0]);
                } else {
                    $this->elasticData = $arguments[0];
                }
                $this->elasticDeserialized = false;
                return null;
            }
            if ($name === 'set' . ucfirst($this->elasticSchemaColumn())) {
                $this->setElasticSchemaIdInternal($arguments[0] ?? null);
                return null;
            }
            $property = lcfirst(substr($name, 3));
            $this->ensureElasticDeserialized();
            $this->ensureElasticInitialized();
            $resolvedName = $this->resolveElasticProperty($property);
            if ($resolvedName !== null) {
                $this->elasticAttributeValues[$resolvedName] = $arguments[0] ?? null;
                $this->elasticDirty = true;
                return null;
            }
        }

        throw new MagicNotHandledException();
    }

    // ========================================
    // MagicExtend handlers for AR methods
    // ========================================

    /**
     * Override to include elastic columns in property values for ActiveRecord save.
     */
    #[MagicExtend('propertyValuesInternal', Priority::NORMAL)]
    protected function elasticPropertyValues(): array
    {
        $values = $this->next();
        $this->ensureElasticDeserialized();
        $values[$this->elasticColumn()] = $this->serializeElastic();
        $values[$this->elasticSchemaColumn()] = $this->elasticSchemaId;
        return $values;
    }

    /**
     * Override to populate elastic properties with protection check.
     */
    #[MagicExtend('populateProperty', Priority::NORMAL)]
    protected function elasticPopulateProperty(string $name, mixed $value): void
    {
        if ($name === $this->elasticColumn()) {
            if ($this->isElasticProtected()) {
                throw new \Error(
                    sprintf('Cannot set read-only elastic column %s::$%s. Use individual properties instead.', static::class, $name)
                );
            }
            $this->setElasticData($value);
            return;
        }
        if ($name === $this->elasticSchemaColumn()) {
            $this->setElasticSchemaIdInternal($value);
            return;
        }

        $this->next($name, $value);
    }

    /**
     * Override to temporarily disable protection during DB load.
     */
    #[MagicExtend('populateRecord', Priority::NORMAL)]
    protected function elasticPopulateRecord(array|object $row): static
    {
        $this->protectElasticInternal(false);
        $result = $this->next($row);
        $this->protectElasticInternal(true);
        return $result;
    }

    /**
     * Override to temporarily disable protection during refresh.
     */
    #[MagicExtend('refreshInternal', Priority::NORMAL)]
    protected function elasticRefreshInternal(array|ActiveRecordInterface|null $record): bool
    {
        $this->protectElasticInternal(false);
        $result = $this->next($record);
        $this->protectElasticInternal(true);
        return $result;
    }

    // ========================================
    // Internal methods
    // ========================================

    private function setElasticSchemaIdInternal(mixed $elasticSchemaId): void
    {
        $this->elasticSchemaId = $elasticSchemaId;
        $this->elasticInitialized = false;
        // Never touch elasticData, elasticAttributes or elasticDeserialized here
    }

    /**
     * Clear static schema cache.
     * Useful for testing or when schemas are modified at runtime.
     */
    public static function clearSchemaCache(): void
    {
        self::$elasticSchemas = [];
    }

    /**
     * Ensure elastic is initialized based on current schemaId.
     */
    private function ensureElasticInitialized(): void
    {
        if ($this->elasticInitialized) {
            return;
        }

        $jsonSchema = $this->loadJsonSchema();
        $this->parseJsonSchema($jsonSchema);
        $this->elasticInitialized = true;
    }

    /**
     * Load JSON Schema from database with caching.
     */
    private function loadJsonSchema(): string
    {
        if ($this->elasticSchemaId === null) {
            return $this->defaultJsonSchema;
        }

        if (!isset(self::$elasticSchemas[$this->elasticSchemaId])) {
            $this->preloadAllSchemas();
        }

        return self::$elasticSchemas[$this->elasticSchemaId] ?? $this->defaultJsonSchema;
    }

    /**
     * Load all schemas into cache in one query.
     */
    private function preloadAllSchemas(): void
    {
        if (self::$elasticSchemas !== []) {
            return;
        }

        $schemas = ElasticSchema::query()->all();
        foreach ($schemas as $schema) {
            self::$elasticSchemas[$schema->getId()] = $schema->getSchema();
        }
    }

    /**
     * Parse JSON Schema and extract attribute names.
     * Only populates elasticAttributeNames, not values.
     */
    private function parseJsonSchema(string $jsonSchema): void
    {
        // Reset attribute names for new schema
        $this->elasticAttributeNames = [];

        try {
            $decoded = json_decode($jsonSchema);
            if (!$decoded instanceof \stdClass) {
                return;
            }

            $schema = Schema::import($decoded);
            $properties = $schema->getProperties();

            if ($properties === null) {
                return;
            }

            foreach ($properties as $name => $property) {
                $this->elasticAttributeNames[strtolower($name)] = $name;
            }
        } catch (\Throwable) {
            // Ignore schema parsing errors
        }
    }

    /**
     * Ensure _extras is deserialized (lazy loading).
     * Loads ALL data from _extras into elasticAttributeValues (not filtered by schema).
     * Also ensures schema is initialized.
     */
    private function ensureElasticDeserialized(): void
    {
        if ($this->elasticDeserialized) {
            return;
        }

        $this->ensureElasticInitialized();

        try {
            $extras = $this->elasticData ?? null;
            if ($extras !== null && $extras !== '') {
                $decoded = is_array($extras) ? $extras : json_decode($extras, true);
                if (is_array($decoded)) {
                    // Load all data directly (not filtered by schema)
                    foreach ($decoded as $name => $value) {
                        $this->elasticAttributeValues[$name] = $value;
                    }
                }
            }
        } catch (\Exception) {
            // Ignore decode errors
        }

        $this->elasticDeserialized = true;
    }

    /**
     * Serialize elastic attributes to JSON string.
     * If no elastic property was explicitly set, preserve original _extras data.
     * If dirty AND elasticSchemaId is set, filter by schema's property names.
     * If dirty AND elasticSchemaId is null, return all values (no filtering).
     */
    private function serializeElastic(): ?string
    {
        // If no elastic property was explicitly set, preserve original _extras as-is
        if (!$this->elasticDirty) {
            return $this->elasticData;
        }

        // No schema = no filtering, return all values
        if ($this->elasticSchemaId === null) {
            return json_encode($this->elasticAttributeValues);
        }

        // Make sure schema is initialized to get property names
        $this->ensureElasticInitialized();

        // Filter values by current schema's property names
        $filtered = [];
        foreach ($this->elasticAttributeNames as $originalName) {
            if (array_key_exists($originalName, $this->elasticAttributeValues)) {
                $filtered[$originalName] = $this->elasticAttributeValues[$originalName];
            }
        }

        return json_encode($filtered);
    }

}
