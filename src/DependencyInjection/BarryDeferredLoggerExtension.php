<?php
namespace Barry\DeferredLoggerBundle\DependencyInjection;


use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;


class BarryDeferredLoggerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);


        $container->setParameter('barry_deferred_logger.logger_channel', $config['logger_channel']);
        $container->setParameter('barry_deferred_logger.auto_flush_on_exception', $config['auto_flush_on_exception']);
        $container->setParameter('barry_deferred_logger.auto_flush_on_request', $config['auto_flush_on_request']);
        $container->setParameter('barry_deferred_logger.enable_sql_logging', $config['enable_sql_logging']);
        $container->setParameter('barry_deferred_logger.inject_trace_id_in_response', $config['inject_trace_id_in_response']);
        $container->setParameter('barry_deferred_logger.enable_messenger_trace', $config['enable_messenger_trace']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../Resources/config'));
        $loader->load('services.yaml');
    }
}