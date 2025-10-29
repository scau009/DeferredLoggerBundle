<?php

namespace Barry\DeferredLoggerBundle\Middleware;

use Barry\DeferredLoggerBundle\Service\DeferredLogger;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Doctrine DBAL Middleware for SQL logging
 * Replaces deprecated SQLLogger interface and postConnect event
 */
class SQLLoggingMiddleware implements Middleware
{
    public function __construct(
        private bool $enableSqlLogging = false
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        if (!$this->enableSqlLogging) {
            return $driver;
        }

        return new SQLLoggingDriver($driver);
    }
}