<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use DateTimeImmutable;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function json_encode;
use function str_contains;

/**
 * Test CalendarFeedTokenFlow.
 *
 * Exercises the whole member iCal subscription lifecycle end-to-end:
 * create the token (201, secret shown once, complete feed URL), read the
 * metadata (no secret), fetch the `.ics` feed WITHOUT any credentials
 * (200, RFC 5545 framing, the seeded member's entries), rotate (old
 * secret dies, new one works), revoke (204), then verify the revoked
 * secret answers the same plain 404 an unknown one does.
 *
 * @category E2E Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenFlowTest extends OAuth2WebTestCase
{
  private const string LD_JSON = 'application/ld+json';

  public function testFullFeedTokenLifecycle(): void
  {
    $client = static::createClientWithFixtures();
    $token = $this->loginAndGetUserAccessToken($client, self::SEEDED_ADMIN_EMAIL, self::SEEDED_ADMIN_PASSWORD);
    $organizationId = OrganizationFixtures::ORGANIZATION_ID;

    // An event guarantees at least one feed entry inside the -30d/+180d window.
    $this->createEvent($client, $token, $organizationId, 'Exercice évacuation annuel');

    // 1. Create the feed token: 201, the secret and the full URL, exactly once.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($token),
    );
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), 'Token creation should answer 201. Response: ' . ($response->getContent() ?: ''));
    $created = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $secret = $created['secret'] ?? null;
    self::assertTrue(is_string($secret) && '' !== $secret, 'The 201 must carry the raw secret.');
    self::assertIsString($created['feedUrl'] ?? null, 'The 201 must carry the complete feed URL.');
    self::assertStringContainsString('/api/calendar/feed/' . $secret . '.ics', (string) $created['feedUrl']);
    self::assertFalse($created['rotated'] ?? null, 'A first creation is not a rotation.');

    // 2. Metadata: no secret, only createdAt / lastUsedAt.
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($token),
    );
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $metadata = $this->decodeJsonResponse($response->getContent() ?: '{}');
    self::assertIsString($metadata['createdAt'] ?? null);
    self::assertArrayNotHasKey('secret', $metadata, 'Metadata must never expose the secret.');
    self::assertArrayNotHasKey('tokenHash', $metadata, 'Metadata must never expose the hash.');
    self::assertNull($metadata['lastUsedAt'] ?? null, 'No fetch happened yet.');

    // 3. The .ics feed, with NO Authorization header and no cookie.
    $client->request(method: 'GET', uri: '/api/calendar/feed/' . $secret . '.ics');
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'The subscribed feed must answer 200 without credentials. Response: ' . ($response->getContent() ?: ''));
    self::assertStringContainsString('text/calendar', (string) $response->headers->get('Content-Type'));
    $cacheControl = (string) $response->headers->get('Cache-Control');
    self::assertStringContainsString('private', $cacheControl, 'The feed response must be privately cacheable only.');
    $ics = $response->getContent() ?: '';
    self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
    self::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
    self::assertStringContainsString('BEGIN:VEVENT', $ics, 'The feed must contain the created event.');
    self::assertStringContainsString('vacuation annuel', $ics, 'The created event title must appear in a SUMMARY.');
    self::assertTrue(str_contains($ics, 'SUMMARY:['), 'Every SUMMARY is type-prefixed.');

    // 4. The fetch recorded lastUsedAt (first write is never throttled).
    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($token),
    );
    $metadata = $this->decodeJsonResponse($client->getResponse()->getContent() ?: '{}');
    self::assertIsString($metadata['lastUsedAt'] ?? null, 'The first feed fetch must record lastUsedAt.');

    // 5. Rotation: a new POST revokes the previous secret.
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($token),
    );
    $response = $client->getResponse();
    self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    $rotated = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $newSecret = $rotated['secret'] ?? null;
    self::assertTrue(is_string($newSecret) && '' !== $newSecret && $newSecret !== $secret, 'Rotation must produce a fresh secret.');
    self::assertTrue($rotated['rotated'] ?? null, 'The second POST is a rotation.');

    $client->request(method: 'GET', uri: '/api/calendar/feed/' . $secret . '.ics');
    self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), 'The pre-rotation secret must be dead.');

    $client->request(method: 'GET', uri: '/api/calendar/feed/' . $newSecret . '.ics');
    self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'The post-rotation secret must serve the feed.');

    // 6. Revocation: 204, then the same uniform 404 as an unknown token.
    $client->request(
      method: 'DELETE',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($token),
    );
    self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

    $client->request(method: 'GET', uri: '/api/calendar/feed/' . $newSecret . '.ics');
    self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), 'A revoked secret answers 404, indistinguishable from unknown.');

    $client->request(
      method: 'GET',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($token),
    );
    self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), 'Metadata answers 404 once revoked.');

    $client->request(
      method: 'DELETE',
      uri: '/api/organizations/' . $organizationId . '/calendar/feed-token',
      server: $this->headers($token),
    );
    self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), 'Revoking again answers 404.');
  }

  // #region Helpers

  private function createEvent(
    KernelBrowser $client,
    string $token,
    string $organizationId,
    string $title,
  ): void {
    $client->request(
      method: 'POST',
      uri: '/api/organizations/' . $organizationId . '/calendar/events',
      server: $this->headers($token),
      content: json_encode([
        'title' => $title,
        'startsAt' => new DateTimeImmutable('+7 days')->format('Y-m-d\TH:i:sP'),
        'endsAt' => new DateTimeImmutable('+7 days +2 hours')->format('Y-m-d\TH:i:sP'),
        'allDay' => false,
      ]) ?: '',
    );

    self::assertSame(
      Response::HTTP_CREATED,
      $client->getResponse()->getStatusCode(),
      'Event creation should succeed. Response: ' . ($client->getResponse()->getContent() ?: ''),
    );
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
  private function headers(string $token): array
  {
    return [
      'CONTENT_TYPE' => self::LD_JSON,
      'HTTP_ACCEPT' => self::LD_JSON,
      'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
  }

  // #endregion
}
