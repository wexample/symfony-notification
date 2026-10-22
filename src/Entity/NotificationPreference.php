<?php

namespace Wexample\SymfonyNotification\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyNotification\Repository\NotificationPreferenceRepository;

/**
 * A choice made by one recipient: this category through this channel, or
 * not. Without a row, the category's channels apply: a notification is
 * opted out of, never into.
 */
#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\Table(name: 'notification_preference')]
#[ORM\UniqueConstraint(name: 'notification_preference_unique', columns: ['recipient_type', 'recipient_id', 'category', 'channel'])]
class NotificationPreference extends AbstractEntity
{
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $recipientType,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private string $recipientId,
        #[ORM\Column(type: Types::STRING, length: 128)]
        private string $category,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private string $channel,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $enabled,
    ) {
        parent::__construct();
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}
