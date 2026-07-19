<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}
