<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InterventionRecurrenceApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testCreateInterventionRecurrenceRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/intervention-recurrences', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"organization":"/api/organizations/' . self::DUMMY_UUID . '","template":"/api/intervention-templates/' . self::DUMMY_UUID . '","name":"Quarterly audit","frequency":"quarterly","interval":1,"anchorDate":"2026-01-01T00:00:00+00:00","timezone":"UTC"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /intervention-recurrences endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /intervention-recurrences, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListInterventionRecurrencesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/intervention-recurrences?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /intervention-recurrences endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /intervention-recurrences, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetInterventionRecurrenceRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/intervention-recurrences/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /intervention-recurrences/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /intervention-recurrences/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testPatchInterventionRecurrenceRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/intervention-recurrences/' . self::DUMMY_UUID, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"isActive":false}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /intervention-recurrences/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /intervention-recurrences/{id}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteInterventionRecurrenceRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/intervention-recurrences/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /intervention-recurrences/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /intervention-recurrences/{id}, got ' . $statusCode,
    );
  }
}
