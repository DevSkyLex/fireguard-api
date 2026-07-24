<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InterventionTeamAssignmentApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testAssignTeamToInterventionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/interventions/' . self::DUMMY_UUID . '/team-assignments', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"teamId":"' . self::DUMMY_UUID_2 . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /interventions/{id}/team-assignments endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /interventions/{id}/team-assignments, got ' . $statusCode,
    );
  }
}
