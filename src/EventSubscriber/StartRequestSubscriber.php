<?php

namespace Barry\DeferredLoggerBundle\EventSubscriber;

use Barry\DeferredLoggerBundle\Service\DeferredLogger;
use Barry\DeferredLoggerBundle\Service\DeferredLoggerInstance;
use Barry\DeferredLoggerBundle\Service\TraceContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class StartRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(LoggerInterface $logger)
    {
        DeferredLoggerInstance::getInstance($logger);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256], // High priority to set trace early
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $instance = DeferredLoggerInstance::getInstance();


        // Reset buffer for new request (important for long-running processes)
        $instance->reset();
        // Extract or generate trace context from request headers
        $traceContext = TraceContext::fromHeaders($request->headers->all());
        $instance->setTraceContext($traceContext);

        DeferredLogger::contextData([
            'client_ip' => $request->getClientIp(),
            'method' => $request->getMethod(),
            'request' => $request->getRequestUri(),
            'query' => $request->query->all(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ], "REQUEST STARTED");
    }
}