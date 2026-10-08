<?php

namespace Wexample\SymfonyNotification\Service;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyHelpers\Entity\Interfaces\AbstractEntityInterface;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Message\DeliverNotificationsMessage;
use Wexample\SymfonyNotification\Repository\NotificationRepository;

/**
 * Where an application tells people something:
 *
 *     $notifications->notify('team.member_added', $team->getAdministrators(), $team, ['member' => $member->getEmail()], $actor);
 *
 * One Notification per recipient allowed to receive the category, kept in
 * its inbox; then a message, after the flush, sends them through their
 * channels — at once, or in the background where the application routes
 * DeliverNotificationsMessage to a transport.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly NotificationCategoryRegistry $categories,
        private readonly NotificationRecipientService $recipients,
        private readonly NotificationRepository $repository,
    ) {
    }

    /**
     * @param iterable<object>           $recipients
     * @param array<string, scalar|null> $parameters what the texts are read with
     *
     * @return list<Notification> the notifications made, one per recipient allowed to receive it
     */
    public function notify(
        string $category,
        iterable $recipients,
        ?AbstractEntityInterface $subject = null,
        array $parameters = [],
        ?object $actor = null,
    ): array {
        return $this->create($category, $recipients, $subject, $parameters, $actor);
    }

    /**
     * notify(), but not to a recipient already told this, about this subject,
     * since $since: a reminder run every day reaches each one once per
     * window.
     *
     * @param iterable<object>           $recipients
     * @param array<string, scalar|null> $parameters
     *
     * @return list<Notification> the notifications made — none when everyone had it already
     */
    public function notifyOnce(
        string $category,
        iterable $recipients,
        ?AbstractEntityInterface $subject,
        DateTimeInterface $since,
        array $parameters = [],
        ?object $actor = null,
    ): array {
        return $this->create($category, $recipients, $subject, $parameters, $actor, $since);
    }

    /**
     * @param iterable<object>           $recipients
     * @param array<string, scalar|null> $parameters
     *
     * @return list<Notification>
     */
    private function create(
        string $category,
        iterable $recipients,
        ?AbstractEntityInterface $subject,
        array $parameters,
        ?object $actor,
        ?DateTimeInterface $since = null,
    ): array {
        $definition = $this->categories->get($category);
        $subjectKey = $subject ? $this->recipients->key($subject) : null;
        $actorKey = $actor ? $this->recipients->key($actor) : null;
        $created = [];
        $seen = [];

        foreach ($recipients as $recipient) {
            [$type, $id] = $this->recipients->key($recipient);

            if (isset($seen[$type . '#' . $id]) || ! $this->categories->mayReceive($definition, $recipient)) {
                continue;
            }

            $seen[$type . '#' . $id] = true;

            if ($since && $this->repository->existsSince($category, $type, $id, $subjectKey[0] ?? null, $subjectKey ? Uuid::fromString($subjectKey[1]) : null, $since)) {
                continue;
            }

            $notification = new Notification($category, $definition->level, $type, $id, $parameters);

            if ($subject) {
                $notification->setLinkedEntity($subject);
            }

            if ($actorKey) {
                $notification->setActor(...$actorKey);
            }

            $this->entityManager->persist($notification);
            $created[] = $notification;
        }

        if ($created) {
            $this->entityManager->flush();
            $this->bus->dispatch(new DeliverNotificationsMessage(array_map(
                static fn (Notification $notification) => (string) $notification->getId(),
                $created
            )));
        }

        return $created;
    }
}
