<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrganizationTeamApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  private const string DUMMY_UUID_2 = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCreateTeamRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/teams', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"name":"Field crew A"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /organizations/{organizationId}/teams endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{organizationId}/teams, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListTeamsRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/teams');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/teams endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/teams, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetTeamRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/teams/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/teams/{teamId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/teams/{teamId}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testUpdateTeamRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PATCH', '/api/organizations/' . self::DUMMY_UUID . '/teams/' . self::DUMMY_UUID_2, server: [
      'CONTENT_TYPE' => 'application/merge-patch+json',
    ], content: '{"name":"Field crew B"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'PATCH /organizations/{organizationId}/teams/{teamId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated PATCH /organizations/{organizationId}/teams/{teamId}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testDeleteTeamRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/teams/' . self::DUMMY_UUID_2);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{organizationId}/teams/{teamId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /organizations/{organizationId}/teams/{teamId}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testAddTeamMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/teams/' . self::DUMMY_UUID_2 . '/members', server: [
      'CONTENT_TYPE' => 'application/json',
    ], content: '{"memberId":"' . self::DUMMY_UUID . '"}');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'POST /organizations/{organizationId}/teams/{teamId}/members endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated POST /organizations/{organizationId}/teams/{teamId}/members, got ' . $statusCode,
    );
  }

  #[Test]
  public function testListTeamMembersRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/teams/' . self::DUMMY_UUID_2 . '/members');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/teams/{teamId}/members endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/teams/{teamId}/members, got ' . $statusCode,
    );
  }

  #[Test]
  public function testRemoveTeamMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('DELETE', '/api/organizations/' . self::DUMMY_UUID . '/teams/' . self::DUMMY_UUID_2 . '/members/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'DELETE /organizations/{organizationId}/teams/{teamId}/members/{memberId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated DELETE /organizations/{organizationId}/teams/{teamId}/members/{memberId}, got ' . $statusCode,
    );
  }
}
