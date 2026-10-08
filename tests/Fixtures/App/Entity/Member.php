<?php

namespace Wexample\SymfonyNotification\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMail\Interface\MailRecipientInterface;

/**
 * Someone notified: signs in, has an address and roles.
 */
#[ORM\Entity]
class Member extends AbstractEntity implements UserInterface, MailRecipientInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
        private string $email,
        #[ORM\Column(type: Types::JSON)]
        private array $roles = [],
    ) {
        parent::__construct();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return [...$this->roles, 'ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
