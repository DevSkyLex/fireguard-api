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
   * Method testOpenIdConfigurationEndpointReturnsValidResponse
   *
   * Tests that the OpenID Configuration endpoint returns valid JSON.
   *
   * @access public
   *
   * @return void
   */
  public function testOpenIdConfigurationEndpointReturnsValidResponse(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/.well-known/openid-configuration',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertContains($response->getStatusCode(), [
      Response::HTTP_OK,
      Response::HTTP_CREATED,
    ]);

    $content = json_decode($response->getContent() ?: '', true);
    $this->assertIsArray($content);
  }

  /**
   * Method testOpenIdConfigurationContainsRequiredFields
   *
   * Tests that the OpenID Configuration contains required fields.
   *
   * @access public
   *
   * @return void
   */
  public function testOpenIdConfigurationContainsRequiredFields(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/.well-known/openid-configuration',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);

    $content = json_decode($response->getContent() ?: '', true);
    $this->assertIsArray($content);

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
   *
   * Tests that the JWKS endpoint returns valid JSON.
   *
   * @access public
   *
   * @return void
   */
  public function testJwksEndpointReturnsValidResponse(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/.well-known/jwks.json',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);
    $this->assertContains($response->getStatusCode(), [
      Response::HTTP_OK,
      Response::HTTP_CREATED,
    ]);

    $content = json_decode($response->getContent() ?: '', true);
    $this->assertIsArray($content);
  }

  /**
   * Method testJwksEndpointContainsKeys
   *
   * Tests that the JWKS endpoint contains keys array.
   *
   * @access public
   *
   * @return void
   */
  public function testJwksEndpointContainsKeys(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/.well-known/jwks.json',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);

    $content = json_decode($response->getContent() ?: '', true);
    $this->assertIsArray($content);
    $this->assertArrayHasKey('keys', $content);
    $this->assertIsArray($content['keys']);
  }

  /**
   * Method testJwksKeysHaveRequiredFields
   *
   * Tests that JWKS keys have required fields per RFC 7517.
   *
   * @access public
   *
   * @return void
   */
  public function testJwksKeysHaveRequiredFields(): void
  {
    $this->client?->request(
      method: 'GET',
      uri: '/api/.well-known/jwks.json',
      server: ['HTTP_ACCEPT' => 'application/json']
    );

    $response = $this->client?->getResponse();
    $this->assertNotNull($response);

    $content = json_decode($response->getContent() ?: '', true);
    $this->assertIsArray($content);
    $this->assertArrayHasKey('keys', $content);

    if (count($content['keys']) > 0) {
      $key = $content['keys'][0];
      $this->assertArrayHasKey('kty', $key);
      $this->assertArrayHasKey('use', $key);
      $this->assertArrayHasKey('kid', $key);
    }
  }
  //#endregion
}
