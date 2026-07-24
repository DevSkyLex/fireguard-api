<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SafetyRegisterExportApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testExportOrganizationSafetyRegisterRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/compliance/export');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{id}/compliance/export endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{id}/compliance/export, got ' . $statusCode,
    );
  }

  #[Test]
  public function testExportFacilitySafetyRegisterRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/facilities/' . self::DUMMY_UUID . '/compliance/export');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{id}/facilities/{id}/compliance/export endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{id}/facilities/{id}/compliance/export, got ' . $statusCode,
    );
  }
}
