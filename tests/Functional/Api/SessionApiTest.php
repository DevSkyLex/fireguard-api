<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test SessionApiTest
 * @final
 *
 * Functional tests for the Session API.
 *
 * @category Functional Tests
 * @package Tests\Functional\Api
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionApiTest extends WebTestCase
{
  //#region Methods
  /**
   * Method testSessionsEndpointRequiresAuthentication
   *
   * Tests that the sessions endpoint requires authentication.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testSessionsEndpointRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/sessions');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 status code for unauthenticated request, got ' . $statusCode
    );
  }

  /**
   * Method testRevokeAllSessionsEndpointExists
   *
   * Tests that the revoke all sessions endpoint exists and requires authentication.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRevokeAllSessionsEndpointExists(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/sessions/revoke-all');

    $statusCode = $client->getResponse()->getStatusCode();

    // Should return 401/403 for unauthenticated, not 404
    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'Revoke all sessions endpoint should exist (got 404)'
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated request, got ' . $statusCode
    );
  }
  //#endregion
}
