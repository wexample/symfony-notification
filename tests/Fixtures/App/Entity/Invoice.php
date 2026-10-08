<?php

namespace Wexample\SymfonyNotification\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * What a notification is about.
 */
#[ORM\Entity]
class Invoice extends AbstractEntity
{
    public function __construct(
        #[ORM\Column(type: Types::STRING)]
        private string $number,
    ) {
        parent::__construct();
    }

    public function getNumber(): string
    {
        return $this->number;
    }
}
