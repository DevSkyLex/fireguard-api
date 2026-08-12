<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function array_column;
use function array_intersect;
use function json_decode;
use function json_encode;

/**
 * Test OrganizationRoleApiTest.
 *
 * Covers the role-management HTTP slice added in lot P2.4: renaming a role
 * through PATCH (including the uniqueness and system-role refusals), the
 * single-role GET, and real pagination on the role list.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationRoleApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  // -------------------------------------------------------------------------
  // PATCH /organizations/{organizationId}/roles/{roleId} — rename
  // -------------------------------------------------------------------------

  #[Test]
  public function testUpdateRoleRenameSucceedsForAuthorizedCaller(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000001';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000002';
    $roleId = '550e8400-e29b-41d4-a716-447020000003';
    $memberId = '550e8400-e29b-41d4-a716-447020000004';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedRole($entityManager, $organization, $roleId, ['organization.read'], $now, 'inspector');
    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000005', $now);
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/roles/' . $roleId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'name' => 'site_manager',
        'permissions' => ['organization.read', 'organization.members.read'],
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Rename should succeed. Response: ' . $response->getContent());

    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame('site_manager', $decoded['name'] ?? null);
    self::assertSame($roleId, $decoded['id'] ?? null);
  }

  /**
   * A permissions-only PATCH — no `name` key at all, so
   * UpdateOrganizationRoleInput::$name stays null and the handler must leave
   * the role's name alone. It also pins the response contract: the returned
   * `permissions` array reflects the new list, not the stored one.
   */
  #[Test]
  public function testUpdateRolePermissionsWithoutRenamingIt(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000110';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000111';
    $roleId = '550e8400-e29b-41d4-a716-447020000112';
    $memberId = '550e8400-e29b-41d4-a716-447020000114';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedRole($entityManager, $organization, $roleId, ['organization.read'], $now, 'inspector');
    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000115', $now);
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/roles/' . $roleId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'permissions' => ['organization.read', 'organization.teams.read'],
      ]),
    );

    $response = $client->getResponse();
    self::assertSame(
      200,
      $response->getStatusCode(),
      'A permissions-only update should succeed. Response: ' . $response->getContent(),
    );

    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame($roleId, $decoded['id'] ?? null);
    self::assertSame('inspector', $decoded['name'] ?? null);

    self::assertArrayHasKey('permissions', $decoded);
    self::assertIsArray($decoded['permissions']);

    $permissionNames = [];
    foreach ($decoded['permissions'] as $permission) {
      self::assertIsArray($permission);
      $permissionNames[] = $permission['name'] ?? null;
    }

    self::assertSame(['organization.read', 'organization.teams.read'], $permissionNames);
  }

  #[Test]
  public function testUpdateRoleRenameRejectsDuplicateName(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000010';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000011';
    $roleId = '550e8400-e29b-41d4-a716-447020000012';
    $conflictingRoleId = '550e8400-e29b-41d4-a716-447020000013';
    $memberId = '550e8400-e29b-41d4-a716-447020000014';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedRole($entityManager, $organization, $roleId, ['organization.read'], $now, 'inspector');
    $this->seedRole($entityManager, $organization, $conflictingRoleId, ['organization.read'], $now, 'site_manager');
    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000015', $now);
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/roles/' . $roleId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'name' => 'site_manager',
        'permissions' => ['organization.read'],
      ]),
    );

    // Mirrors CreateOrganizationRoleProcessor's mapping of the same
    // "Role name already exists for this organization." InvalidArgumentException.
    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testUpdateRoleRenameRejectsSystemRole(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000020';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000021';
    $systemRoleId = '550e8400-e29b-41d4-a716-447020000022';
    $memberId = '550e8400-e29b-41d4-a716-447020000024';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);

    $systemRole = new OrganizationRoleRecord();
    $systemRole->id = $systemRoleId;
    $systemRole->organization = $organization;
    $systemRole->name = 'admin';
    $systemRole->permissions = ['organization.*'];
    $systemRole->description = 'System admin role.';
    $systemRole->isSystem = true;
    $systemRole->createdAt = $now;
    $entityManager->persist($systemRole);

    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000025', $now);
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/roles/' . $systemRoleId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'name' => 'renamed_admin',
        'permissions' => ['organization.*'],
      ]),
    );

    // InvalidArgumentException('System roles cannot be modified.') mapped by
    // UpdateOrganizationRoleProcessor the same way as the duplicate-name case.
    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testUpdateRoleRejectsCallerWithoutRolesManagePermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000030';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000031';
    $roleId = '550e8400-e29b-41d4-a716-447020000032';
    $unentitledUserId = '550e8400-e29b-41d4-a716-447020000033';
    $memberId = '550e8400-e29b-41d4-a716-447020000034';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedRole($entityManager, $organization, $roleId, ['organization.read'], $now, 'inspector');
    // Active member with NO role assigned — authenticated but not entitled.
    $this->seedMember($entityManager, $organization, $memberId, $unentitledUserId, true, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');

    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/roles/' . $roleId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'permissions' => ['organization.read'],
      ]),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testUpdateRoleRejectsRoleInAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000040';
    $otherOrganizationId = '550e8400-e29b-41d4-a716-447020000041';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000042';
    $foreignRoleId = '550e8400-e29b-41d4-a716-447020000043';
    $memberId = '550e8400-e29b-41d4-a716-447020000044';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $otherOrganization = $this->seedOrganization($entityManager, $otherOrganizationId, '550e8400-e29b-41d4-a716-447020000045', $now);
    $this->seedRole($entityManager, $otherOrganization, $foreignRoleId, ['organization.read'], $now, 'foreign_role');

    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000046', $now);
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    // The caller holds organization.roles.manage in `$organizationId`, but the
    // role id belongs to `$otherOrganizationId`. UpdateOrganizationRoleHandler
    // scopes the role lookup to the URL's organization and currently reports
    // this as InvalidArgumentException('Role not found in this organization.'),
    // mapped by UpdateOrganizationRoleProcessor to 400 — NOT the 404 the
    // sibling GetOrganizationRole endpoint returns for the identical case
    // (see OrganizationRoleNotFoundException there). This asymmetry predates
    // this HTTP slice (the Application-layer command/handler were already
    // landed); documented here rather than silently asserting a 404 that does
    // not match the real behavior.
    $client->request(
      method: 'PATCH',
      uri: '/api/organizations/' . $organizationId . '/roles/' . $foreignRoleId,
      server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
      content: (string) json_encode([
        'permissions' => ['organization.read'],
      ]),
    );

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  // -------------------------------------------------------------------------
  // GET /organizations/{organizationId}/roles/{roleId}
  // -------------------------------------------------------------------------

  #[Test]
  public function testGetOrganizationRoleRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/roles/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{id}/roles/{roleId} endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /roles/{roleId}, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetOrganizationRoleSucceedsForAuthorizedCaller(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000050';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000051';
    $roleId = '550e8400-e29b-41d4-a716-447020000052';
    $memberId = '550e8400-e29b-41d4-a716-447020000053';
    $secondMemberId = '550e8400-e29b-41d4-a716-447020000054';
    $secondUserId = '550e8400-e29b-41d4-a716-447020000055';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $role = $this->seedRole($entityManager, $organization, $roleId, ['organization.read', 'organization.members.read'], $now, 'inspector');
    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000056', $now);

    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);

    // A second ACTIVE member holding the role under test — proves memberCount.
    $secondMember = $this->seedMember($entityManager, $organization, $secondMemberId, $secondUserId, true, $now);
    $this->assignRole($entityManager, $secondMember, $role, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/roles/' . $roleId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), 'Get role should succeed. Response: ' . $response->getContent());

    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame($roleId, $decoded['id'] ?? null);
    self::assertSame('inspector', $decoded['name'] ?? null);
    self::assertSame(1, $decoded['memberCount'] ?? null);
  }

  #[Test]
  public function testGetOrganizationRoleRejectsCallerWithoutRolesReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000060';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000061';
    $roleId = '550e8400-e29b-41d4-a716-447020000062';
    $unentitledUserId = '550e8400-e29b-41d4-a716-447020000063';
    $memberId = '550e8400-e29b-41d4-a716-447020000064';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $this->seedRole($entityManager, $organization, $roleId, ['organization.read'], $now, 'inspector');
    $this->seedMember($entityManager, $organization, $memberId, $unentitledUserId, true, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($unentitledUserId), 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/roles/' . $roleId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGetOrganizationRoleRejectsRoleInAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000070';
    $otherOrganizationId = '550e8400-e29b-41d4-a716-447020000071';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000072';
    $foreignRoleId = '550e8400-e29b-41d4-a716-447020000073';
    $memberId = '550e8400-e29b-41d4-a716-447020000074';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $otherOrganization = $this->seedOrganization($entityManager, $otherOrganizationId, '550e8400-e29b-41d4-a716-447020000075', $now);
    $this->seedRole($entityManager, $otherOrganization, $foreignRoleId, ['organization.read'], $now, 'foreign_role');

    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000076', $now);
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    // GetOrganizationRoleHandler correctly scopes the role to the URL's
    // organization: OrganizationRoleNotFoundException -> 404, never a
    // cross-tenant read (unlike PATCH — see the note on
    // testUpdateRoleRejectsRoleInAnotherOrganization above).
    $client->request('GET', '/api/organizations/' . $organizationId . '/roles/' . $foreignRoleId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGetOrganizationRoleRejectsUnknownRole(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000080';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000081';
    $memberId = '550e8400-e29b-41d4-a716-447020000084';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000086', $now);
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/roles/' . self::DUMMY_UUID, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  // -------------------------------------------------------------------------
  // GET /organizations/{organizationId}/roles — real pagination
  // -------------------------------------------------------------------------

  #[Test]
  public function testListOrganizationRolesPaginatesToASecondPage(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000090';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000091';
    $memberId = '550e8400-e29b-41d4-a716-447020000092';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000093', $now, 'z_full_access');
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);

    // Five roles total (including z_full_access), named to sort predictably by name ASC.
    $roleNames = ['role_a', 'role_b', 'role_c', 'role_d'];
    foreach ($roleNames as $index => $roleName) {
      $this->seedRole(
        $entityManager,
        $organization,
        '550e8400-e29b-41d4-a716-44702000009' . ($index + 4),
        ['organization.read'],
        $now,
        $roleName,
      );
    }
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/roles?page=1&itemsPerPage=2&order[name]=asc', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $firstPageResponse = $client->getResponse();
    self::assertSame(200, $firstPageResponse->getStatusCode());
    $firstPageBody = $this->decodeObject((string) $firstPageResponse->getContent());
    self::assertSame(5, $firstPageBody['totalItems'] ?? null);
    $firstPageNames = array_column($this->decodeMembers((string) $firstPageResponse->getContent()), 'name');
    self::assertCount(2, $firstPageNames);
    self::assertSame(['role_a', 'role_b'], $firstPageNames);

    // Uses its own freshly authenticated client: the token set by
    // loginUser() does not reliably survive a second request on a reused
    // client.
    static::ensureKernelShutdown();
    $secondClient = static::createClient();
    $secondClient->loginUser($this->securityUser($ownerUserId), 'api');
    $secondClient->request('GET', '/api/organizations/' . $organizationId . '/roles?page=2&itemsPerPage=2&order[name]=asc', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    $secondPageResponse = $secondClient->getResponse();
    self::assertSame(200, $secondPageResponse->getStatusCode());
    $secondPageBody = $this->decodeObject((string) $secondPageResponse->getContent());
    self::assertSame(5, $secondPageBody['totalItems'] ?? null);
    $secondPageNames = array_column($this->decodeMembers((string) $secondPageResponse->getContent()), 'name');
    self::assertCount(2, $secondPageNames);
    self::assertSame(['role_c', 'role_d'], $secondPageNames);

    // The two pages must never repeat the same rows.
    self::assertEmpty(array_intersect($firstPageNames, $secondPageNames));
  }

  #[Test]
  public function testListOrganizationRolesTotalItemsReflectsSearchFilteredCount(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000100';
    $ownerUserId = '550e8400-e29b-41d4-a716-447020000101';
    $memberId = '550e8400-e29b-41d4-a716-447020000102';

    $organization = $this->seedOrganization($entityManager, $organizationId, $ownerUserId, $now);
    $fullAccessRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000103', $now, 'zzz_full_access');
    $member = $this->seedMember($entityManager, $organization, $memberId, $ownerUserId, true, $now);
    $this->assignRole($entityManager, $member, $fullAccessRole, $now);

    $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000104', ['organization.read'], $now, 'inspector_alpha');
    $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000105', ['organization.read'], $now, 'inspector_beta');
    $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000106', ['organization.read'], $now, 'site_manager');
    $entityManager->flush();

    $client->loginUser($this->securityUser($ownerUserId), 'api');

    $client->request('GET', '/api/organizations/' . $organizationId . '/roles?search=inspector', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode());
    $body = $this->decodeObject((string) $response->getContent());

    // 4 roles exist in the organization; only 2 match the search — totalItems
    // must reflect the FILTERED count, never the organization's raw role count.
    self::assertSame(2, $body['totalItems'] ?? null);
    $names = array_column($this->decodeMembers((string) $response->getContent()), 'name');
    self::assertCount(2, $names);
    self::assertContains('inspector_alpha', $names);
    self::assertContains('inspector_beta', $names);
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function entityManager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $entityManager;
  }

  /**
   * @return array<mixed>
   */
  private function decodeObject(string $content): array
  {
    $decoded = json_decode($content, true);
    self::assertIsArray($decoded);

    return $decoded;
  }

  /**
   * Decodes a collection response's `member` array.
   *
   * @return list<array<mixed>>
   */
  private function decodeMembers(string $content): array
  {
    $body = $this->decodeObject($content);
    self::assertArrayHasKey('member', $body);
    $rawMembers = $body['member'];
    self::assertIsArray($rawMembers);

    $members = [];
    foreach ($rawMembers as $rawMember) {
      self::assertIsArray($rawMember);
      $members[] = $rawMember;
    }

    return $members;
  }

  private function seedOrganization(
    EntityManagerInterface $entityManager,
    string $id,
    string $ownerUserId,
    DateTimeImmutable $now,
    string $status = 'active',
  ): OrganizationRecord {
    $existing = $entityManager->find(OrganizationRecord::class, $id);
    if ($existing instanceof OrganizationRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Role API Test ' . $id;
    $organization->slug = 'role-api-test-' . $id;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = $status;
    $organization->isActive = 'archived' !== $status;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    return $organization;
  }

  /**
   * @param list<string> $permissions
   */
  private function seedRole(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    array $permissions,
    DateTimeImmutable $now,
    string $name = 'test_role',
  ): OrganizationRoleRecord {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->permissions = $permissions;
    $role->description = 'Functional-test-only role.';
    $role->isSystem = false;
    $role->createdAt = $now;
    $entityManager->persist($role);

    return $role;
  }

  private function seedFullAccessRole(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    DateTimeImmutable $now,
    string $name = 'full_access_role',
  ): OrganizationRoleRecord {
    return $this->seedRole($entityManager, $organization, $id, ['*'], $now, $name);
  }

  private function seedMember(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    string $userId,
    bool $isActive,
    DateTimeImmutable $joinedAt,
  ): OrganizationMemberRecord {
    $member = new OrganizationMemberRecord();
    $member->id = $id;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = $isActive;
    $member->joinedAt = $joinedAt;
    $entityManager->persist($member);

    return $member;
  }

  private function assignRole(
    EntityManagerInterface $entityManager,
    OrganizationMemberRecord $member,
    OrganizationRoleRecord $role,
    DateTimeImmutable $now,
  ): void {
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $entityManager->persist($assignment);
  }

  private function securityUser(string $userId): SecurityUser
  {
    return new SecurityUser(
      id: $userId,
      email: $userId . '@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    );
  }
}
