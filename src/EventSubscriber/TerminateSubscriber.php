<?php

namespace Barry\DeferredLoggerBundle\EventSubscriber;

use Barry\DeferredLoggerBundle\Service\DeferredLogger;
use Barry\DeferredLoggerBundle\Service\DeferredLoggerInstance;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TerminateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private bool $autoFlushOnRequest = false,
        private bool $injectTraceIdInResponse = true
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256], // Low priority, after most processing
            KernelEvents::TERMINATE => ['onKernelTerminate', 0],
        ];
    }

    /**
     * Inject trace ID into response headers for distributed tracing
     */
    public function onKernelResponse(\Symfony\Component\HttpKernel\Event\ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->injectTraceIdInResponse) {
            return;
        }

        $instance = DeferredLoggerInstance::getInstance();
        $traceContext = $instance->getTraceContext();
        $response = $event->getResponse();

        // Add trace headers to response
        $response->headers->set('X-Trace-ID', $traceContext->getTraceId());
        $response->headers->set('X-Span-ID', $traceContext->getSpanId());

        // Also add W3C Trace Context for compatibility
        $response->headers->set('traceparent', $traceContext->toW3CTraceParent());
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $contentType = $event->getResponse()->headers->get('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            DeferredLogger::contextData([
                'response' => $event->getResponse()->getContent(),
            ], "REQUEST TERMINATED");
        } else {
            DeferredLogger::contextData([], 'NO JSON RESPONSE, IGNORED');
        }

        if ($this->autoFlushOnRequest) {
            DeferredLogger::finalize();
        }
    }
}