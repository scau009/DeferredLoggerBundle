<?php
namespace Barry\DeferredLoggerBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;


class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('barry_deferred_logger');
        $root = $tb->getRootNode();


        $root
            ->children()
            ->scalarNode('logger_channel')->defaultValue('app')->end()
            ->booleanNode('auto_flush_on_exception')->defaultTrue()->end()
            ->booleanNode('auto_flush_on_request')->defaultFalse()->end()
            ->booleanNode('enable_sql_logging')->defaultFalse()->end()
            ->booleanNode('inject_trace_id_in_response')->defaultTrue()->end()
            ->booleanNode('enable_messenger_trace')->defaultTrue()->end()
            ->end();


        return $tb;
    }
}