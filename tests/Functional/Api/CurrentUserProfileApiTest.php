<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrentUserProfileApiTest extends WebTestCase
{
  #[Test]
  public function testGetCurrentUserProfileRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/me', server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }
}
