<?php

namespace Wexample\SymfonyNotification\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyNotification\Routing\NotificationRoute;
use Wexample\SymfonyNotification\Service\NotificationInboxService;

/**
 * What a page asks of the inbox of the signed-in account without reloading:
 * how many are unread, one or all marked read.
 */
#[Route(path: '/_notifications')]
#[IsGranted('IS_AUTHENTICATED')]
final class NotificationController extends AbstractController
{
    #[Route(path: '/unread-count', name: NotificationRoute::UNREAD_COUNT, methods: [Request::METHOD_GET])]
    public function unreadCount(NotificationInboxService $inbox): JsonResponse
    {
        return new JsonResponse(['count' => $inbox->countUnread()]);
    }

    #[Route(path: '/read-all', name: NotificationRoute::READ_ALL, methods: [Request::METHOD_POST])]
    public function readAll(NotificationInboxService $inbox): JsonResponse
    {
        $inbox->markAllRead();

        return new JsonResponse(['count' => 0]);
    }

    #[Route(path: '/{id}/read', name: NotificationRoute::READ, methods: [Request::METHOD_POST])]
    public function read(string $id, NotificationInboxService $inbox): JsonResponse
    {
        if (! $inbox->markRead($id)) {
            throw $this->createNotFoundException();
        }

        return new JsonResponse(['count' => $inbox->countUnread()]);
    }
}
