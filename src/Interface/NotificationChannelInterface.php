<?php

namespace Wexample\SymfonyNotification\Interface;

use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;

/**
 * A way a notification leaves the application. A personal channel — the mail —
 * reaches each recipient, who may turn it off; a broadcast channel — a chat
 * room, a webhook — is sent once per notify(), whoever the recipients. The
 * inbox is no channel: every notification is kept there. The tag follows the
 * class.
 */
interface NotificationChannelInterface
{
    public const string TAG = 'wexample_notification.channel';

    public static function getName(): string;

    public function isPersonal(): bool;

    /**
     * $notification is the recipient's own for a personal channel, the first
     * of the batch for a broadcast one. Failures are the channel's to log:
     * one channel down never stops the others.
     */
    public function deliver(NotificationCategory $category, Notification $notification, ?object $recipient): void;
}
