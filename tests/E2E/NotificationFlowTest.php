<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Notification\Application\Contract\Notification\NotificationType;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

use function is_int;

/**
 * Test NotificationFlowTest.
 *
 * End-to-end tests for the Notification module's pagination, organization
 * scoping, unread count, and mark-all-read endpoints (lot L1.6).
 *
 * @category E2E Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationFlowTest extends OAuth2WebTestCase
{
  /**
   * Id of the `test-user` fixture (see `User\Infrastructure\DataFixtures\UserFixtures`).
   * Used as an "other user" to assert cross-user isolation.
   */
  private const string OTHER_USER_ID = 'b2c3d4e5-f6a7-4901-8cde-f23456789012';

  // #region Setup
  protected function setUp(): void
  {
    parent::setUp();
    // Reset token cache between tests since fixtures are reloaded.
    $this->accessToken = null;
  }
  // #endregion

  // #region List Pagination & Organization Scoping

  public function testListNotificationsIsPaginatedAndFiltersByOrganization(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);
    self::assertNotNull($token, 'Should be able to obtain access token.');

    $userId = self::DEV_CLIENT_ID;
    $organizationId = Uuid::v4()->toRfc4122();

    for ($i = 0; $i < 3; ++$i) {
      $this->persistNotification($userId, $organizationId);
    }
    for ($i = 0; $i < 2; ++$i) {
      $this->persistNotification($userId, null);
    }

    // Page 1 of 2, itemsPerPage=2: exactly 2 items, total across all pages is 5.
    $client->request(
      method: 'GET',
      uri: '/api/notifications?itemsPerPage=2&page=1',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    self::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'List should succeed. Response: ' . $response->getContent());

    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertArrayHasKey('member', $data);
    self::assertIsArray($data['member']);
    self::assertCount(2, $data['member'], 'itemsPerPage=2 should return exactly 2 items on page 1.');
    self::assertSame(5, $this->extractTotalItems($data), 'Total must count every matching notification across all pages, not just the current page.');

    // A request WITHOUT the organization filter still returns everything,
    // including the account-level (no-organization) ones.
    $client->request(
      method: 'GET',
      uri: '/api/notifications?itemsPerPage=50',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );
    $allData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(5, $this->extractTotalItems($allData));

    // Filtering by organization only returns the 3 org-scoped notifications.
    $client->request(
      method: 'GET',
      uri: '/api/notifications?organization=' . $organizationId . '&itemsPerPage=50',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $scopedData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(3, $this->extractTotalItems($scopedData));
    self::assertIsArray($scopedData['member']);
    self::assertCount(3, $scopedData['member']);
    foreach ($scopedData['member'] as $item) {
      self::assertIsArray($item);
      self::assertSame($organizationId, $item['organizationId'] ?? null);
    }
  }

  // #endregion

  // #region Unread Count

  public function testUnreadNotificationsCountHonoursOrganizationFilter(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);
    self::assertNotNull($token, 'Should be able to obtain access token.');

    $userId = self::DEV_CLIENT_ID;
    $organizationId = Uuid::v4()->toRfc4122();

    $this->persistNotification($userId, $organizationId);
    $this->persistNotification($userId, $organizationId);
    $this->persistNotification($userId, $organizationId, isRead: true);
    $this->persistNotification($userId, null);
    $this->persistNotification($userId, null);
    $this->persistNotification($userId, null);

    $client->request(
      method: 'GET',
      uri: '/api/notifications/unread-count',
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $response = $client->getResponse();
    self::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'Response: ' . $response->getContent());
    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertSame(5, $data['count'] ?? null, '2 unread org notifications + 3 unread account-level notifications = 5.');

    $client->request(
      method: 'GET',
      uri: '/api/notifications/unread-count?organization=' . $organizationId,
      server: [
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
    );

    $scopedData = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(2, $scopedData['count'] ?? null, 'Only the 2 unread org-scoped notifications should be counted.');
  }

  // #endregion

  // #region Mark All As Read

  public function testMarkAllNotificationsAsReadIsBulkScopedIdempotentAndLeavesOtherUsersUntouched(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->getAccessToken($client);
    self::assertNotNull($token, 'Should be able to obtain access token.');

    $userId = self::DEV_CLIENT_ID;
    $organizationId = Uuid::v4()->toRfc4122();

    $this->persistNotification($userId, $organizationId);
    $this->persistNotification($userId, $organizationId);
    $this->persistNotification($userId, null);
    $this->persistNotification($userId, null);
    // Another user's unread notification in the same organization must never
    // be touched by this user's read-all call.
    $this->persistNotification(self::OTHER_USER_ID, $organizationId);

    $repository = $this->notificationRepository();

    self::assertSame(1, $repository->countUnreadByUserId(self::OTHER_USER_ID, $organizationId));

    // Scoped read-all: only the 2 org-scoped notifications of the current user.
    $client->request(
      method: 'PATCH',
      uri: '/api/notifications/read-all?organization=' . $organizationId,
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $response = $client->getResponse();
    self::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'Response: ' . $response->getContent());
    $data = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertSame(2, $data['count'] ?? null, 'Only the 2 org-scoped unread notifications of the current user should be marked as read.');

    // The 2 no-organization notifications of the same user are untouched by the org-scoped call.
    self::assertSame(2, $repository->countUnreadByUserId($userId));
    self::assertSame(0, $repository->countUnreadByUserId($userId, $organizationId));

    // Another user's notification in the very same organization is untouched.
    self::assertSame(1, $repository->countUnreadByUserId(self::OTHER_USER_ID, $organizationId));

    // Global read-all (no organization filter) marks the remaining 2 as read.
    $client->request(
      method: 'PATCH',
      uri: '/api/notifications/read-all',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame(2, $data['count'] ?? null);
    self::assertSame(0, $repository->countUnreadByUserId($userId));

    // Calling it again is a no-op (idempotent): zero rows left to update.
    $client->request(
      method: 'PATCH',
      uri: '/api/notifications/read-all',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
      ],
      content: '{}',
    );

    $idempotentResponse = $client->getResponse();
    self::assertSame(Response::HTTP_OK, $idempotentResponse->getStatusCode());
    $idempotentData = $this->decodeJsonResponse($idempotentResponse->getContent() ?: '{}');
    self::assertSame(0, $idempotentData['count'] ?? null);

    // The other user's notification was never touched by any of the calls above.
    self::assertSame(1, $repository->countUnreadByUserId(self::OTHER_USER_ID, $organizationId));
  }

  public function testMarkAllNotificationsAsReadRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'PATCH',
      uri: '/api/notifications/read-all',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: '{}',
    );

    self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
  }

  // #endregion

  // #region Helpers

  /**
   * Persists a notification directly through the repository, bypassing HTTP,
   * so tests can seed precise read/unread and organization-scoped fixtures.
   */
  private function persistNotification(string $userId, ?string $organizationId, bool $isRead = false): void
  {
    $notification = Notification::create(
      id: NotificationId::fromString(Uuid::v4()->toRfc4122()),
      type: NotificationType::ORGANIZATION_INVITATION,
      subject: 'Test notification',
      body: '<p>Body</p>',
      channels: ['mercure'],
      recipientUserId: $userId,
      organizationId: $organizationId,
    );

    if ($isRead) {
      $notification->markAsRead();
    }

    $this->notificationRepository()->save($notification);
  }

  private function notificationRepository(): NotificationRepository
  {
    $repository = static::getContainer()->get(NotificationRepository::class);
    self::assertInstanceOf(NotificationRepository::class, $repository);

    return $repository;
  }

  /**
   * @param array<string, mixed> $data
   */
  private function extractTotalItems(array $data): ?int
  {
    $value = $data['totalItems'] ?? $data['hydra:totalItems'] ?? null;

    return is_int($value) ? $value : null;
  }

  // #endregion
}
