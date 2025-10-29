<?php

namespace Barry\DeferredLoggerBundle\Middleware;

use Barry\DeferredLoggerBundle\Service\DeferredLogger;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;

/**
 * Statement wrapper for SQL logging with parameter binding
 */
class SQLLoggingStatement extends AbstractStatementMiddleware
{
    private array $params = [];
    private float $startTime = 0;

    public function __construct(
        \Doctrine\DBAL\Driver\Statement $statement,
        private string $sql
    ) {
        parent::__construct($statement);
    }

    public function bindValue($param, $value, $type = ParameterType::STRING): void
    {
        $this->params[$param] = $value;
        parent::bindValue($param, $value, $type);
    }

    public function execute($params = null): Result
    {
        $this->startTime = microtime(true);

        try {
            $result = parent::execute();

            $this->logQuery();

            return $result;
        } catch (\Throwable $e) {
            $this->logQuery($e);
            throw $e;
        }
    }

    private function logQuery(?\Throwable $exception = null): void
    {
        // Skip logging if DeferredLogger is not initialized (e.g., in CLI commands)
        if (!\Barry\DeferredLoggerBundle\Service\DeferredLoggerInstance::isInitialized()) {
            return;
        }

        $executionTime = $this->startTime !== 0
            ? round((microtime(true) - $this->startTime) * 1000, 2)
            : 0;

        $context = [
            'sql' => $this->sql,
            'params' => $this->params,
            'formatted_sql' => $this->formatSql($this->sql, $this->params),
            'execution_time_ms' => $executionTime,
        ];

        if ($exception !== null) {
            $context['error'] = $exception->getMessage();
        }

        try {
            DeferredLogger::contextData(
                $context,
                $exception !== null ? 'SQL ERROR' : 'SQL EXECUTED'
            );
        } catch (\Throwable $e) {
            // Silently fail if logger not available
        }
    }

    private function formatSql(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }

        $formatted = $sql;
        $paramIndex = 1; // Named parameters often start at 1

        // Replace positional parameters (?)
        $formatted = preg_replace_callback('/\?/', function() use ($params, &$paramIndex) {
            $value = $params[$paramIndex] ?? $params[$paramIndex - 1] ?? '?';
            $result = is_string($value) || is_numeric($value)
                ? $this->formatParam($value)
                : '?';
            $paramIndex++;
            return $result;
        }, $formatted);

        // Replace named parameters (:name)
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $placeholder = str_starts_with($key, ':') ? $key : ':' . $key;
                $formatted = str_replace(
                    $placeholder,
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
