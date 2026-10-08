<?php

namespace Wexample\SymfonyNotification\Channel;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Interface\NotificationChannelInterface;
use Wexample\SymfonyNotification\Service\NotificationTextService;

/**
 * The notification posted once to a chat room, through a Notifier chatter
 * transport — Rocket.Chat, Slack — the application configures; in the
 * default language, the room being everyone's. Without a chatter, nothing.
 */
class ChatChannel implements NotificationChannelInterface
{
    public const string NAME = 'chat';

    public function __construct(
        private readonly NotificationTextService $texts,
        private readonly ?ChatterInterface $chatter = null,
        #[Autowire(param: 'wexample_symfony_notification.chat.transport')]
        private readonly ?string $transport = null,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function isPersonal(): bool
    {
        return false;
    }

    public function deliver(NotificationCategory $category, Notification $notification, ?object $recipient): void
    {
        if (! $this->chatter) {
            return;
        }

        $text = implode("\n", array_filter([
            $this->texts->getTitle($notification),
            $this->texts->getBody($notification),
            $this->texts->getUrl($notification, UrlGeneratorInterface::ABSOLUTE_URL),
        ]));

        $this->chatter->send((new ChatMessage($text))->transport($this->transport));
    }
}
