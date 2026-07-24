<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;

/**
 * Test CalendarWriteFlow.
 *
 * Exercises the standalone calendar event PATCH endpoint end-to-end so the
 * Presentation-layer UpdateCalendarEventProcessor is covered by an
 * authenticated HTTP flow (create prerequisite via POST, then merge-patch it).
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarWriteFlowTest extends OAuth2WebTestCase
{
  private const string ADMIN_EMAIL = 'admin@fireguard.local';

  private const string ADMIN_PASSWORD = 'Admin123!';

  private const string LD_JSON = 'application/ld+json';

  private const string MERGE_PATCH = 'application/merge-patch+json';

  /**
   * A random UUID used purely to probe route existence on unauthenticated
   * requests. Security is evaluated before the processor runs, so a
   * non-existent identifier still yields 401/403 rather than 404.
   */
  private const string PLACEHOLDER_UUID = '550e8400-e29b-41d4-a716-446655440000';

  // #region UpdateCalendarEventProcessor (calendar_update_event)

  public function testUpdateCalendarEventReturnsUpdatedEvent(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
    $organizationId = OrganizationFixtures::ORGANIZATION_ID;

    $created = $this->createEvent($client, $token, $organizationId, 'Quarterly fire drill');
    $eventId = $created['id'] ?? null;
    self::assertIsString($eventId, 'Event creation should return an id.');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/calendar/events/' . $eventId,
      server: $this->headers($token, self::MERGE_PATCH),
      content: json_encode([
        'title' => 'Rescheduled fire drill',
        'description' => 'Moved to the afternoon slot',
        'startsAt' => '2026-08-10T14:00:00+00:00',
        'endsAt' => '2026-08-10T16:00:00+00:00',
        'allDay' => true,
      ]) ?: '',
    );

    $response = $client->getResponse();
    self::assertSame(
      Response::HTTP_OK,
      $response->getStatusCode(),
      'Updating a calendar event should succeed. Response: ' . ($response->getContent() ?: ''),
    );

    $updated = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertSame($eventId, $updated['id'] ?? null, 'The updated event id must be unchanged.');
    self::assertSame('Rescheduled fire drill', $updated['title'] ?? null);
    self::assertSame('Moved to the afternoon slot', $updated['description'] ?? null);
    self::assertTrue($updated['allDay'] ?? null, 'The allDay flag should reflect the patched value.');
  }

  public function testUpdateCalendarEventRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . OrganizationFixtures::ORGANIZATION_ID . '/calendar/events/' . self::PLACEHOLDER_UUID,
      server: [
        'CONTENT_TYPE' => self::MERGE_PATCH,
        'HTTP_ACCEPT' => self::LD_JSON,
      ],
      content: json_encode(['title' => 'Anonymous edit']) ?: '',
    );

    $status = $client->getResponse()->getStatusCode();
    self::assertNotSame(Response::HTTP_NOT_FOUND, $status, 'The update route must exist.');
    self::assertContains(
      $status,
      [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN],
      'Unauthenticated update must be rejected with 401/403.',
    );
  }

  // #endregion

  // #region Helpers

  /**
   * @return array<string, mixed>
   */
  private function createEvent(
    KernelBrowser $client,
    string $token,
    string $organizationId,
    string $title,
  ): array {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/calendar/events',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode([
        'title' => $title,
        'startsAt' => '2026-08-10T09:00:00+00:00',
        'endsAt' => '2026-08-10T11:00:00+00:00',
        'allDay' => false,
      ]) ?: '',
    );

    self::assertSame(
      Response::HTTP_CREATED,
      $client->getResponse()->getStatusCode(),
      'Event creation should succeed. Response: ' . ($client->getResponse()->getContent() ?: ''),
    );

    return $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
  }

  private function loginAndGetUserAccessToken(KernelBrowser $client, string $email, string $password): string
  {
    $client->request(
      method: 'POST',
      uri: '/api/auth/login',
      server: [
        'CONTENT_TYPE' => self::LD_JSON,
        'HTTP_ACCEPT' => self::LD_JSON,
      ],
      content: json_encode(['email' => $email, 'password' => $password]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;
    self::assertTrue(is_string($token) && '' !== $token, 'Login should return an access token.');

    return $token;
  }

  /**
   * @return array<string, string>
   */
  private function headers(string $token, string $contentType): array
  {
    return [
      'CONTENT_TYPE' => $contentType,
      'HTTP_ACCEPT' => self::LD_JSON,
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
  }

  // #endregion
}
