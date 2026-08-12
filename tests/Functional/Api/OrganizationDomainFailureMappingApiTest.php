<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{
  OrganizationMemberRecord,
  OrganizationMemberRoleRecord,
  OrganizationRecord,
  OrganizationRoleRecord,
  TeamRecord
};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test OrganizationDomainFailureMappingApiTest.
 *
 * Proves that a domain failure raised inside a use-case handler still reaches
 * the caller as its intended status code. Every handler runs behind the
 * command/query bus, which wraps whatever it throws into a
 * MessengerRuntimeException — a processor that catches the domain exception
 * straight off dispatch() never sees it, and the 404/409 silently degrades to
 * a 500. These cases exercise the real bus against a real database, so they
 * fail if that unwrapping is ever dropped again.
 *
 * The seeded member holds a role with the `*` permission, so each request
 * clears the processor's permission gate and fails for the domain reason
 * under test rather than for authorization.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationDomainFailureMappingApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554495a0';

  private const string USER_ID = '550e8400-e29b-41d4-a716-4466554495a1';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-4466554495a2';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-4466554495a3';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-4466554495a4';

  private const string OTHER_TEAM_ID = '550e8400-e29b-41d4-a716-4466554495a5';

  private const string UNKNOWN_ID = '550e8400-e29b-41d4-a716-4466554495ff';

  private const string EXISTING_TEAM_NAME = 'Domain failure mapping crew';

  private const string OTHER_TEAM_NAME = 'Domain failure mapping crew bis';

  #[Test]
  public function testRemoveUnknownOrganizationMemberReturnsNotFound(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request('DELETE', '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::UNKNOWN_ID);

    $this->assertStatus($client, 404, 'removing an unknown organization member');
  }

  #[Test]
  public function testAssignRoleToUnknownMemberReturnsNotFound(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request(
      'POST',
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::UNKNOWN_ID . '/roles',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: '{"roleId":"' . self::ROLE_ID . '"}',
    );

    $this->assertStatus($client, 404, 'assigning a role to an unknown member');
  }

  #[Test]
  public function testRemoveUnknownRoleFromMemberReturnsNotFound(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::MEMBER_ID . '/roles/' . self::UNKNOWN_ID,
    );

    $this->assertStatus($client, 404, 'removing an unknown role from a member');
  }

  #[Test]
  public function testDeleteUnknownTeamReturnsNotFound(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request('DELETE', '/api/organizations/' . self::ORGANIZATION_ID . '/teams/' . self::UNKNOWN_ID);

    $this->assertStatus($client, 404, 'deleting an unknown team');
  }

  #[Test]
  public function testAddMemberToUnknownTeamReturnsNotFound(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request(
      'POST',
      '/api/organizations/' . self::ORGANIZATION_ID . '/teams/' . self::UNKNOWN_ID . '/members',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: '{"memberId":"' . self::MEMBER_ID . '"}',
    );

    $this->assertStatus($client, 404, 'adding a member to an unknown team');
  }

  #[Test]
  public function testRemoveUnknownMemberFromTeamReturnsNotFound(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request(
      'DELETE',
      '/api/organizations/' . self::ORGANIZATION_ID . '/teams/' . self::TEAM_ID . '/members/' . self::UNKNOWN_ID,
    );

    $this->assertStatus($client, 404, 'removing an unknown member from a team');
  }

  #[Test]
  public function testCreateTeamWithTakenNameReturnsConflict(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request(
      'POST',
      '/api/organizations/' . self::ORGANIZATION_ID . '/teams',
      server: ['CONTENT_TYPE' => 'application/ld+json'],
      content: '{"name":"' . self::EXISTING_TEAM_NAME . '"}',
    );

    $this->assertStatus($client, 409, 'creating a team whose name is already taken');
  }

  #[Test]
  public function testRenameTeamToATakenNameReturnsConflict(): void
  {
    $client = $this->createAuthenticatedClient();

    // This one case needs both fixes at once: `read: false` on the
    // TeamResource Patch, so API Platform's ReadProvider does not answer 404
    // before UpdateTeamProcessor runs at all, and the bus unwrapping, so the
    // handler's TeamNameAlreadyExistsException surfaces as 409 and not 500.
    $client->request(
      'PATCH',
      '/api/organizations/' . self::ORGANIZATION_ID . '/teams/' . self::OTHER_TEAM_ID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: '{"name":"' . self::EXISTING_TEAM_NAME . '"}',
    );

    $this->assertStatus($client, 409, 'renaming a team to a name already taken');
  }

  #[Test]
  public function testUpdateUnknownTeamReturnsNotFound(): void
  {
    $client = $this->createAuthenticatedClient();

    $client->request(
      'PATCH',
      '/api/organizations/' . self::ORGANIZATION_ID . '/teams/' . self::UNKNOWN_ID,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: '{"name":"Renamed by the mapping suite"}',
    );

    $this->assertStatus($client, 404, 'updating an unknown team');
  }

  /**
   * Method assertStatus.
   *
   * Asserts the response status, naming the 500 explicitly: an unmapped domain
   * failure is exactly what this suite exists to catch.
   */
  private function assertStatus(KernelBrowser $client, int $expected, string $action): void
  {
    $response = $client->getResponse();

    self::assertSame(
      expected: $expected,
      actual: $response->getStatusCode(),
      message: 'Expected HTTP ' . $expected . ' when ' . $action . ', got '
        . $response->getStatusCode() . '. A 500 means the domain exception was not unwrapped '
        . 'from the bus failure. Response: ' . (string) $response->getContent(),
    );
  }

  /**
   * Method createAuthenticatedClient.
   *
   * Seeds the organization, its full-access role, the member holding it, and
   * two teams, then authenticates as the member's user.
   */
  private function createAuthenticatedClient(): KernelBrowser
  {
    $client = static::createClient();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    $existing = $entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    if ($existing instanceof OrganizationRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Domain Failure Mapping Test';
    $organization->slug = 'domain-failure-mapping-test-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::USER_ID;
    $organization->createdByUserId = self::USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE_ID;
    $role->organization = $organization;
    $role->name = 'domain_failure_mapping_tester';
    $role->permissions = ['*'];
    $role->description = 'Functional-test-only role granting every permission.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    $member = new OrganizationMemberRecord();
    $member->id = self::MEMBER_ID;
    $member->organization = $organization;
    $member->userId = self::USER_ID;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $roleAssignment = new OrganizationMemberRoleRecord();
    $roleAssignment->member = $member;
    $roleAssignment->role = $role;
    $roleAssignment->assignedAt = $now;
    $entityManager->persist($roleAssignment);

    foreach ([[self::TEAM_ID, self::EXISTING_TEAM_NAME], [self::OTHER_TEAM_ID, self::OTHER_TEAM_NAME]] as [$teamId, $teamName]) {
      $team = new TeamRecord();
      $team->id = $teamId;
      $team->organization = $organization;
      $team->name = $teamName;
      $team->description = 'Seeded for the domain-failure mapping functional test.';
      $team->createdAt = $now;
      $team->updatedAt = $now;
      $entityManager->persist($team);
    }

    $entityManager->flush();

    $client->loginUser(new SecurityUser(
      id: self::USER_ID,
      email: 'domain-failure-mapping-test@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    ), 'api');

    return $client;
  }
}
