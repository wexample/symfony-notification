<?php

namespace Wexample\SymfonyNotification\Tests\Integration;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyNotification\Tests\Fixtures\App\Entity\Invoice;
use Wexample\SymfonyNotification\Tests\Fixtures\App\Entity\Member;

/**
 * What a page asks of the inbox without reloading.
 */
class InboxEndpointTest extends WebTestCase
{
    public function testTheCountAndTheReadMarksAreTheSignedInMembersOwn(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $ada = new Member('ada@example.com');
        $bob = new Member('bob@example.com');
        $invoice = new Invoice('F-30');
        array_map($entityManager->persist(...), [$ada, $bob, $invoice]);
        $entityManager->flush();

        $notifications = self::getContainer()->get('test.notifications');
        [$first] = $notifications->notify('treasury.report', [$ada, $bob]) ?: [null];
        $this->assertNull($first, 'Neither holds ROLE_TREASURER.');
        [$adas] = $notifications->notify('invoice.due', [$ada], $invoice, ['number' => 'F-30']);
        $notifications->notify('invoice.due', [$ada], $invoice, ['number' => 'F-30']);
        [$bobs] = $notifications->notify('invoice.due', [$bob], $invoice, ['number' => 'F-30']);

        $client->request('GET', '/_notifications/unread-count');
        $this->assertResponseStatusCodeSame(401);

        $client->loginUser($ada);

        $client->request('GET', '/_notifications/unread-count');
        $this->assertSame(['count' => 2], json_decode($client->getResponse()->getContent(), true));

        $client->request('POST', '/_notifications/' . $bobs->getId() . '/read');
        $this->assertResponseStatusCodeSame(404);

        $client->request('POST', '/_notifications/' . $adas->getId() . '/read');
        $this->assertSame(['count' => 1], json_decode($client->getResponse()->getContent(), true));

        $client->request('POST', '/_notifications/read-all');
        $client->request('GET', '/_notifications/unread-count');
        $this->assertSame(['count' => 0], json_decode($client->getResponse()->getContent(), true));
    }
}
