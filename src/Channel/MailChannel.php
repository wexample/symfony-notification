<?php

namespace Wexample\SymfonyNotification\Channel;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyMail\Interface\MailRecipientInterface;
use Wexample\SymfonyMail\Service\MailSenderService;
use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Interface\NotificationChannelInterface;
use Wexample\SymfonyNotification\Service\NotificationPreferenceService;
use Wexample\SymfonyNotification\Service\NotificationTextService;
use Wexample\SymfonyTranslations\Interface\HasLocaleInterface;

/**
 * The notification by mail, through symfony-mail, in the recipient's
 * language: its title as subject, its text, a link to its subject, and a
 * link to stop receiving this category by mail.
 */
class MailChannel implements NotificationChannelInterface
{
    public const string NAME = 'mail';

    public const string TEMPLATE = '@WexampleSymfonyNotificationBundle/mails/notification.html.twig';

    public function __construct(
        private readonly MailSenderService $sender,
        private readonly NotificationTextService $texts,
        private readonly NotificationPreferenceService $preferences,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function isPersonal(): bool
    {
        return true;
    }

    public function deliver(NotificationCategory $category, Notification $notification, ?object $recipient): void
    {
        if (! $recipient instanceof MailRecipientInterface || ! $recipient->getEmail()) {
            return;
        }

        $locale = $recipient instanceof HasLocaleInterface ? $recipient->getLocale() : null;
        $title = $this->texts->getTitle($notification, $locale);

        $this->sender->sendTo($recipient, (new TemplatedEmail())
            ->subject($title)
            ->htmlTemplate(self::TEMPLATE)
            ->context([
                'title' => $title,
                'body' => $this->texts->getBody($notification, $locale),
                'url' => $this->texts->getUrl($notification, UrlGeneratorInterface::ABSOLUTE_URL),
                'unsubscribe_url' => $this->preferences->getUnsubscribeUrl($notification, self::NAME),
            ]));
    }
}
