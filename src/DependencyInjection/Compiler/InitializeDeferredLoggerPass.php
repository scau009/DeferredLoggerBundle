<?php

namespace Barry\DeferredLoggerBundle\DependencyInjection\Compiler;

use Barry\DeferredLoggerBundle\Service\DeferredLoggerInstance;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Ensures DeferredLoggerInstance is properly initialized via dependency injection
 */
class InitializeDeferredLoggerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Register DeferredLoggerInstance as a service (non-public)
        if (!$container->hasDefinition(DeferredLoggerInstance::class)) {
            $container->register(DeferredLoggerInstance::class)
                ->setFactory([DeferredLoggerInstance::class, 'getInstance'])
                ->addArgument(new Reference('logger'))
                ->setPublic(false);
        }
    }
}