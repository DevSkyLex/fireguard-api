<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\{NotificationId, NotificationType};
use Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

use function json_decode;

/**
 * Test NotificationApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationApiTest extends WebTestCase
{
  #[Test]
  public function testListNotificationsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/notifications');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /notifications endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /notifications, got ' . $statusCode);
  }

  #[Test]
  public function testGetUnreadNotificationsCountRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/notifications/unread-count');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /notifications/unread-count endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /notifications/unread-count, got ' . $statusCode);
  }

  #[Test]
  public function testGetUnreadNotificationsCountWithOrganizationFilterRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/notifications/unread-count?organization=550e8400-e29b-41d4-a716-446655440000');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /notifications/unread-count?organization=... endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated request, got ' . $statusCode);
  }

  #[Test]
  public function testMarkAllNotificationsAsReadRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/notifications/read-all', server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /notifications/read-all endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /notifications/read-all, got ' . $statusCode);
  }

  #[Test]
  public function testMarkAllNotificationsAsReadIsNotSwallowedByTheItemRoute(): void
  {
    // Regression guard for the deliberate route ordering constraint:
    // /read-all must not be interpreted as a notification with id "read-all"
    // by the /{id}/read route.
    $client = static::createClient();

    $client->request('PATCH', '/api/notifications/read-all/read');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      404,
      $statusCode,
      'PATCH /notifications/{id}/read should still resolve as a route (with id="read-all"), confirming /read-all itself is matched first for its own route',
    );
  }

  #[Test]
  public function testGetNotificationByIdStillRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/notifications/550e8400-e29b-41d4-a716-446655440000');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /notifications/{id} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /notifications/{id}, got ' . $statusCode);
  }

  #[Test]
  public function testGetNotificationPreferencesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/notifications/preferences');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /notifications/preferences endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /notifications/preferences, got ' . $statusCode);
  }

  #[Test]
  public function testUpdateNotificationPreferencesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/notifications/preferences', server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"preferences":[{"category":"organization","emailEnabled":false,"mercureEnabled":true}]}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /notifications/preferences endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated PATCH /notifications/preferences, got ' . $statusCode);
  }

  #[Test]
  public function testNotificationPreferencesIsNotSwallowedByTheItemRoute(): void
  {
    // Regression guard for the deliberate route ordering constraint: a
    // notification "read" sub-path with id="preferences" must still resolve
    // to /{id}/read, confirming /preferences itself is matched first for its
    // own dedicated route rather than being captured by /{id}.
    $client = static::createClient();

    $client->request('PATCH', '/api/notifications/preferences/read');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      404,
      $statusCode,
      'PATCH /notifications/{id}/read should still resolve as a route (with id="preferences"), confirming /preferences itself is matched first for its own route',
    );
  }

  #[Test]
  public function testGetInboxRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/inbox');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /inbox endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /inbox, got ' . $statusCode);
  }

  #[Test]
  public function testGetInboxUnreadCountRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/inbox/unread-count');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /inbox/unread-count endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403 for unauthenticated GET /inbox/unread-count, got ' . $statusCode);
  }

  /**
   * `GET /api/inbox/unread-count` must sum through the unified inbox seam
   * (`InboxAggregator::countUnread()`) rather than reimplementing the
   * count — this test seeds notifications directly through the repository
   * (bypassing HTTP) and checks the number the endpoint returns matches the
   * same figure `GET /notifications/unread-count` already exposes, since
   * Notification is still the only registered `inbox.source_provider`.
   *
   * Mirrors every other authenticated functional test in this file/suite:
   * exactly one authenticated request per test (`loginUser()` on this
   * codebase's stateless `api` firewall reliably authenticates only the
   * NEXT request — the organization-scoped assertion therefore lives in its
   * own sibling test below, not as a second request here).
   */
  #[Test]
  public function testGetInboxUnreadCountSumsUnreadNotificationsForTheAuthenticatedUser(): void
  {
    $client = static::createClient();

    /** @var NotificationRepository $notificationRepository */
    $notificationRepository = static::getContainer()->get(NotificationRepository::class);

    $userId = '550e8400-e29b-41d4-a716-446655449960';
    $organizationId = '550e8400-e29b-41d4-a716-446655449961';

    $this->seedInboxUnreadCountNotifications($notificationRepository, $userId, $organizationId);

    $user = new SecurityUser(
      id: $userId,
      email: 'inbox-unread-count-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request('GET', '/api/inbox/unread-count', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Inbox unread-count request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame(2, $decoded['unreadCount'] ?? null, '2 unread org notification + 2 unread account-level notification (1 already read) = 2.');
  }

  /**
   * @see testGetInboxUnreadCountSumsUnreadNotificationsForTheAuthenticatedUser
   */
  #[Test]
  public function testGetInboxUnreadCountHonoursOrganizationFilter(): void
  {
    $client = static::createClient();

    /** @var NotificationRepository $notificationRepository */
    $notificationRepository = static::getContainer()->get(NotificationRepository::class);

    $userId = '550e8400-e29b-41d4-a716-446655449962';
    $organizationId = '550e8400-e29b-41d4-a716-446655449963';

    $this->seedInboxUnreadCountNotifications($notificationRepository, $userId, $organizationId);

    $user = new SecurityUser(
      id: $userId,
      email: 'inbox-unread-count-scoped-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request('GET', '/api/inbox/unread-count?organization=' . $organizationId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Scoped inbox unread-count request should succeed. Response: ' . $response->getContent());

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame(1, $decoded['unreadCount'] ?? null, 'Only the 1 unread org-scoped notification should be counted.');
  }

  /**
   * Seeds one unread org-scoped notification, one unread account-level
   * notification, and one already-read account-level notification for the
   * given user — the shared fixture behind both `/inbox/unread-count`
   * assertions above.
   */
  private function seedInboxUnreadCountNotifications(NotificationRepository $notificationRepository, string $userId, string $organizationId): void
  {
    $unreadScoped = Notification::create(
      id: NotificationId::fromString(Uuid::v4()->toRfc4122()),
      type: NotificationType::ORGANIZATION_INVITATION,
      subject: 'Inbox unread-count test (org-scoped)',
      body: '<p>Body</p>',
      channels: ['mercure'],
      recipientUserId: $userId,
      organizationId: $organizationId,
    );
    $notificationRepository->save($unreadScoped);

    $unreadAccountLevel = Notification::create(
      id: NotificationId::fromString(Uuid::v4()->toRfc4122()),
      type: NotificationType::ORGANIZATION_INVITATION,
      subject: 'Inbox unread-count test (account-level)',
      body: '<p>Body</p>',
      channels: ['mercure'],
      recipientUserId: $userId,
      organizationId: null,
    );
    $notificationRepository->save($unreadAccountLevel);

    $alreadyRead = Notification::create(
      id: NotificationId::fromString(Uuid::v4()->toRfc4122()),
      type: NotificationType::ORGANIZATION_INVITATION,
      subject: 'Inbox unread-count test (already read)',
      body: '<p>Body</p>',
      channels: ['mercure'],
      recipientUserId: $userId,
      organizationId: null,
    );
    $alreadyRead->markAsRead();
    $notificationRepository->save($alreadyRead);
  }
}
