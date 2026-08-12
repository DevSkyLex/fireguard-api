<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, TeamRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

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

  // #region Authenticated update contract

  /**
   * The endpoint used to answer 404 for every request: the Patch operation
   * did not set `read: false`, so API Platform's ReadProvider ran first,
   * found no resource for this plain (non-Doctrine) resource class and threw
   * NotFoundHttpException before UpdateTeamProcessor was ever invoked. This
   * test authenticates as a real organization member holding a role with the
   * `*` permission and asserts the rename actually lands.
   */
  #[Test]
  public function testUpdateTeamRenamesTheTeam(): void
  {
    $client = static::createClient();
    $seed = $this->seedOrganizationWithTeams('10');

    $user = new SecurityUser(
      id: $seed['userId'],
      email: 'organization-team-update-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $seed['organizationId'] . '/teams/' . $seed['teamId'],
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: (string) json_encode(['name' => 'Field crew renamed']),
    );

    $response = $client->getResponse();
    self::assertSame(
      expected: 200,
      actual: $response->getStatusCode(),
      message: 'PATCH on an existing team should succeed. Response: ' . $response->getContent(),
    );

    $decoded = json_decode($response->getContent() ?: '{}', true);
    self::assertIsArray($decoded);
    self::assertSame($seed['teamId'], $decoded['id'] ?? null);
    self::assertSame('Field crew renamed', $decoded['name'] ?? null);
  }

  /**
   * The conflict path the 404 had been masking: renaming a team to a name
   * another team in the same organization already holds must map
   * TeamNameAlreadyExistsException to 409, not 404 and not 500.
   */
  #[Test]
  public function testUpdateTeamRejectsAnAlreadyTakenName(): void
  {
    $client = static::createClient();
    $seed = $this->seedOrganizationWithTeams('11');

    $user = new SecurityUser(
      id: $seed['userId'],
      email: 'organization-team-update-conflict-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
    $client->loginUser($user, 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $seed['organizationId'] . '/teams/' . $seed['teamId'],
      server: [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: (string) json_encode(['name' => $seed['otherTeamName']]),
    );

    $response = $client->getResponse();
    self::assertSame(
      expected: 409,
      actual: $response->getStatusCode(),
      message: 'Renaming a team to an already-taken name should conflict. Response: ' . $response->getContent(),
    );
  }

  // #endregion

  // #region Fixtures

  /**
   * Seeds an organization, a role granting every permission, an active member
   * for that role, and two teams — mirroring the harness in
   * {@see OrganizationApiTest}. The role name is underscore-separated because
   * OrganizationRoleName rejects hyphens.
   *
   * @param string $suffix two digits keeping each test's UUIDs distinct
   *
   * @return array{organizationId: string, userId: string, teamId: string, otherTeamId: string, otherTeamName: string}
   */
  private function seedOrganizationWithTeams(string $suffix): array
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $organizationId = '550e8400-e29b-41d4-a716-4466554460' . $suffix;
    $userId = '550e8400-e29b-41d4-a716-4466554461' . $suffix;
    $memberId = '550e8400-e29b-41d4-a716-4466554462' . $suffix;
    $roleId = '550e8400-e29b-41d4-a716-4466554463' . $suffix;
    $teamId = '550e8400-e29b-41d4-a716-4466554464' . $suffix;
    $otherTeamId = '550e8400-e29b-41d4-a716-4466554465' . $suffix;
    $otherTeamName = 'Field crew taken ' . $suffix;

    $existingOrganization = $entityManager->find(OrganizationRecord::class, $organizationId);
    if ($existingOrganization instanceof OrganizationRecord) {
      $entityManager->remove($existingOrganization);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $organizationId;
    $organization->name = 'Team Update Test ' . $suffix;
    $organization->slug = 'team-update-test-' . $organizationId;
    $organization->ownerUserId = $userId;
    $organization->createdByUserId = $userId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $role = new OrganizationRoleRecord();
    $role->id = $roleId;
    $role->organization = $organization;
    $role->name = 'full_access_tester';
    $role->permissions = ['*'];
    $role->description = 'Functional-test-only role granting every permission.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = $memberId;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);

    $team = new TeamRecord();
    $team->id = $teamId;
    $team->organization = $organization;
    $team->name = 'Field crew ' . $suffix;
    $team->description = 'Seeded for the team-update functional test.';
    $team->createdAt = $now;
    $team->updatedAt = $now;
    $entityManager->persist($team);

    $otherTeam = new TeamRecord();
    $otherTeam->id = $otherTeamId;
    $otherTeam->organization = $organization;
    $otherTeam->name = $otherTeamName;
    $otherTeam->description = 'Holds the name the conflict test tries to steal.';
    $otherTeam->createdAt = $now;
    $otherTeam->updatedAt = $now;
    $entityManager->persist($otherTeam);

    $entityManager->flush();

    return [
      'organizationId' => $organizationId,
      'userId' => $userId,
      'teamId' => $teamId,
      'otherTeamId' => $otherTeamId,
      'otherTeamName' => $otherTeamName,
    ];
  }

  // #endregion
}
