<?php

namespace Wexample\SymfonyNotification\Repository;

use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyNotification\Entity\Notification;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * The inbox of a recipient, the latest first.
     *
     * @return array{notifications: list<Notification>, count: int, pages_count: int}
     */
    public function paginateForRecipient(string $recipientType, string $recipientId, int $page, int $perPage, bool $unreadOnly = false): array
    {
        $builder = $this->createRecipientQuery($recipientType, $recipientId);

        if ($unreadOnly) {
            $builder->andWhere('notification.dateRead IS NULL');
        }

        $count = (int) (clone $builder)->select('COUNT(notification.id)')->getQuery()->getSingleScalarResult();

        return [
            'notifications' => $builder
                ->orderBy('notification.dateCreated', 'DESC')
                ->setFirstResult(($page - 1) * $perPage)
                ->setMaxResults($perPage)
                ->getQuery()
                ->getResult(),
            'count' => $count,
            'pages_count' => max(1, (int) ceil($count / $perPage)),
        ];
    }

    public function countUnread(string $recipientType, string $recipientId): int
    {
        return (int) $this->createRecipientQuery($recipientType, $recipientId)
            ->select('COUNT(notification.id)')
            ->andWhere('notification.dateRead IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForRecipient(string $id, string $recipientType, string $recipientId): ?Notification
    {
        if (! Uuid::isValid($id)) {
            return null;
        }

        return $this->createRecipientQuery($recipientType, $recipientId)
            ->andWhere('notification.id = :id')
            ->setParameter('id', Uuid::fromString($id), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markAllRead(string $recipientType, string $recipientId): int
    {
        return $this->createQueryBuilder('notification')
            ->update()
            ->set('notification.dateRead', ':now')
            ->where('notification.recipientType = :type')
            ->andWhere('notification.recipientId = :id')
            ->andWhere('notification.dateRead IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('type', $recipientType)
            ->setParameter('id', $recipientId)
            ->getQuery()
            ->execute();
    }

    /**
     * Whether this recipient was already told this, about this subject, since
     * then: what notifyOnce() asks.
     */
    public function existsSince(string $category, string $recipientType, string $recipientId, ?string $subjectType, ?Uuid $subjectId, DateTimeInterface $since): bool
    {
        $builder = $this->createRecipientQuery($recipientType, $recipientId)
            ->select('1')
            ->andWhere('notification.category = :category')
            ->andWhere('notification.dateCreated >= :since')
            ->setParameter('category', $category)
            ->setParameter('since', $since)
            ->setMaxResults(1);

        if ($subjectType === null) {
            $builder->andWhere('notification.entityType IS NULL');
        } else {
            $builder
                ->andWhere('notification.entityType = :subjectType')
                ->andWhere('notification.entityId = :subjectId')
                ->setParameter('subjectType', $subjectType)
                ->setParameter('subjectId', $subjectId, UuidType::NAME);
        }

        return (bool) $builder->getQuery()->getOneOrNullResult();
    }

    private function createRecipientQuery(string $recipientType, string $recipientId): QueryBuilder
    {
        return $this->createQueryBuilder('notification')
            ->where('notification.recipientType = :recipientType')
            ->andWhere('notification.recipientId = :recipientId')
            ->setParameter('recipientType', $recipientType)
            ->setParameter('recipientId', $recipientId);
    }
}
