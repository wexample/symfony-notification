<?php

namespace Wexample\SymfonyNotification\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
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
    private readonly array $channels;

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
        $byName = [];
        foreach ($channels as $channel) {
            $byName[$channel::getName()] = $channel;
        }

        $this->channels = $byName;
    }

    public function __invoke(DeliverNotificationsMessage $message): void
    {
        // Read at once, then put back in the order notify() made them: the
        // first one stands for the batch on the broadcast channels.
        $ids = array_values(array_filter($message->ids, Uuid::isValid(...)));
        $byId = [];

        foreach ($ids ? $this->repository->findBy(['id' => $ids]) : [] as $notification) {
            $byId[(string) $notification->getId()] = $notification;
        }

        $notifications = array_values(array_filter(array_map(
            static fn (string $id) => $byId[Uuid::fromString($id)->toRfc4122()] ?? null,
            $ids
        )));

        if (! $notifications) {
            return;
        }

        $category = $this->categories->get($notifications[0]->getCategory());

        // The recipients and what they turned off, read for the whole batch:
        // a notify() to a whole team is otherwise a query per member and
        // another per member and channel.
        $found = $this->recipients->findAll(array_map(
            static fn (Notification $notification) => [$notification->getRecipientType(), $notification->getRecipientId()],
            $notifications
        ));
        $recipients = [];

        foreach ($notifications as $index => $notification) {
            $recipient = $found[$notification->getRecipientType().'#'.$notification->getRecipientId()] ?? null;

            if ($recipient) {
                $recipients[$index] = $recipient;
            }
        }

        $enabled = $this->preferences->getEnabledForEach($recipients, $category);

        foreach ($recipients as $index => $recipient) {
            $notification = $notifications[$index];

            foreach ($category->channels as $name) {
                if ($enabled[$index][$name]
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
