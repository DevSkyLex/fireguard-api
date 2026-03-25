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
}
