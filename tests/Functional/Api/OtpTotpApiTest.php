<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OtpTotpApiTest extends WebTestCase
{
  #[Test]
  public function testSetupTotpRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/otp/totp/setup', server: ['HTTP_ACCEPT' => 'application/ld+json']);

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testConfirmTotpRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'POST',
      '/api/otp/totp/confirm',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: '{"code":"123456"}',
    );

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testDisableTotpRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      'POST',
      '/api/otp/totp/disable',
      server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: '{"code":"123456"}',
    );

    $response = $client->getResponse();
    self::assertNotSame(404, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [401, 403]);
  }
}
