<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class UserStatusApiTest extends WebTestCase
{
  #[Test]
  public function testListUserStatusesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'GET',
      uri: '/api/users/statuses',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $client->getResponse();

    self::assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }
}
