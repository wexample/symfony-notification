<?php

namespace Wexample\SymfonyNotification\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyNotification\Class\NotificationCategory;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_notification');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('categories')
                    ->info('Each kind of notification the application sends, `team.member_added`. A category not declared here is refused.')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')
                                ->info('The translation id of its title, read with its parameters in the recipient\'s language.')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('label')
                                ->info('The translation id naming the category on the preferences page, without parameters. Null, its title.')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('body')
                                ->info('The translation id of its text, if it has one.')
                                ->defaultNull()
                            ->end()
                            ->enumNode('level')
                                ->values(NotificationCategory::LEVELS)
                                ->defaultValue(NotificationCategory::LEVEL_INFO)
                            ->end()
                            ->scalarNode('group')
                                ->info('Where the preferences page files it, a translation id: categories of a group are shown together.')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('roles')
                                ->info('The roles, one of which a recipient holds, through the hierarchy. Empty, anyone receives it.')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('channels')
                                ->info('The personal channels it goes out through — `mail` —, each recipient able to turn one off. Every notification is kept in the inbox anyway.')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('broadcast')
                                ->info('The broadcast channels — `chat`, `webhook` —, sent once per notify(), whatever the recipients.')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->scalarNode('route')
                                ->info('The route of the page of its subject, given the subject id as `id`: where the inbox and the mail link to.')
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('chat')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')
                            ->info('The Notifier chatter transport the `chat` channel posts to — `rocketchat`, `slack`, as `framework.notifier.chatter_transports` names it. Null, the first one.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('webhook')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')
                            ->info('Where the `webhook` channel posts, an n8n workflow for instance. Null, the channel sends nothing.')
                            ->defaultNull()
                        ->end()
                        ->enumNode('method')->values(['POST', 'GET'])->defaultValue('POST')->end()
                        ->scalarNode('username')->defaultNull()->end()
                        ->scalarNode('password')->defaultNull()->end()
                        ->floatNode('timeout')->defaultValue(2.0)->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
