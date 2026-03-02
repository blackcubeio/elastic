<?php

declare(strict_types=1);

/**
 * ElasticInterface.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Elastic;

use Swaggest\JsonSchema\Schema;

/**
 * Interface for models that use the Elastic data storage.
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
interface ElasticInterface
{
    public function elasticColumn(): string;

    public function elasticSchemaColumn(): string;

    public function getSchema(): ?Schema;

    public function getElasticAttributes(): array;

    public function getElasticValues(): array;

    public function getElasticLabels(): array;

    public function getElasticHints(): array;

    public function getElasticPlaceholders(): array;

    public function getPropertyLabel(string $property): string;

    public function getPropertyHint(string $property): string;

    public function getPropertyPlaceholder(string $property): string;

    public function populateRecord(array|object $row): static;
}