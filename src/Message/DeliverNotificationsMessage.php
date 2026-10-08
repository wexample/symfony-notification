<?php

namespace Wexample\SymfonyNotification\Message;

/**
 * The notifications of one notify(), to send through their channels. Ids
 * only: the texts are built when sending, in each recipient's language, and
 * never sit in a queue.
 */
final readonly class DeliverNotificationsMessage
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        public array $ids,
    ) {
    }
}
