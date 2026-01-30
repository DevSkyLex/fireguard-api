<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test AuditApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditApiTest extends WebTestCase
{
  // #region Methods
  /**
   * Method testAuditEventsEndpointRequiresAuthentication.
   *
   * Tests that the audit events endpoint requires authentication.
   */
  #[Test]
  public function testAuditEventsEndpointRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/audit-events');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 status code for unauthenticated request, got ' . $statusCode,
    );
  }

  /**
   * Method testAuditEventEndpointExists.
   *
   * Tests that the audit event endpoint exists and requires authentication.
   */
  #[Test]
  public function testAuditEventEndpointExists(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/audit-events/550e8400-e29b-41d4-a716-446655440000');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'Audit event endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated request, got ' . $statusCode,
    );
  }
  // #endregion
}
