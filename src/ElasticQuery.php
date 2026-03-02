<?php

declare(strict_types=1);

/**
 * ElasticQuery.php
 *
 * PHP version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Elastic;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Expression\ExpressionInterface;

use function array_keys;
use function in_array;
use function is_array;
use function is_int;
use function preg_match;
use function strtoupper;

/**
 * ElasticQuery provides support for querying virtual columns stored in JSON.
 *
 * This ActiveQuery automatically converts references to elastic (virtual) columns
 * in WHERE and ORDER BY clauses to JSON_VALUE() expressions.
 *
 * Usage:
 * ```php
 * class MyModel extends ActiveRecord {
 *     use ElasticTrait;
 *     // query() is automatically overridden to return ElasticQuery
 * }
 *
 * // Then you can query virtual columns:
 * MyModel::query()->where(['myElasticField' => 'value'])->all();
 * MyModel::query()->orderBy(['myElasticField' => SORT_ASC])->all();
 * ```
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
class ElasticQuery extends ActiveQuery
{
    /**
     * @var array<string, array<string>> Cache of real attributes per model class
     */
    private static array $realAttributesCache = [];

    /**
     * @var list<string> Operators that take column as second element
     */
    private const COLUMN_OPERATORS = [
        'BETWEEN', 'NOT BETWEEN', 'IN', 'NOT IN',
        'LIKE', 'NOT LIKE', 'OR LIKE', 'OR NOT LIKE',
        '>', '<', '>=', '<=', '=', '!=', '<>',
    ];

    /**
     * @var list<string> Logical operators with nested conditions
     */
    private const LOGICAL_OPERATORS = ['AND', 'OR', 'NOT'];

    /**
     * Get the JSON column name from the model.
     */
    private function getJsonColumn(): string
    {
        $model = $this->getModel();
        return $model->elasticColumn();
    }

    /**
     * Get real DB attributes for the model class.
     *
     * @return array<string>
     */
    public function getRealAttributes(): array
    {
        $model = $this->getModel();
        $modelClass = $model::class;

        if (!isset(self::$realAttributesCache[$modelClass])) {
            $tableName = $model->tableName();
            $schema = $this->db->getTableSchema($tableName);
            self::$realAttributesCache[$modelClass] = $schema !== null ? array_keys($schema->getColumns()) : [];
        }

        return self::$realAttributesCache[$modelClass];
    }

    /**
     * {@inheritDoc}
     */
    public function where(array|string|ExpressionInterface|null $condition, array $params = []): static
    {
        if (is_array($condition)) {
            $condition = $this->buildVirtualCondition($condition);
        }
        return parent::where($condition, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function setWhere(array|string|ExpressionInterface|null $condition, array $params = []): static
    {
        if (is_array($condition)) {
            $condition = $this->buildVirtualCondition($condition);
        }
        return parent::setWhere($condition, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function andWhere($condition, array $params = []): static
    {
        if (is_array($condition)) {
            $condition = $this->buildVirtualCondition($condition);
        }
        return parent::andWhere($condition, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function orWhere(array|string|ExpressionInterface $condition, array $params = []): static
    {
        if (is_array($condition)) {
            $condition = $this->buildVirtualCondition($condition);
        }
        return parent::orWhere($condition, $params);
    }

    /**
     * {@inheritDoc}
     */
    public function orderBy(array|string|ExpressionInterface $columns): static
    {
        if (is_array($columns)) {
            $columns = $this->buildOrderBy($columns);
        }
        return parent::orderBy($columns);
    }

    /**
     * {@inheritDoc}
     */
    public function addOrderBy(array|string|ExpressionInterface $columns): static
    {
        if (is_array($columns)) {
            $columns = $this->buildOrderBy($columns);
        }
        return parent::addOrderBy($columns);
    }

    /**
     * Build virtual columns for orderBy if needed.
     *
     * @param array $columns
     * @return array
     */
    private function buildOrderBy(array $columns): array
    {
        $result = [];
        foreach ($columns as $column => $direction) {
            $newColumn = $this->buildVirtualColumn($column);
            if ($newColumn instanceof Expression) {
                // Expression objects cannot be array keys, use string representation
                $result[(string) $newColumn] = $direction;
            } else {
                $result[$newColumn] = $direction;
            }
        }
        return $result;
    }

    /**
     * Check if column is virtual (not a real DB column).
     *
     * @param mixed $column
     * @return bool
     */
    private function isVirtualColumn(mixed $column): bool
    {
        if (!is_string($column) || is_int($column)) {
            return false;
        }

        $extractedColumn = $column;
        $model = $this->getModel();
        $modelTable = $model->tableName();

        // Handle table.column format
        if (preg_match('/^\s*([^.]+)\.([\w_-]+)\s*$/', $extractedColumn, $matches)) {
            $table = $matches[1];
            $extractedColumn = $matches[2];

            // Only process columns for our model's table
            if ($table !== $modelTable && $table !== '{{%' . trim($modelTable, '{}%') . '}}') {
                return false;
            }
        }

        // Check if column is a real DB column
        if (preg_match('/\s*([\w_-]+)\s*$/', $extractedColumn, $matches)) {
            $columnToCheck = $matches[1];
            return !in_array($columnToCheck, $this->getRealAttributes(), true);
        }

        return false;
    }

    /**
     * Extract virtual column name from column reference.
     * Called only after isVirtualColumn() returns true.
     *
     * @param string $column
     * @return string
     */
    private function getVirtualColumn(string $column): string
    {
        // Handle table.column format - extract column name
        if (preg_match('/^\s*[^.]+\.([\w_-]+)\s*$/', $column, $matches) === 1) {
            return $matches[1];
        }

        return $column;
    }

    /**
     * Build JSON_VALUE expression for virtual column.
     *
     * @param mixed $column
     * @return mixed
     */
    private function buildVirtualColumn(mixed $column): mixed
    {
        if (!$this->isVirtualColumn($column)) {
            return $column;
        }

        $virtualColumn = $this->getVirtualColumn($column);
        $model = $this->getModel();
        $tableName = $model->tableName();

        return new Expression(
            'JSON_VALUE(' . $tableName . '.[[' . $this->getJsonColumn() . ']], \'$.' . $virtualColumn . '\')'
        );
    }

    /**
     * Check if condition contains virtual columns.
     *
     * @param array $condition
     * @return bool
     */
    private function isVirtualCondition(array $condition): bool
    {
        if (isset($condition[0])) {
            $operator = strtoupper((string) $condition[0]);

            // Handle operators: BETWEEN, IN, LIKE, comparison operators, etc.
            // Format: ['operator', 'column', value(s)]
            if (in_array($operator, self::COLUMN_OPERATORS, true)) {
                if (isset($condition[1]) && $this->isVirtualColumn($condition[1])) {
                    return true;
                }
            } elseif (in_array($operator, self::LOGICAL_OPERATORS, true)) {
                // Recursively check nested conditions
                for ($i = 1; $i < count($condition); $i++) {
                    if (is_array($condition[$i]) && $this->isVirtualCondition($condition[$i])) {
                        return true;
                    }
                }
            }
        }

        // Hash format: ['column' => 'value']
        foreach ($condition as $column => $value) {
            if (!is_int($column) && $this->isVirtualColumn($column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build condition with virtual columns converted to JSON_VALUE.
     *
     * @param array $condition
     * @return array
     */
    private function buildVirtualCondition(array $condition): array
    {
        if (!$this->isVirtualCondition($condition)) {
            return $condition;
        }

        if (isset($condition[0])) {
            $operator = strtoupper((string) $condition[0]);

            // Handle operators: BETWEEN, IN, LIKE, comparison operators, etc.
            // Format: ['operator', 'column', value(s)]
            if (in_array($operator, self::COLUMN_OPERATORS, true)) {
                if (isset($condition[1]) && $this->isVirtualColumn($condition[1])) {
                    $condition[1] = $this->buildVirtualColumn($condition[1]);
                }
                return $condition;
            }

            // Handle AND, OR, NOT - recursively process nested conditions
            if (in_array($operator, self::LOGICAL_OPERATORS, true)) {
                for ($i = 1; $i < count($condition); $i++) {
                    if (is_array($condition[$i])) {
                        $condition[$i] = $this->buildVirtualCondition($condition[$i]);
                    }
                }
            }

            return $condition;
        }

        // Hash format: ['column' => 'value']
        $result = [];
        foreach ($condition as $column => $value) {
            if (!is_int($column) && $this->isVirtualColumn($column)) {
                // buildVirtualColumn returns Expression for virtual columns
                $result[(string) $this->buildVirtualColumn($column)] = $value;
            } else {
                $result[$column] = $value;
            }
        }

        return $result;
    }
}
