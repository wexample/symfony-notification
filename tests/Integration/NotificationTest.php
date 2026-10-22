<?php

namespace Wexample\SymfonyNotification\Tests\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Wexample\SymfonyMail\Service\MailboxService;
use Wexample\SymfonyNotification\Entity\Notification;
use Wexample\SymfonyNotification\Service\NotificationInboxService;
use Wexample\SymfonyNotification\Service\NotificationPreferenceService;
use Wexample\SymfonyNotification\Service\NotificationService;
use Wexample\SymfonyNotification\Tests\Fixtures\App\Entity\Invoice;
use Wexample\SymfonyNotification\Tests\Fixtures\App\Entity\Member;
use Wexample\SymfonyNotification\Tests\Fixtures\App\Service\RecordingChatter;

/**
 * network's `bill_validated`, as the fixture declares it: every member told
 * in the inbox and by mail, the room and the webhook once, and whoever turned
 * the mail off told in the inbox only.
 */
class NotificationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /** @var list<array{string, string, array}> */
    private array $requests = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->getMailbox()->purge();

        self::getContainer()->get(\Symfony\Contracts\HttpClient\HttpClientInterface::class)->setResponseFactory(
            function (string $method, string $url, array $options) {
                $this->requests[] = [$method, $url, $options];

                return new MockResponse('{}');
            }
        );
    }

    public function testEachMemberIsToldThroughItsChannelsAndTheRoomOnce(): void
    {
        [$ada, $bob] = [$this->member('ada@example.com'), $this->member('bob@example.com')];
        $invoice = $this->invoice('F-12');

        $created = $this->getNotifications()->notify('invoice.validated', [$ada, $bob, $ada], $invoice, ['number' => 'F-12', 'actor' => 'Cleo'], $bob);

        $this->assertCount(2, $created);
        $this->assertSame('success', $created[0]->getLevel());
        $this->assertSame((string) $invoice->getId(), (string) $created[0]->getEntityId());
        $this->assertSame(['mail'], $this->reload($created[0])->getChannels());

        $mail = $this->getMailbox()->last('ada@example.com');
        $this->assertSame('Invoice F-12 validated', $mail->subject);
        $this->assertStringContainsString('Cleo validated it.', $mail->html);

        $chatter = $this->getChatter();
        $this->assertCount(1, $chatter->messages);
        $this->assertStringStartsWith('Invoice F-12 validated', $chatter->messages[0]->getSubject());

        $this->assertCount(1, $this->requests);
        [$method, $url, $options] = $this->requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame('https://n8n.test/webhook/notifications', $url);
        $payload = json_decode($options['body'], true);
        $this->assertSame('invoice.validated', $payload['notification']);
        $this->assertSame(['number' => 'F-12', 'actor' => 'Cleo'], $payload['data']);
        $this->assertContains('Authorization: Basic ' . base64_encode('robot:secret'), $options['headers']);
    }

    public function testAMemberWhoTurnedTheMailOffIsToldInTheInboxOnly(): void
    {
        $ada = $this->member('ada@example.com');
        $this->getPreferences()->set($ada, 'invoice.validated', 'mail', false);

        $created = $this->getNotifications()->notify('invoice.validated', [$ada], $this->invoice('F-13'), ['number' => 'F-13', 'actor' => 'Cleo']);

        $this->assertCount(1, $created);
        $this->assertSame([], $this->reload($created[0])->getChannels());
        $this->assertNull($this->getMailbox()->last('ada@example.com'));

        $this->signIn($ada);
        $this->assertSame(1, $this->getInbox()->countUnread());
    }

    public function testTheLinkOfTheMailTurnsTheMailOffWithoutSigningIn(): void
    {
        $ada = $this->member('ada@example.com');
        $this->getNotifications()->notify('invoice.due', [$ada], $this->invoice('F-14'), ['number' => 'F-14']);

        $link = $this->getMailbox()->last('ada@example.com')->getFirstLink();
        $this->assertNotNull($link);

        $this->assertNull($this->getPreferences()->unsubscribe(Request::create($link . 'tampered')));
        $this->assertTrue($this->getPreferences()->getChoices($ada, $this->categories())['invoice.due']['mail']);

        $this->assertNotNull($this->getPreferences()->unsubscribe(Request::create($link)));
        $this->assertFalse($this->getPreferences()->getChoices($ada, $this->categories())['invoice.due']['mail']);
    }

    public function testAnUrlParameterIsTheLinkOfACategoryWithoutRoute(): void
    {
        $ada = $this->member('ada@example.com');
        $this->getNotifications()->notify('invoice.due', [$ada], $this->invoice('F-21'), ['number' => 'F-21', 'url' => 'https://app.test/signed?_hash=x']);

        $this->assertSame('https://app.test/signed?_hash=x', $this->getMailbox()->last('ada@example.com')->getFirstLink());
    }

    public function testACategoryWithRolesReachesTheirHoldersOnly(): void
    {
        $treasurer = $this->member('tom@example.com', ['ROLE_TREASURER']);
        $member = $this->member('may@example.com');

        $created = $this->getNotifications()->notify('treasury.report', [$treasurer, $member]);

        $this->assertCount(1, $created);
        $this->assertSame((string) $treasurer->getId(), $created[0]->getRecipientId());
    }

    public function testAReminderReachesEachRecipientOncePerWindow(): void
    {
        $ada = $this->member('ada@example.com');
        $invoice = $this->invoice('F-15');
        $notifications = $this->getNotifications();

        // Two months before the date: told; again the same day, not.
        $this->assertCount(1, $notifications->notifyOnce('invoice.due', [$ada], $invoice, new DateTimeImmutable('-1 day'), ['number' => 'F-15']));
        $this->assertCount(0, $notifications->notifyOnce('invoice.due', [$ada], $invoice, new DateTimeImmutable('-1 day'), ['number' => 'F-15']));

        // Another invoice is another subject.
        $this->assertCount(1, $notifications->notifyOnce('invoice.due', [$ada], $this->invoice('F-16'), new DateTimeImmutable('-1 day'), ['number' => 'F-16']));

        // The next window starts after the last notification: told again.
        $this->assertCount(1, $notifications->notifyOnce('invoice.due', [$ada], $invoice, new DateTimeImmutable('+1 second'), ['number' => 'F-15']));
    }

    public function testAChannelFailingStopsNeitherTheOthersNorTheInbox(): void
    {
        self::getContainer()->get(\Symfony\Contracts\HttpClient\HttpClientInterface::class)->setResponseFactory(
            new MockResponse('down', ['http_code' => 503])
        );
        $ada = $this->member('ada@example.com');

        $created = $this->getNotifications()->notify('invoice.validated', [$ada], $this->invoice('F-17'), ['number' => 'F-17', 'actor' => 'Cleo']);

        $this->assertCount(1, $created);
        $this->assertNotNull($this->getMailbox()->last('ada@example.com'));
        $this->assertCount(1, $this->getChatter()->messages);
    }

    public function testTheInboxIsTheSignedInMembersOwn(): void
    {
        [$ada, $bob] = [$this->member('ada@example.com'), $this->member('bob@example.com')];
        $notifications = $this->getNotifications();
        $notifications->notify('invoice.due', [$ada], $this->invoice('F-18'), ['number' => 'F-18']);
        $notifications->notify('invoice.due', [$ada], $this->invoice('F-19'), ['number' => 'F-19']);
        [$bobs] = $notifications->notify('invoice.due', [$bob], $this->invoice('F-20'), ['number' => 'F-20']);

        $this->signIn($ada);
        $inbox = $this->getInbox();

        $page = $inbox->paginate(1, 10);
        $this->assertSame(2, $page['count']);
        $this->assertNull($inbox->markRead((string) $bobs->getId()));

        $inbox->markRead((string) $page['notifications'][0]->getId());
        $this->assertSame(1, $inbox->countUnread());

        $inbox->markAllRead();
        $this->assertSame(0, $inbox->countUnread());
    }

    public function testAnUndeclaredCategoryIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->getNotifications()->notify('invoice.lost', [$this->member('ada@example.com')]);
    }

    private function member(string $email, array $roles = []): Member
    {
        $member = new Member($email, $roles);
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $member;
    }

    private function invoice(string $number): Invoice
    {
        $invoice = new Invoice($number);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function reload(Notification $notification): Notification
    {
        $this->entityManager->refresh($notification);

        return $notification;
    }

    private function signIn(Member $member): void
    {
        self::getContainer()->get('test.token_storage')->setToken(new UsernamePasswordToken($member, 'main', $member->getRoles()));
    }

    private function categories(): array
    {
        return self::getContainer()->get(\Wexample\SymfonyNotification\Service\NotificationCategoryRegistry::class)->all();
    }

    private function getNotifications(): NotificationService
    {
        return self::getContainer()->get('test.notifications');
    }

    private function getPreferences(): NotificationPreferenceService
    {
        return self::getContainer()->get('test.preferences');
    }

    private function getInbox(): NotificationInboxService
    {
        return self::getContainer()->get('test.inbox');
    }

    private function getMailbox(): MailboxService
    {
        return self::getContainer()->get('test.mailbox');
    }

    private function getChatter(): RecordingChatter
    {
        return self::getContainer()->get(\Symfony\Component\Notifier\ChatterInterface::class);
    }
}
