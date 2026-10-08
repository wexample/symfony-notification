<?php

namespace Wexample\SymfonyNotification\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Repository\NotificationRepository;

/**
 * The inbox of the signed-in account: what it was told, read or not. Nobody
 * signed in has an empty one.
 */
class NotificationInboxService
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $repository,
        private readonly NotificationRecipientService $recipients,
    ) {
    }

    /**
     * @return array{notifications: list<Notification>, count: int, pages_count: int}
     */
    public function paginate(int $page, int $perPage, bool $unreadOnly = false): array
    {
        $key = $this->getKey();

        return $key
            ? $this->repository->paginateForRecipient($key[0], $key[1], $page, $perPage, $unreadOnly)
            : ['notifications' => [], 'count' => 0, 'pages_count' => 1];
    }

    public function countUnread(): int
    {
        $key = $this->getKey();

        return $key ? $this->repository->countUnread(...$key) : 0;
    }

    /**
     * Its own notification under that id, marked read; null for any other.
     */
    public function markRead(string $id): ?Notification
    {
        $key = $this->getKey();
        $notification = $key ? $this->repository->findOneForRecipient($id, ...$key) : null;

        if ($notification && ! $notification->isRead()) {
            $notification->markRead();
            $this->repository->getEntityManager()->flush();
        }

        return $notification;
    }

    public function markAllRead(): int
    {
        $key = $this->getKey();

        return $key ? $this->repository->markAllRead(...$key) : 0;
    }

    /**
     * @return array{string, string}|null
     */
    private function getKey(): ?array
    {
        $user = $this->security->getUser();

        return $user && method_exists($user, 'getId') && $user->getId() !== null
            ? $this->recipients->key($user)
            : null;
    }
}
