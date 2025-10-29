<?php

namespace Barry\DeferredLoggerBundle\EventSubscriber;

use Barry\DeferredLoggerBundle\Service\DeferredLoggerInstance;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Initializes DeferredLoggerInstance for console commands
 */
class ConsoleInitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
        // Initialize DeferredLoggerInstance early for CLI commands
        DeferredLoggerInstance::getInstance($this->logger);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 255],
        ];
    }

    public function onConsoleCommand(): void
    {
        // Instance already initialized in constructor
        // This event ensures subscriber is instantiated early
    }
}