<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function http_build_query;
use function is_string;
use function json_encode;
use function uniqid;

/**
 * Test CalendarPresentation.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarPresentationFlowTest extends OAuth2WebTestCase
{
  private const string LD_JSON = 'application/ld+json';

  public function testCreateCalendarEventReturnsCreatedEvent(): void
  {
    $client = static::createClientWithFixtures();
    [$token, $organizationId] = $this->authenticatedOrganization($client, 'calendar-create');

    $event = $this->createEvent($client, $token, $organizationId, 'Fire drill');

    self::assertArrayHasKey('id', $event);
    self::assertSame('Fire drill', $event['title'] ?? null);
    self::assertSame($organizationId, $event['organizationId'] ?? null);
    self::assertArrayHasKey('startsAt', $event);
    self::assertArrayHasKey('createdByMemberId', $event);
  }

  public function testGetCalendarEventReturnsEvent(): void
  {
    $client = static::createClientWithFixtures();
    [$token, $organizationId] = $this->authenticatedOrganization($client, 'calendar-get');

    $created = $this->createEvent($client, $token, $organizationId, 'Sprinkler check');
    $eventId = $created['id'] ?? null;
    self::assertIsString($eventId);

    $client->request(
      'GET',
      '/api/organizations/' . $organizationId . '/calendar/events/' . $eventId,
      server: $this->headers($token, self::LD_JSON),
    );

    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $fetched = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertSame($eventId, $fetched['id'] ?? null);
    self::assertSame('Sprinkler check', $fetched['title'] ?? null);
  }

  public function testDeleteCalendarEventReturnsNoContent(): void
  {
    $client = static::createClientWithFixtures();
    [$token, $organizationId] = $this->authenticatedOrganization($client, 'calendar-delete');

    $created = $this->createEvent($client, $token, $organizationId, 'Ephemeral event');
    $eventId = $created['id'] ?? null;
    self::assertIsString($eventId);

    $client->request(
      'DELETE',
      '/api/organizations/' . $organizationId . '/calendar/events/' . $eventId,
      server: $this->headers($token, self::LD_JSON),
    );

    self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
  }

  public function testGetCalendarFeedReturnsMergedFeed(): void
  {
    $client = static::createClientWithFixtures();
    [$token, $organizationId] = $this->authenticatedOrganization($client, 'calendar-feed');

    // Seed a standalone event so the merged feed has at least one entry.
    $this->createEvent($client, $token, $organizationId, 'Feed sample event');

    $query = http_build_query([
      'from' => '2026-08-01T00:00:00+00:00',
      'to' => '2026-08-31T23:59:59+00:00',
    ]);
    $client->request(
      'GET',
      '/api/organizations/' . $organizationId . '/calendar/feed?' . $query,
      server: $this->headers($token, self::LD_JSON),
    );

    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    $feed = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertArrayHasKey('from', $feed);
    self::assertArrayHasKey('to', $feed);
    self::assertArrayHasKey('items', $feed);
    self::assertIsArray($feed['items'] ?? null);
  }

  public function testGetCalendarFeedRejectsMissingDateRange(): void
  {
    $client = static::createClientWithFixtures();
    [$token, $organizationId] = $this->authenticatedOrganization($client, 'calendar-feed-range');

    $client->request(
      'GET',
      '/api/organizations/' . $organizationId . '/calendar/feed',
      server: $this->headers($token, self::LD_JSON),
    );

    self::assertSame(
      Response::HTTP_BAD_REQUEST,
      $client->getResponse()->getStatusCode(),
      'The feed requires both "from" and "to" query filters.',
    );
  }

  public function testCalendarEventDetailRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      'GET',
      '/api/organizations/' . uniqid() . '/calendar/events/' . uniqid(),
    );

    $status = $client->getResponse()->getStatusCode();
    self::assertNotSame(Response::HTTP_NOT_FOUND, $status, 'The route must exist.');
    self::assertContains($status, [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
  }

  public function testCalendarFeedRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      'GET',
      '/api/organizations/' . uniqid() . '/calendar/feed?from=2026-08-01T00:00:00%2B00:00&to=2026-08-31T00:00:00%2B00:00',
    );

    $status = $client->getResponse()->getStatusCode();
    self::assertNotSame(Response::HTTP_NOT_FOUND, $status, 'The route must exist.');
    self::assertContains($status, [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
  }

  public function testCreateCalendarEventRequiresAuthentication(): void
  {
    $client = static::createClientWithFixtures();

    $client->request(
      'POST',
      '/api/organizations/' . uniqid() . '/calendar/events',
      server: ['CONTENT_TYPE' => self::LD_JSON, 'HTTP_ACCEPT' => self::LD_JSON],
      content: json_encode(['title' => 'x', 'startsAt' => '2026-08-01T09:00:00+00:00']) ?: '',
    );

    $status = $client->getResponse()->getStatusCode();
    self::assertNotSame(Response::HTTP_NOT_FOUND, $status, 'The route must exist.');
    self::assertContains($status, [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
  }

  /**
   * Create an activated user, log in, and create an organization they own.
   *
   * @return array{0: string, 1: string} the access token and organization id
   */
  private function authenticatedOrganization(KernelBrowser $client, string $prefix): array
  {
    $email = $prefix . '-' . uniqid() . '@example.com';
    $password = 'OwnerPassword123!';
    $this->createAndActivateUser($client, $email, $password);
    $token = $this->loginAndGetUserAccessToken($client, $email, $password);
    $organizationId = $this->createOrganization($client, $token, 'Calendar Org ' . uniqid());
    self::assertIsString($organizationId);

    return [$token, $organizationId];
  }

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
      'POST',
      '/api/organizations/' . $organizationId . '/calendar/events',
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
      'POST',
      '/api/auth/login',
      server: ['CONTENT_TYPE' => self::LD_JSON, 'HTTP_ACCEPT' => self::LD_JSON],
      content: json_encode(['email' => $email, 'password' => $password]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $token = $data['access_token'] ?? null;
    self::assertIsString($token, 'Login should return an access token.');
    self::assertNotSame('', $token);

    return $token;
  }

  private function createOrganization(KernelBrowser $client, string $token, string $name): ?string
  {
    $client->request(
      'POST',
      '/api/organizations',
      server: $this->headers($token, self::LD_JSON),
      content: json_encode(['name' => $name]) ?: '',
    );

    $data = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    $id = $data['id'] ?? null;

    return is_string($id) && '' !== $id ? $id : null;
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
}
