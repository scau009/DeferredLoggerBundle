<?php

namespace Barry\DeferredLoggerBundle\Service;

class LogItem
{
    const TYPE_INFO = 'info';
    const TYPE_SQL = 'sql';
    const TYPE_DATA = 'data';
    const TYPE_EXCEPTION = 'exception';

    public string $type;
    public string $message;
    public mixed $buffer = [];
    public int $microtime;
    public string $time;
    public string $memory;

    public string $systemLoad;

    public function __construct(string $type, string $message, mixed $buffer = [])
    {
        $this->type = $type;
        $this->message = $message;
        $this->microtime = floor(microtime(true) * 1000);
        $this->time = date('Y-m-d H:i:s');
        $this->memory = "Current: " . $this->formatBytes(memory_get_usage()) . "| Peak: " . $this->formatBytes(memory_get_peak_usage());
        $this->buffer = $buffer;
        $this->systemLoad = sys_getloadavg()[0];
    }

    function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        if ($bytes <= 0) {
            return '0 B';
        }

        $pow = floor(log($bytes, 1024)); // 计算应该用哪个单位
        $pow = min($pow, count($units) - 1); // 防止超出 TB

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

}