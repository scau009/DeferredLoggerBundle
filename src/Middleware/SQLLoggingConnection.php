<?php

namespace Barry\DeferredLoggerBundle\Middleware;

use Barry\DeferredLoggerBundle\Service\DeferredLogger;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Connection wrapper for SQL logging
 */
class SQLLoggingConnection extends AbstractConnectionMiddleware
{
    public function prepare(string $sql): Statement
    {
        return new SQLLoggingStatement(
            parent::prepare($sql),
            $sql
        );
    }

    public function query(string $sql): Result
    {
        $startTime = microtime(true);

        try {
            $result = parent::query($sql);

            $this->logQuery($sql, [], $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->logQuery($sql, [], $startTime, $e);
            throw $e;
        }
    }

    public function exec(string $sql): int
    {
        $startTime = microtime(true);

        try {
            $result = parent::exec($sql);

            $this->logQuery($sql, [], $startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->logQuery($sql, [], $startTime, $e);
            throw $e;
        }
    }

    private function logQuery(
        string $sql,
        array $params,
        float $startTime,
        ?\Throwable $exception = null
    ): void {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        $context = [
            'sql' => $sql,
            'params' => $params,
            'formatted_sql' => $this->formatSql($sql, $params),
            'execution_time_ms' => $executionTime,
        ];

        if ($exception !== null) {
            $context['error'] = $exception->getMessage();
        }

        DeferredLogger::contextSql(
            $context,
            $exception !== null ? 'SQL ERROR' : 'SQL EXECUTED'
        );
    }

    private function formatSql(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }

        $formatted = $sql;
        $paramIndex = 0;

        // Replace positional parameters (?)
        $formatted = preg_replace_callback('/\?/', function() use ($params, &$paramIndex) {
            if (!isset($params[$paramIndex])) {
                return '?';
            }
            $value = $this->formatParam($params[$paramIndex]);
            $paramIndex++;
            return $value;
        }, $formatted);

        // Replace named parameters (:name)
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $formatted = str_replace(
                    ':' . $key,
                    $this->formatParam($value),
                    $formatted
                );
            }
        }

        return $formatted;
    }

    private function formatParam(mixed $param): string
    {
        if ($param === null) {
            return 'NULL';
        }

        if (is_bool($param)) {
            return $param ? 'TRUE' : 'FALSE';
        }

        if (is_numeric($param)) {
            return (string) $param;
        }

        if (is_string($param)) {
            return "'" . str_replace("'", "''", $param) . "'";
        }

        if (is_array($param) || is_object($param)) {
            return "'" . json_encode($param) . "'";
        }

        return "'" . (string) $param . "'";
    }
}
