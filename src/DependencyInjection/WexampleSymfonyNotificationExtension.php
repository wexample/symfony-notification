<?php

namespace Wexample\SymfonyNotification\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyNotification\Interface\NotificationChannelInterface;

class WexampleSymfonyNotificationExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wexample_symfony_notification.categories', $config['categories']);
        $container->setParameter('wexample_symfony_notification.chat.transport', $config['chat']['transport']);
        $container->setParameter('wexample_symfony_notification.webhook', $config['webhook']);

        $container
            ->registerForAutoconfiguration(NotificationChannelInterface::class)
            ->addTag(NotificationChannelInterface::TAG);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
