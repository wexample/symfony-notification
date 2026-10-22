<?php

namespace Wexample\SymfonyNotification\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonyNotification\Entity\NotificationPreference;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    public function findOne(string $recipientType, string $recipientId, string $category, string $channel): ?NotificationPreference
    {
        return $this->findOneBy([
            'recipientType' => $recipientType,
            'recipientId' => $recipientId,
            'category' => $category,
            'channel' => $channel,
        ]);
    }

    /**
     * @return list<NotificationPreference>
     */
    public function findForRecipient(string $recipientType, string $recipientId): array
    {
        return $this->findBy(['recipientType' => $recipientType, 'recipientId' => $recipientId]);
    }
}
