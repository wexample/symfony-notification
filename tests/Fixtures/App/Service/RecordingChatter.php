<?php

namespace Wexample\SymfonyNotification\Tests\Fixtures\App\Service;

use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

/**
 * A chat room keeping what is posted to it.
 */
class RecordingChatter implements ChatterInterface
{
    /** @var list<MessageInterface> */
    public array $messages = [];

    public function send(MessageInterface $message): ?SentMessage
    {
        $this->messages[] = $message;

        return null;
    }

    public function supports(MessageInterface $message): bool
    {
        return true;
    }

    public function __toString(): string
    {
        return 'recording';
    }
}
