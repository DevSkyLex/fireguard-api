<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\ORM\EntityManagerInterface;
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository;

use function rawurlencode;
use function sprintf;

/** The public cursor is opaque, scoped by authentication, and preserves equal timestamps. */
final class InboxPaginationContractTest extends OAuth2WebTestCase
{
  public function testAllTimestampTiesAreReachableAndLegacyDatesRemainAvailable(): void
  {
    $client = self::createClientWithFixtures();
    $identity = $this->authenticateFreshUser($client);
    /** @var NotificationRepository $repository */
    $repository = self::getContainer()->get(NotificationRepository::class);
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $expected = [];
    for ($i = 0; $i < 5; ++$i) {
      $id = sprintf('550e8400-e29b-41d4-a716-44665544998%d', $i);
      $repository->save(Notification::create(NotificationId::fromString($id), NotificationType::USER_EMAIL_VERIFIED, 'Cursor test', 'Body', ['mercure'], recipientUserId: $identity['userId']));
      $manager->getConnection()->executeStatement('UPDATE notifications SET created_at = :at WHERE id = :id', ['at' => '2026-09-20 10:00:00', 'id' => $id]);
      $expected[] = $id;
    }
    $seen = [];
    $cursor = null;
    $server = ['HTTP_AUTHORIZATION' => 'Bearer ' . $identity['token'], 'HTTP_ACCEPT' => 'application/ld+json'];
    for ($page = 0; $page < 3; ++$page) {
      if (null !== $cursor) {
        self::assertIsString($cursor);
      }
      $client->request('GET', '/api/inbox?limit=2' . (null === $cursor ? '' : '&cursor=' . rawurlencode($cursor)), server: $server);
      self::assertResponseIsSuccessful();
      $body = $this->decodeJsonResponse((string) $client->getResponse()->getContent());
      self::assertIsArray($body['items']);
      foreach ($body['items'] as $item) {
        self::assertIsArray($item);
        $seen[] = $item['id'];
      }
      $cursor = $body['nextPageCursor'];
      self::assertSame($page < 2, $body['hasMore']);
    }
    self::assertSame($expected, $seen);
    self::assertNull($cursor);

    $client->request('GET', '/api/inbox?before=2026-09-20T10:00:00%2B00:00', server: $server);
    self::assertResponseIsSuccessful();
    $body = $this->decodeJsonResponse((string) $client->getResponse()->getContent());
    self::assertSame([], $body['items']);

    $client->request('GET', '/api/inbox?cursor=invalid-cursor', server: $server);
    self::assertResponseStatusCodeSame(400);
  }
}
