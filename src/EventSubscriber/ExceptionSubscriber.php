<?php

namespace Barry\DeferredLoggerBundle\EventSubscriber;

use Barry\DeferredLoggerBundle\Exception\AutoFlushException;
use Barry\DeferredLoggerBundle\Service\DeferredLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private bool $autoFlushOnException = false,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($this->autoFlushOnException && $event->getThrowable() instanceof AutoFlushException) {
            DeferredLogger::flush();
        }
    }
}