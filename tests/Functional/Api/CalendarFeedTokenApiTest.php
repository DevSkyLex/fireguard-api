<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test CalendarFeedTokenApiTest.
 *
 * Contract-shape checks for the member feed token endpoints, mirroring
 * {@see CalendarApiTest}: the three management routes exist and require
 * authentication, and the public `.ics` route exists, is reachable WITHOUT
 * credentials, and answers 404 (not 401/403) for an unknown token — the
 * anti-oracle contract.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testCreateFeedTokenRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/calendar/feed-token', server: [
      'CONTENT_TYPE' => 'application/json',
    ]);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'POST /organizations/{organizationId}/calendar/feed-token endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testGetFeedTokenMetadataRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/calendar/feed-token');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'GET /organizations/{organizationId}/calendar/feed-token endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testDeleteFeedTokenRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/calendar/feed-token');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(404, $statusCode, 'DELETE /organizations/{organizationId}/calendar/feed-token endpoint should exist (got 404)');
    self::assertContains($statusCode, [401, 403], 'Expected 401 or 403, got ' . $statusCode);
  }

  #[Test]
  public function testIcsFeedIsPublicAndAnswersAUniformNotFoundForAnUnknownToken(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/calendar/feed/definitely-not-a-real-token.ics');

    $statusCode = $client->getResponse()->getStatusCode();

    // The route must be PUBLIC: an unauthenticated request may never bounce
    // on 401/403 — an unknown token answers a plain 404, with no oracle.
    self::assertSame(404, $statusCode, 'Expected a uniform 404 for an unknown token on the public .ics route, got ' . $statusCode);
  }
}
