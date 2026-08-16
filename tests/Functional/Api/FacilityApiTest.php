<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FacilityApiTest extends WebTestCase
{
  #[Test]
  public function testListFacilityStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/facilities/statuses');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /facilities/statuses endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /facilities/statuses, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListFacilitiesWithHasCoordinatesFilterRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/550e8400-e29b-41d4-a716-446655449000/facilities?hasCoordinates=true');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/facilities?hasCoordinates=true endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/facilities?hasCoordinates=true, got ' . $statusCode,
    );
  }
}
