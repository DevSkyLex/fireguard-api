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

  #[Test]
  public function testUpdateCurrentUserProfileRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'PATCH',
      '/api/me',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: '{"firstName":"Jane"}',
    );

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testUploadCurrentUserAvatarRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PUT', '/api/me/avatar', server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testUpdateCurrentUserProfileLocaleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'PATCH',
      '/api/me',
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: '{"locale":"fr"}',
    );

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }
}
