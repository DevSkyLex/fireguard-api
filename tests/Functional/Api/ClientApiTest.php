<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function json_encode;

/**
 * Test ClientApiTest.
 *
 * @category Functional Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientApiTest extends WebTestCase
{
  // #region Properties
  private ?KernelBrowser $client = null;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->client = static::createClient();
  }
  // #endregion

  // #region Tests
  /**
   * Method testClientEndpointsRequireAuthentication.
   *
   * Tests that client endpoints require authentication.
   */
  public function testClientEndpointsRequireAuthentication(): void
  {
    // Client management requires ROLE_ADMIN per security.yaml
    // Without authentication, should get 401
    $this->client?->request(
      method: 'POST',
      uri: '/api/clients',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'name' => 'Test Client',
        'redirectUris' => ['https://example.com/callback'],
        'grantTypes' => ['client_credentials'],
        'scopes' => ['read'],
      ]) ?: ''
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // Should require authentication
    $this->assertContains($response->getStatusCode(), [
      Response::HTTP_UNAUTHORIZED,
      Response::HTTP_FORBIDDEN,
      Response::HTTP_NOT_FOUND, // If endpoint not exposed
    ]);
  }
  // #endregion
}
