<?php

namespace Wexample\SymfonyNotification\Routing;

/**
 * The routes of notifications. The endpoints are this package's; the pages
 * are symfony-notification-ds's, or the application's under these names.
 */
final class NotificationRoute
{
    // Endpoints.
    public const string UNREAD_COUNT = 'notification_unread_count';
    public const string READ = 'notification_read';
    public const string READ_ALL = 'notification_read_all';

    // Pages, and the buttons of the inbox.
    public const string INBOX = 'notification_inbox';
    public const string INBOX_UNREAD = 'notification_inbox_unread';
    public const string INBOX_OPEN = 'notification_inbox_open';
    public const string INBOX_READ_ALL = 'notification_inbox_read_all';
    public const string PREFERENCES = 'notification_preferences';
    public const string UNSUBSCRIBE = 'notification_unsubscribe';
}
