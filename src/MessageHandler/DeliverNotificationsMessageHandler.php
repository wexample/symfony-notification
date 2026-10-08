<?php

namespace Wexample\SymfonyNotification\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;
use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Interface\NotificationChannelInterface;
use Wexample\SymfonyNotification\Message\DeliverNotificationsMessage;
use Wexample\SymfonyNotification\Repository\NotificationRepository;
use Wexample\SymfonyNotification\Service\NotificationCategoryRegistry;
use Wexample\SymfonyNotification\Service\NotificationPreferenceService;
use Wexample\SymfonyNotification\Service\NotificationRecipientService;

/**
 * Sends the notifications of one notify(): each through the personal
 * channels its recipient keeps, then the batch once through the broadcast
 * channels of its category. A channel failing is logged and the others go
 * on: the notification is in the inbox already.
 */
#[AsMessageHandler]
class DeliverNotificationsMessageHandler
{
    /** @var array<string, NotificationChannelInterface> */
    private array $channels = [];

    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        private readonly NotificationRepository $repository,
        private readonly NotificationCategoryRegistry $categories,
        private readonly NotificationPreferenceService $preferences,
        private readonly NotificationRecipientService $recipients,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[AutowireIterator(NotificationChannelInterface::TAG)]
        iterable $channels = [],
    ) {
        foreach ($channels as $channel) {
            $this->channels[$channel::getName()] = $channel;
        }
    }

    public function __invoke(DeliverNotificationsMessage $message): void
    {
        $notifications = array_values(array_filter(array_map(
            fn (string $id) => $this->repository->find($id),
            $message->ids
        )));

        if (! $notifications) {
            return;
        }

        $category = $this->categories->get($notifications[0]->getCategory());

        foreach ($notifications as $notification) {
            $recipient = $this->recipients->find($notification->getRecipientType(), $notification->getRecipientId());

            if (! $recipient) {
                continue;
            }

            foreach ($category->channels as $name) {
                if ($this->preferences->isEnabled($recipient, $category, $name)
                    && $this->send($name, true, $category, $notification, $recipient)) {
                    $notification->addChannel($name);
                }
            }
        }

        foreach ($category->broadcast as $name) {
            $this->send($name, false, $category, $notifications[0], null);
        }

        $this->entityManager->flush();
    }

    private function send(string $name, bool $personal, NotificationCategory $category, Notification $notification, ?object $recipient): bool
    {
        $channel = $this->channels[$name] ?? null;

        if (! $channel || $channel->isPersonal() !== $personal) {
            $this->logger->warning('Notification channel "{channel}" is not a {kind} channel of this application.', [
                'channel' => $name,
                'kind' => $personal ? 'personal' : 'broadcast',
            ]);

            return false;
        }

        try {
            $channel->deliver($category, $notification, $recipient);

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Notification channel "{channel}" failed: {error}', [
                'channel' => $name,
                'category' => $notification->getCategory(),
                'notification' => (string) $notification->getId(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
