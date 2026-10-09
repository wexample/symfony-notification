<?php

namespace Wexample\SymfonyNotification\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyNotification\Channel\WebhookChannel;
use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;

class WebhookChannelTest extends TestCase
{
    public function testWithoutAnHttpClientNothingIsSent(): void
    {
        $channel = new WebhookChannel(['url' => 'https://n8n.test/hook', 'method' => 'POST', 'username' => null, 'password' => null, 'timeout' => 2.0], 'test');

        $channel->deliver(new NotificationCategory('a', 'title'), new Notification('a', 'info', 'Member', '1'), null);

        $this->addToAssertionCount(1);
    }
}
