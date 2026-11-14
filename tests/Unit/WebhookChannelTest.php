<?php

namespace Wexample\SymfonyNotification\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Wexample\SymfonyNotification\Channel\WebhookChannel;
use Wexample\SymfonyNotification\Class\NotificationCategory;
use Wexample\SymfonyNotification\Entity\Notification;

class WebhookChannelTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array}> */
    private array $requests = [];

    public function testWithoutAnHttpClientNothingIsSent(): void
    {
        $channel = new WebhookChannel(['url' => 'https://n8n.test/hook', 'method' => 'POST', 'username' => null, 'password' => null, 'timeout' => 2.0], 'test');

        $channel->deliver(new NotificationCategory('a', 'title'), new Notification('a', 'info', 'Member', '1'), null);

        $this->addToAssertionCount(1);
    }

    public function testWithoutAUrlNothingIsSent(): void
    {
        $this->deliver(['url' => null]);

        $this->assertSame([], $this->requests);
    }

    public function testAPostCarriesThePayloadAsJson(): void
    {
        $this->deliver(['method' => 'POST'], new Notification('invoice.validated', 'success', 'Member', '1', ['number' => 'F-12']));

        $this->assertCount(1, $this->requests);
        ['method' => $method, 'url' => $url, 'options' => $options] = $this->requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame('https://n8n.test/hook', $url);
        $this->assertSame([
            'env' => 'test',
            'notification' => 'invoice.validated',
            'channel' => 'webhook',
            'level' => 'success',
            'subject' => null,
            'data' => ['number' => 'F-12'],
        ], json_decode($options['body'], true));
        $this->assertSame(2.0, $options['timeout']);
        $this->assertFalse($this->hasAuthorization($options));
    }

    public function testAGetCarriesThePayloadInItsQuery(): void
    {
        $this->deliver(['method' => 'GET']);

        ['method' => $method, 'url' => $url, 'options' => $options] = $this->requests[0];
        $this->assertSame('GET', $method);
        $this->assertSame('', (string) ($options['body'] ?? ''));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('invoice.validated', json_decode($query['payload'], true)['notification']);
    }

    public function testCredentialsAreSentAsBasicAuth(): void
    {
        $this->deliver(['username' => 'robot', 'password' => 'secret']);

        $this->assertContains('Authorization: Basic '.base64_encode('robot:secret'), $this->requests[0]['options']['headers']);
    }

    public function testARefusalRaises(): void
    {
        $this->expectException(ClientExceptionInterface::class);

        $this->deliver([], response: new MockResponse('Unauthorized', ['http_code' => 401]));
    }

    public function testAFailingWorkflowRaises(): void
    {
        $this->expectException(ServerExceptionInterface::class);

        $this->deliver([], response: new MockResponse('Bad gateway', ['http_code' => 502]));
    }

    private function deliver(
        array $webhook,
        ?Notification $notification = null,
        ?MockResponse $response = null
    ): void {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response) {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response ?? new MockResponse('{}');
        });
        $channel = new WebhookChannel(
            $webhook + ['url' => 'https://n8n.test/hook', 'method' => 'POST', 'username' => null, 'password' => null, 'timeout' => 2.0],
            'test',
            $http
        );

        $channel->deliver(
            new NotificationCategory('invoice.validated', 'title'),
            $notification ?? new Notification('invoice.validated', 'info', 'Member', '1'),
            null
        );
    }

    private function hasAuthorization(array $options): bool
    {
        foreach ($options['headers'] ?? [] as $header) {
            if (str_starts_with(strtolower($header), 'authorization:')) {
                return true;
            }
        }

        return false;
    }
}
