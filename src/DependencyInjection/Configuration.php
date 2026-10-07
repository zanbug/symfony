<?php

namespace Zanbug\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('zanbug');

        // Symfony >= 4.2 exposes getRootNode(); older versions use root().
        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            $rootNode = $treeBuilder->root('zanbug');
        }

        $rootNode
            ->children()
                ->scalarNode('api_key')->defaultValue('')->end()
                ->scalarNode('app_name')->defaultNull()->end()
                ->scalarNode('host')->defaultValue('https://zanbug.com')->end()
                ->scalarNode('environment')->defaultNull()->end()
                ->scalarNode('release')->defaultNull()->end()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->booleanNode('capture_requests')->defaultFalse()->end()
                ->booleanNode('capture_queries')->defaultTrue()->end()
                ->integerNode('slow_query_ms')->defaultValue(1000)->end()
                ->floatNode('sample_rate')->defaultValue(1.0)->end()
            ->end();

        return $treeBuilder;
    }
}
