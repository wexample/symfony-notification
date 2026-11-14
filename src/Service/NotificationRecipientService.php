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

    /**
     * find() for many at once, a query per type rather than one per
     * recipient. What is not found is missing from the result, as find()
     * answers null for it.
     *
     * @param iterable<array{string, string}> $keys type and id, as key() gives them
     *
     * @return array<string, object> by "type#id"
     */
    public function findAll(iterable $keys): array
    {
        $idsByType = [];

        foreach ($keys as [$type, $id]) {
            $idsByType[$type][$id] = $id;
        }

        $found = [];

        foreach ($idsByType as $type => $ids) {
            try {
                $field = $this->entityManager->getClassMetadata($type)->getSingleIdentifierFieldName();

                foreach ($this->entityManager->getRepository($type)->findBy([$field => array_values($ids)]) as $entity) {
                    $found[$type.'#'.$entity->getId()] = $entity;
                }
            } catch (Throwable) {
                // What one query cannot read — no entity, a composite key, an
                // id its column refuses — is read as find() reads it, each
                // failing on its own.
                foreach ($ids as $id) {
                    if ($entity = $this->find($type, (string) $id)) {
                        $found[$type.'#'.$id] = $entity;
                    }
                }
            }
        }

        return $found;
    }
}
