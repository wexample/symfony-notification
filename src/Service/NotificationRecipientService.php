<?php

namespace Wexample\SymfonyNotification\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use LogicException;
use Throwable;

/**
 * A recipient — or an actor — as a notification keeps it, by type and id,
 * and back: the entity the channels deliver to.
 */
class NotificationRecipientService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{string, string}
     *
     * @throws LogicException for an object with no id
     */
    public function key(object $entity): array
    {
        if (! method_exists($entity, 'getId') || null === $entity->getId()) {
            throw new LogicException(sprintf('A %s has no id to be notified by.', $entity::class));
        }

        $class = $entity instanceof Proxy ? get_parent_class($entity) : $entity::class;

        return [$class, (string) $entity->getId()];
    }

    public function find(string $type, string $id): ?object
    {
        try {
            return class_exists($type) ? $this->entityManager->find($type, $id) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
