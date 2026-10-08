<?php

namespace Wexample\SymfonyNotification\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyHelpers\Entity\Traits\LinkedToEntityTrait;
use Wexample\SymfonyNotification\Repository\NotificationRepository;

/**
 * What one person was told: its category, about which entity — its subject —,
 * by whom, read or not. One per recipient, kept in the inbox whatever the
 * channels it also went through.
 *
 * The recipient and the actor are kept by type and id, the subject through
 * LinkedToEntityTrait: any entity can receive one or be its subject.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(name: 'notification_recipient', columns: ['recipient_type', 'recipient_id', 'date_created'])]
#[ORM\Index(name: 'notification_subject', columns: ['category', 'entity_type', 'entity_id'])]
class Notification extends AbstractEntity
{
    use HasDateCreatedTrait;
    use LinkedToEntityTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $recipientType;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $recipientId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $actorType = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $actorId = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $category;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $level;

    /**
     * What its texts are read with: `%team%`, `%member%`.
     *
     * @var array<string, scalar|null>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $parameters = [];

    /**
     * The personal channels it went out through, beside the inbox.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $channels = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dateRead = null;

    /**
     * @param array<string, scalar|null> $parameters
     */
    public function __construct(
        string $category,
        string $level,
        string $recipientType,
        string $recipientId,
        array $parameters = [],
    ) {
        parent::__construct();

        $this->category = $category;
        $this->level = $level;
        $this->recipientType = $recipientType;
        $this->recipientId = $recipientId;
        $this->parameters = $parameters;
        $this->setDateCreatedNow();
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getRecipientType(): string
    {
        return $this->recipientType;
    }

    public function getRecipientId(): string
    {
        return $this->recipientId;
    }

    public function getActorType(): ?string
    {
        return $this->actorType;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function setActor(?string $type, ?string $id): static
    {
        $this->actorType = $type;
        $this->actorId = $id;

        return $this;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return list<string>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    public function addChannel(string $channel): static
    {
        $this->channels = array_values(array_unique([...$this->channels, $channel]));

        return $this;
    }

    public function isRead(): bool
    {
        return $this->dateRead !== null;
    }

    public function getDateRead(): ?DateTimeImmutable
    {
        return $this->dateRead;
    }

    public function markRead(): static
    {
        $this->dateRead ??= new DateTimeImmutable();

        return $this;
    }
}
