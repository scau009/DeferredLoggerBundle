<?php

namespace Barry\DeferredLoggerBundle\Service;

/**
 * @method static contextInfo(string $info)
 * @method static contextData(mixed $data, string $message = '')
 * @method static contextSql(mixed $data, string $message = 'SQL EXECUTED')
 * @method static contextException(\Throwable $e)
 * @method static flush()
 * @method static finalize()
 * @method static getTraceId()
 *
 */
class DeferredLogger
{
    public static function __callStatic(string $name, array $arguments)
    {
        return DeferredLoggerInstance::getInstance()->$name(...$arguments);
    }
}
