<?php

namespace Barry\DeferredLoggerBundle\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp that carries trace context across async message boundaries
 */
class TraceContextStamp implements StampInterface
{
    public function __construct(
        private string $traceId,
        private string $spanId,
        private ?string $parentSpanId = null,
        private bool $sampled = true
    ) {
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
     * Create stamp from TraceContext
     */
    public static function fromTraceContext(\Barry\DeferredLoggerBundle\Service\TraceContext $context): self
    {
        return new self(
            traceId: $context->getTraceId(),
            spanId: $context->getSpanId(),
            parentSpanId: $context->getParentSpanId(),
            sampled: $context->isSampled()
        );
    }

    /**
     * Convert stamp to TraceContext
     */
    public function toTraceContext(): \Barry\DeferredLoggerBundle\Service\TraceContext
    {
        return new \Barry\DeferredLoggerBundle\Service\TraceContext(
            traceId: $this->traceId,
            spanId: $this->spanId,
            parentSpanId: $this->parentSpanId,
            sampled: $this->sampled
        );
    }
}