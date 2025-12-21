<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_encode;

/**
 * Test TenantApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantApiTest extends WebTestCase
{
  // #region Methods
  /**
   * Method testTenantsEndpointRequiresAuthentication.
   *
   * Tests that the tenants endpoint requires authentication.
   */
  #[Test]
  public function testTenantsEndpointRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/tenants');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 status code for unauthenticated request, got ' . $statusCode,
    );
  }

  /**
   * Method testCreateTenantRequiresAuthentication.
   *
   * Tests that tenant creation requires authentication.
   */
  #[Test]
  public function testCreateTenantRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request(
      method: 'POST',
      uri: '/api/tenants',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: (string) json_encode(['name' => 'Test Tenant']),
    );

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 status code for unauthenticated request, got ' . $statusCode,
    );
  }
  // #endregion
}
