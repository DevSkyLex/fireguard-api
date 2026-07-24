<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test ImportJobApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJobApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testCreateImportJobRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/imports', server: [
      'CONTENT_TYPE' => 'multipart/form-data',
    ]);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /imports endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /imports, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListImportJobsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/imports?organization=' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /imports endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /imports, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetImportJobRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/imports/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /imports/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /imports/{id}, got ' . $statusCode,
    );
  }
}
