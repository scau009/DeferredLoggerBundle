<?php

namespace Barry\DeferredLoggerBundle\Service;

use Symfony\Component\Uid\Uuid;

/**
 * Manages distributed tracing context (TraceId, SpanId, etc.)
 * Supports W3C Trace Context and custom trace headers
 */
class TraceContext
{
    private string $traceId;
    private string $spanId;
    private ?string $parentSpanId = null;
    private bool $sampled = true;

    public function __construct(
        ?string $traceId = null,
        ?string $spanId = null,
        ?string $parentSpanId = null,
        bool $sampled = true
    ) {
        $this->traceId = $traceId ?? $this->generateTraceId();
        $this->spanId = $spanId ?? $this->generateSpanId();
        $this->parentSpanId = $parentSpanId;
        $this->sampled = $sampled;
    }

    /**
     * Parse W3C Trace Context from traceparent header
     * Format: 00-{trace-id}-{parent-id}-{trace-flags}
     */
    public static function fromW3CTraceParent(string $traceparent): ?self
    {
        $parts = explode('-', $traceparent);
        if (count($parts) !== 4) {
            return null;
        }

        [$version, $traceId, $parentSpanId, $flags] = $parts;

        if ($version !== '00') {
            return null; // Only support version 00
        }

        $sampled = (hexdec($flags) & 1) === 1;

        return new self(
            traceId: $traceId,
            spanId: self::generateSpanId(), // Generate new span for this service
            parentSpanId: $parentSpanId,
            sampled: $sampled
        );
    }

    /**
     * Parse from common trace headers (X-Request-ID, X-Trace-ID, etc.)
     */
    public static function fromHeaders(array $headers): self
    {
        // Priority order: W3C traceparent > X-Trace-ID > X-Request-ID

        // 1. Try W3C Trace Context
        if (isset($headers['traceparent'][0])) {
            $context = self::fromW3CTraceParent($headers['traceparent'][0]);
            if ($context !== null) {
                return $context;
            }
        }

        // 2. Try X-Trace-ID (common in microservices)
        if (isset($headers['x-trace-id'][0])) {
            return new self(traceId: $headers['x-trace-id'][0]);
        }

        // 3. Try X-Request-ID (Nginx, Load Balancers)
        if (isset($headers['x-request-id'][0])) {
            return new self(traceId: $headers['x-request-id'][0]);
        }

        // 4. Try X-Correlation-ID (some systems use this)
        if (isset($headers['x-correlation-id'][0])) {
            return new self(traceId: $headers['x-correlation-id'][0]);
        }

        // 5. Generate new trace
        return new self();
    }

    /**
     * Generate W3C traceparent header value
     */
    public function toW3CTraceParent(): string
    {
        $flags = $this->sampled ? '01' : '00';
        return sprintf('00-%s-%s-%s', $this->traceId, $this->spanId, $flags);
    }

    /**
     * Get all trace information as array
     */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'sampled' => $this->sampled,
        ];
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function isSampled(): bool
    {
        return $this->sampled;
    }

    /**
     * Create a child span (for nested operations)
     */
    public function createChildSpan(): self
    {
        return new self(
            traceId: $this->traceId,
            spanId: self::generateSpanId(),
            parentSpanId: $this->spanId,
            sampled: $this->sampled
        );
    }

    /**
     * Generate a 32-character trace ID (compatible with most tracing systems)
     */
    private static function generateTraceId(): string
    {
        // Remove hyphens from UUID to get 32 hex chars
        return str_replace('-', '', Uuid::v4()->toRfc4122());
    }

    /**
     * Generate a 16-character span ID
     */
    private static function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}