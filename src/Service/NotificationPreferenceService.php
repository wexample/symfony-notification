<?php

namespace Wexample\SymfonyNotification\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Entity\NotificationPreference;
use Wexample\SymfonyNotification\Repository\NotificationPreferenceRepository;
use Wexample\SymfonyNotification\Repository\NotificationRepository;
use Wexample\SymfonyNotification\Routing\NotificationRoute;

/**
 * Which personal channels each recipient keeps. A category goes out through
 * its channels unless the recipient turned one off: opted out of, never
 * opted into, and the inbox always.
 */
class NotificationPreferenceService
{
    public const string PARAMETER_NOTIFICATION = 'notification';
    public const string PARAMETER_CHANNEL = 'channel';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationPreferenceRepository $preferences,
        private readonly NotificationRepository $notifications,
        private readonly NotificationRecipientService $recipients,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function isEnabled(object $recipient, NotificationCategory $category, string $channel): bool
    {
        if (! in_array($channel, $category->channels, true)) {
            return false;
        }

        [$type, $id] = $this->recipients->key($recipient);

        return $this->preferences->findOne($type, $id, $category->name, $channel)?->isEnabled() ?? true;
    }

    public function set(object $recipient, string $category, string $channel, bool $enabled): void
    {
        [$type, $id] = $this->recipients->key($recipient);
        $this->setByKey($type, $id, $category, $channel, $enabled);
        $this->entityManager->flush();
    }

    /**
     * Each category's channels and whether the recipient keeps them, for a
     * preferences page.
     *
     * @param array<string, NotificationCategory> $categories
     *
     * @return array<string, array<string, bool>>
     */
    public function getChoices(object $recipient, array $categories): array
    {
        [$type, $id] = $this->recipients->key($recipient);
        $stored = [];

        foreach ($this->preferences->findForRecipient($type, $id) as $preference) {
            $stored[$preference->getCategory()][$preference->getChannel()] = $preference->isEnabled();
        }

        $choices = [];

        foreach ($categories as $name => $category) {
            foreach ($category->channels as $channel) {
                $choices[$name][$channel] = $stored[$name][$channel] ?? true;
            }
        }

        return $choices;
    }

    /**
     * A link turning off $channel for the category of $notification, for its
     * recipient: signed, so it works without signing in — from the mail — and
     * for nobody else. Null where no page answers it.
     */
    public function getUnsubscribeUrl(Notification $notification, string $channel): ?string
    {
        if (! $this->urlGenerator->getRouteCollection()->get(NotificationRoute::UNSUBSCRIBE)) {
            return null;
        }

        return $this->uriSigner->sign($this->urlGenerator->generate(NotificationRoute::UNSUBSCRIBE, [
            self::PARAMETER_NOTIFICATION => (string) $notification->getId(),
            self::PARAMETER_CHANNEL => $channel,
        ], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * Turns off what a link of getUnsubscribeUrl() names. Null for a link not
     * signed here, or naming nothing.
     */
    public function unsubscribe(Request $request): ?Notification
    {
        if (! $this->uriSigner->checkRequest($request)) {
            return null;
        }

        $notification = $this->notifications->find((string) $request->query->get(self::PARAMETER_NOTIFICATION));
        $channel = (string) $request->query->get(self::PARAMETER_CHANNEL);

        if (! $notification || $channel === '') {
            return null;
        }

        $this->setByKey($notification->getRecipientType(), $notification->getRecipientId(), $notification->getCategory(), $channel, false);
        $this->entityManager->flush();

        return $notification;
    }

    private function setByKey(string $type, string $id, string $category, string $channel, bool $enabled): void
    {
        $preference = $this->preferences->findOne($type, $id, $category, $channel);

        if ($preference) {
            $preference->setEnabled($enabled);

            return;
        }

        $this->entityManager->persist(new NotificationPreference($type, $id, $category, $channel, $enabled));
    }
}
