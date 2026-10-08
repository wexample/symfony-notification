<?php

namespace Wexample\SymfonyNotification\Class;

/**
 * One kind of notification, as `wexample_symfony_notification.categories`
 * declares it.
 */
final readonly class NotificationCategory
{
    public const string LEVEL_INFO = 'info';
    public const string LEVEL_SUCCESS = 'success';
    public const string LEVEL_WARNING = 'warning';
    public const string LEVEL_DANGER = 'danger';

    /** The levels of the design system's messages and markers. */
    public const array LEVELS = [self::LEVEL_INFO, self::LEVEL_SUCCESS, self::LEVEL_WARNING, self::LEVEL_DANGER];

    /**
     * @param list<string> $roles
     * @param list<string> $channels
     * @param list<string> $broadcast
     */
    public function __construct(
        public string $name,
        public string $title,
        public ?string $body = null,
        public string $level = self::LEVEL_INFO,
        public ?string $group = null,
        public array $roles = [],
        public array $channels = [],
        public array $broadcast = [],
        public ?string $route = null,
    ) {
    }
}
