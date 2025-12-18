<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Test WellKnownApiTest
 * @final
 *
 * Functional tests for OpenID Connect Discovery endpoints.
 *
 * @category Functional Test
 * @package Tests\Functional\Api
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WellKnownApiTest extends WebTestCase
{
  //#region Properties
  private KernelBrowser $client;
  //#endregion

  //#region Setup
  protected function setUp(): void
  {
    $this->client = static::createClient();
  }
  //#endregion

  //#region Tests
  /**
   * Method testOpenIdConfigurationEndpointReturnsValidResponse
   */
  public function testOpenIdConfigurationEndpointReturnsValidResponse(): void
  {
    $this->client->request(
      method: 'GET',
      uri: '/api/.well-known/openid-configuration',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client->getResponse();
    $this->assertContains($response->getStatusCode(), [
      Response::HTTP_OK,
      Response::HTTP_CREATED,
    ]);

    $content = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertNotEmpty($content);
  }

  /**
   * Method testOpenIdConfigurationContainsRequiredFields
   */
  public function testOpenIdConfigurationContainsRequiredFields(): void
  {
    $this->client->request(
      method: 'GET',
      uri: '/api/.well-known/openid-configuration',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client->getResponse();
    $content = $this->decodeJsonResponse($response->getContent() ?: '{}');

    // Required fields per OpenID Connect Discovery 1.0
    $this->assertArrayHasKey('issuer', $content);
    $this->assertArrayHasKey('authorization_endpoint', $content);
    $this->assertArrayHasKey('token_endpoint', $content);
    $this->assertArrayHasKey('jwks_uri', $content);
    $this->assertArrayHasKey('response_types_supported', $content);
    $this->assertArrayHasKey('subject_types_supported', $content);
    $this->assertArrayHasKey('id_token_signing_alg_values_supported', $content);
  }

  /**
   * Method testJwksEndpointReturnsValidResponse
   */
  public function testJwksEndpointReturnsValidResponse(): void
  {
    $this->client->request(
      method: 'GET',
      uri: '/api/.well-known/jwks.json',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client->getResponse();
    $this->assertContains($response->getStatusCode(), [
      Response::HTTP_OK,
      Response::HTTP_CREATED,
    ]);

    $content = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertNotEmpty($content);
  }

  /**
   * Method testJwksEndpointContainsKeys
   */
  public function testJwksEndpointContainsKeys(): void
  {
    $this->client->request(
      method: 'GET',
      uri: '/api/.well-known/jwks.json',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client->getResponse();
    $content = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('keys', $content);
    $this->assertIsArray($content['keys']);
  }

  /**
   * Method testJwksKeysHaveRequiredFields
   */
  public function testJwksKeysHaveRequiredFields(): void
  {
    $this->client->request(
      method: 'GET',
      uri: '/api/.well-known/jwks.json',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client->getResponse();
    $content = $this->decodeJsonResponse($response->getContent() ?: '{}');
    $this->assertArrayHasKey('keys', $content);

    $keys = $content['keys'];
    if (is_array($keys) && count($keys) > 0) {
      $key = $keys[0];
      if (is_array($key)) {
        $this->assertArrayHasKey('kty', $key);
        $this->assertArrayHasKey('use', $key);
        $this->assertArrayHasKey('kid', $key);
      }
    }
  }

  /**
   * Decode JSON response content to array
   *
   * @param string $content Response content
   * @return array<string, mixed>
   */
  protected function decodeJsonResponse(string $content): array
  {
    $data = json_decode($content, true);
    if (!is_array($data)) {
      return [];
    }
    $result = [];
    foreach ($data as $key => $value) {
      if (is_string($key)) {
        $result[$key] = $value;
      }
    }
    return $result;
  }
  //#endregion
}
