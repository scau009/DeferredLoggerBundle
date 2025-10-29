<?php

namespace Barry\DeferredLoggerBundle\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Driver wrapper for SQL logging middleware
 */
class SQLLoggingDriver extends AbstractDriverMiddleware
{
    public function connect(
        #[\SensitiveParameter]
        array $params
    ): Connection {
        $connection = parent::connect($params);

        return new SQLLoggingConnection($connection);
    }
}