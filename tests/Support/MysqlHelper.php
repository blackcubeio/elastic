<?php

declare(strict_types=1);

namespace Blackcube\Elastic\Tests\Support;

use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

final class MysqlHelper
{
    private static ?ConnectionInterface $connection = null;

    public function createConnection(): ConnectionInterface
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';
        $database = $_ENV['DB_DATABASE'] ?? '';
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $user = $_ENV['DB_USER'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        $pdoDriver = new Driver("$driver:host=$host;dbname=$database;port=$port", $user, $password);
        $pdoDriver->charset('UTF8MB4');

        self::$connection = new Connection($pdoDriver, new SchemaCache(new MemorySimpleCache()));
        return self::$connection;
    }
}
