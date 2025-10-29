<?php

namespace Barry\DeferredLoggerBundle\Service;

use Psr\Log\LoggerInterface;
use Monolog\Logger;

/**
 * @method static contextInfo(string $info)
 * @method static contextData(mixed $data, string $message = '')
 * @method static contextSql(mixed $data, string $message = 'SQL EXECUTED')
 * @method static contextException(\Throwable $e)
 */
class DeferredLoggerInstance
{
    private static ?self $instance = null;

    private LoggerInterface $logger;

    /** @var array<string,mixed> */
    private array $buffer = [];

    private ?TraceContext $traceContext = null;

    private function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return self::getInstance()->$name(...$arguments);
    }

    public static function getInstance(LoggerInterface $logger = null): self
    {
        if (self::$instance === null) {
            if ($logger === null) {
                throw new \LogicException('Logger must be set');
            }
            self::$instance = new self($logger);
        }
        return self::$instance;
    }

    /**
     * Set trace context from external source (e.g., HTTP headers)
     */
    public function setTraceContext(TraceContext $traceContext): void
    {
        $this->traceContext = $traceContext;
    }

    /**
     * Get or create trace context
     */
    public function getTraceContext(): TraceContext
    {
        if ($this->traceContext === null) {
            $this->traceContext = new TraceContext();
        }
        return $this->traceContext;
    }

    public function contextInfo(string $info): mixed
    {
        return $this->buffer[] = new LogItem(LogItem::TYPE_INFO, $info);
    }

    /**
     * @param mixed $data
     * @param string $message
     * @return $this
     */
    public function contextData(mixed $data, string $message = 'COLLECTED DATA'): self
    {
        $this->buffer[] = new LogItem(LogItem::TYPE_DATA, $message, $data);
        return $this;
    }

    /**
     * @param mixed $data
     * @param string $message
     * @return $this
     */
    public function contextSql(mixed $data, string $message = 'SQL EXECUTED'): self
    {
        $this->buffer[] = new LogItem(LogItem::TYPE_SQL, $message, $data);
        return $this;
    }

    public function contextException(\Throwable $e): self
    {
        $this->buffer[] = new LogItem(LogItem::TYPE_EXCEPTION, "EXCEPTION OCCURRED", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace(),
        ]);
        return $this;
    }

    public function clear(): void
    {
        if ($this->logger instanceof Logger) {
            $this->logger->reset();
        }
        $this->buffer = [];
    }

    /**
     * Immediately write buffered context to logger with given level and message
     */
    public function flush(int $level = Logger::INFO): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $traceContext = $this->getTraceContext();

        // PSR-3 logger expects level names via ->log
        $this->logger->log($this->mapLevel($level), 'Deferred Logger Flush', array_merge(
            $traceContext->toArray(),
            [
                'info' => $this->buffer,
            ]
        ));
    }

    /**
     * Called at end of lifecycle (e.g. kernel.terminate). If enabled, flush.
     */
    public function finalize(): void
    {
        $this->flush();
    }

    /**
     * Reset instance for new request (useful in long-running processes)
     */
    public function reset(): void
    {
        $this->clear();
        $this->traceContext = null;
    }

    private function normalizeContext(array $ctx): mixed
    {
        $enc = @json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $enc !== false ? $enc : $ctx;
    }

    private function mapLevel(int $level): string
    {
        // Monolog\Logger constants to PSR-3 level names
        return Logger::getLevelName($level) ?: 'info';
    }

    /**
     * Get current trace ID (backward compatibility)
     */
    public static function getTraceId(): string
    {
        return self::getInstance()->getTraceContext()->getTraceId();
    }
}
