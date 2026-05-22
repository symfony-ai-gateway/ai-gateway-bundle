<?php

declare(strict_types=1);

namespace AIGateway\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration tree for dashboard and routing options.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ai_gateway');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('dashboard')
                    ->children()
                        ->booleanNode('tokenRequired')->defaultFalse()->end()
                        ->booleanNode('token_required')->defaultFalse()->end()
                        ->scalarNode('token')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->children()
                        ->scalarNode('prefix')->defaultValue('')->end()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
