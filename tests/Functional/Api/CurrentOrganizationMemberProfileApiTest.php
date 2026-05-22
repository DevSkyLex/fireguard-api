<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrentOrganizationMemberProfileApiTest extends WebTestCase
{
  #[Test]
  public function testGetCurrentOrganizationMemberProfileRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'GET',
      '/api/organizations/550e8400-e29b-41d4-a716-446655442501/me',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }
}
