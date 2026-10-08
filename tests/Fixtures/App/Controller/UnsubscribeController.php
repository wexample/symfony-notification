<?php

namespace Wexample\SymfonyNotification\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyNotification\Routing\NotificationRoute;
use Wexample\SymfonyNotification\Service\NotificationPreferenceService;

/**
 * The page symfony-notification-ds draws, reduced to what it does.
 */
final class UnsubscribeController
{
    #[Route(path: '/notifications/unsubscribe', name: NotificationRoute::UNSUBSCRIBE)]
    public function __invoke(Request $request, NotificationPreferenceService $preferences): Response
    {
        return $preferences->unsubscribe($request)
            ? new Response('done')
            : new Response('invalid', Response::HTTP_NOT_FOUND);
    }
}
