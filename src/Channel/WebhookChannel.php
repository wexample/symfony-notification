<?php

namespace Wexample\SymfonyNotification\Channel;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Interface\NotificationChannelInterface;

/**
 * The notification sent once to a url — an n8n workflow — as data: its
 * category, its level, its subject and its parameters, for the workflow to
 * do what it wants with. Without `webhook.url`, nothing.
 */
class WebhookChannel implements NotificationChannelInterface
{
    public const string NAME = 'webhook';

    /**
     * @param array{url: ?string, method: string, username: ?string, password: ?string, timeout: float} $webhook
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(param: 'wexample_symfony_notification.webhook')]
        private readonly array $webhook,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
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
        if (! $this->webhook['url']) {
            return;
        }

        $payload = [
            'env' => $this->environment,
            'notification' => $category->name,
            'channel' => self::NAME,
            'level' => $notification->getLevel(),
            'subject' => $notification->hasLinkedEntity()
                ? ['type' => $notification->getEntityType(), 'id' => (string) $notification->getEntityId()]
                : null,
            'data' => $notification->getParameters(),
        ];

        $options = ['timeout' => $this->webhook['timeout']];

        if ($this->webhook['username'] !== null) {
            $options['auth_basic'] = [$this->webhook['username'], (string) $this->webhook['password']];
        }

        $options += $this->webhook['method'] === 'GET'
            ? ['query' => ['payload' => json_encode($payload)]]
            : ['json' => $payload];

        // Read, so that a refusal — a status from 300 up — raises here, and is
        // logged by the handler.
        $this->httpClient->request($this->webhook['method'], $this->webhook['url'], $options)->getContent();
    }
}
