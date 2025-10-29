<?php

namespace Barry\DeferredLoggerBundle\Messenger;

use Barry\DeferredLoggerBundle\Service\DeferredLoggerInstance;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Middleware that propagates trace context across async message boundaries
 */
class TraceContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private bool $enabled = true
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$this->enabled) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Check if this is a message being dispatched (sending side)
        $receivedStamp = $envelope->last(ReceivedStamp::class);
        $isDispatching = $receivedStamp === null;

        if ($isDispatching) {
            // DISPATCH: Attach current trace context to the message
            $envelope = $this->attachTraceContext($envelope);
        } else {
            // HANDLE: Restore trace context from the message
            $envelope = $this->restoreTraceContext($envelope);
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            // Clean up after handling (for worker processes)
            if (!$isDispatching) {
                $this->cleanupTraceContext();
            }
        }
    }

    /**
     * Attach current trace context to outgoing message
     */
    private function attachTraceContext(Envelope $envelope): Envelope
    {
        // Skip if already has trace stamp (avoid duplicates)
        if ($envelope->last(TraceContextStamp::class) !== null) {
            return $envelope;
        }

        try {
            $instance = DeferredLoggerInstance::getInstance();
            $traceContext = $instance->getTraceContext();

            // Create a child span for async operation
            $childContext = $traceContext->createChildSpan();

            $stamp = TraceContextStamp::fromTraceContext($childContext);

            return $envelope->with($stamp);
        } catch (\Throwable $e) {
            // If logger not initialized yet, skip trace propagation
            return $envelope;
        }
    }

    /**
     * Restore trace context when handling incoming message
     */
    private function restoreTraceContext(Envelope $envelope): Envelope
    {
        $stamp = $envelope->last(TraceContextStamp::class);

        if ($stamp === null) {
            // No trace context in message, create new one
            return $envelope;
        }

        try {
            $instance = DeferredLoggerInstance::getInstance();
            $traceContext = $stamp->toTraceContext();

            // Reset and set trace context for this worker execution
            $instance->reset();
            $instance->setTraceContext($traceContext);
        } catch (\Throwable $e) {
            // Ignore if logger not available
        }

        return $envelope;
    }

    /**
     * Clean up trace context after message handling
     * Important for long-running workers processing multiple messages
     */
    private function cleanupTraceContext(): void
    {
        try {
            $instance = DeferredLoggerInstance::getInstance();
            $instance->reset();
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }
}