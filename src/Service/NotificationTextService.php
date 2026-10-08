<?php

namespace Wexample\SymfonyNotification\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Wexample\SymfonyNotification\Entity\Notification;

/**
 * What a notification says, in the recipient's language, and where it leads:
 * the same for the inbox, the mail and the chat.
 */
class NotificationTextService
{
    public function __construct(
        private readonly NotificationCategoryRegistry $categories,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getTitle(Notification $notification, ?string $locale = null): string
    {
        return $this->translate($this->categories->get($notification->getCategory())->title, $notification, $locale);
    }

    public function getBody(Notification $notification, ?string $locale = null): ?string
    {
        $body = $this->categories->get($notification->getCategory())->body;

        return $body === null ? null : $this->translate($body, $notification, $locale);
    }

    /**
     * The page of its subject, where the category names a route and the
     * notification has a subject; null otherwise.
     */
    public function getUrl(Notification $notification, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $route = $this->categories->get($notification->getCategory())->route;

        if ($route === null || ! $notification->hasLinkedEntity()) {
            return null;
        }

        try {
            return $this->urlGenerator->generate($route, ['id' => (string) $notification->getEntityId()], $referenceType);
        } catch (Throwable) {
            return null;
        }
    }

    private function translate(string $id, Notification $notification, ?string $locale): string
    {
        $parameters = [];

        foreach ($notification->getParameters() as $key => $value) {
            $parameters['%' . trim($key, '%') . '%'] = $value;
        }

        return $this->translator->trans($id, $parameters, locale: $locale);
    }
}
