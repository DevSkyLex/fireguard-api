<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Test UserApiTest
 * @final
 *
 * Functional tests for User API endpoints.
 *
 * @category Functional Test
 * @package Tests\Functional\Api
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserApiTest extends WebTestCase
{
  //#region Properties
  private ?KernelBrowser $client = null;
  //#endregion

  //#region Setup
  protected function setUp(): void
  {
    $this->client = static::createClient();
  }
  //#endregion

  //#region Tests
  /**
   * Method testCreateUserEndpointExists
   *
   * Tests that the create user endpoint exists.
   *
   * @access public
   *
   * @return void
   */
  public function testCreateUserEndpointExists(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/users',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json'
      ],
      content: json_encode([]) ?: ''
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // Should not be 404 - endpoint exists
    $this->assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }

  /**
   * Method testUserEndpointsRequireAuthentication
   *
   * Tests that user endpoints require authentication.
   *
   * @access public
   *
   * @return void
   */
  public function testUserEndpointsRequireAuthentication(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/users',
      server: ['HTTP_ACCEPT' => 'application/ld+json']
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // Should require authentication
    $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  /**
   * Method testCreateUserWithoutAuthReturnsUnauthorized
   *
   * Tests that creating a user without auth returns 401.
   *
   * @access public
   *
   * @return void
   */
  public function testCreateUserWithoutAuthReturnsUnauthorized(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/users',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json'
      ],
      content: json_encode([
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'TestPassword123!',
        'firstName' => 'Test',
        'lastName' => 'User',
      ]) ?: ''
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    // Should require authentication
    $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }
  //#endregion
}
