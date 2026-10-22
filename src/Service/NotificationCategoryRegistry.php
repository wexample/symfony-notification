<?php

namespace Wexample\SymfonyNotification\Service;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyNotification\Class\NotificationCategory;

/**
 * The categories the application declared, and who may receive each.
 */
class NotificationCategoryRegistry
{
    /** @var array<string, NotificationCategory> */
    private readonly array $categories;

    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        #[Autowire(param: 'wexample_symfony_notification.categories')]
        array $categories = [],
    ) {
        $built = [];

        foreach ($categories as $name => $category) {
            $built[$name] = new NotificationCategory(...[...$category, 'name' => $name]);
        }

        $this->categories = $built;
    }

    /**
     * @return array<string, NotificationCategory>
     */
    public function all(): array
    {
        return $this->categories;
    }

    public function has(string $name): bool
    {
        return isset($this->categories[$name]);
    }

    /**
     * @throws InvalidArgumentException for a category the application did not declare
     */
    public function get(string $name): NotificationCategory
    {
        return $this->categories[$name] ?? throw new InvalidArgumentException(sprintf(
            'Unknown notification category "%s"; declared: %s.',
            $name,
            implode(', ', array_keys($this->categories)) ?: 'none'
        ));
    }

    /**
     * Whether $recipient may receive it: holds one of its roles, through the
     * hierarchy. A recipient that is no user receives what has no role.
     */
    public function mayReceive(NotificationCategory $category, object $recipient): bool
    {
        if (! $category->roles) {
            return true;
        }

        if (! $recipient instanceof UserInterface) {
            return false;
        }

        return (bool) array_intersect($category->roles, $this->roleHierarchy->getReachableRoleNames($recipient->getRoles()));
    }

    /**
     * The categories $recipient may receive.
     *
     * @return array<string, NotificationCategory>
     */
    public function forRecipient(object $recipient): array
    {
        return array_filter($this->categories, fn (NotificationCategory $category) => $this->mayReceive($category, $recipient));
    }
}
