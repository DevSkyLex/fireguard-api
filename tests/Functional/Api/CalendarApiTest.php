<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CalendarApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCreateCalendarEventRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/calendar/events', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"title":"Fire drill","startsAt":"2026-08-01T09:00:00+02:00"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /organizations/{organizationId}/calendar/events endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{organizationId}/calendar/events, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetCalendarEventRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/calendar/events/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/calendar/events/{eventId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testUpdateCalendarEventRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/organizations/' . self::DUMMY_UUID . '/calendar/events/' . self::DUMMY_UUID_2, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"title":"Updated"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'PATCH /organizations/{organizationId}/calendar/events/{eventId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testDeleteCalendarEventRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/calendar/events/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /organizations/{organizationId}/calendar/events/{eventId} endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testGetCalendarFeedRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/calendar/feed?from=2026-08-01T00:00:00Z&to=2026-08-31T23:59:59Z');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/calendar/feed endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }
}
