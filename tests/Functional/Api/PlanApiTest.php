<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test PlanApiTest.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testListPlansRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/plans');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /plans endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /plans, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetPlanRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/plans/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /plans/{id} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /plans/{id}, got ' . $statusCode,
    );
  }
}
