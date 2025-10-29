<?php

namespace Barry\DeferredLoggerBundle;

use Barry\DeferredLoggerBundle\DependencyInjection\Compiler\InitializeDeferredLoggerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BarryDeferredLoggerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new InitializeDeferredLoggerPass());
    }
}