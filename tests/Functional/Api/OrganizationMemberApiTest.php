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
 * Test OrganizationMemberApiTest.
 *
 * Covers the member-management HTTP slice added in lot P2.3: the extended
 * member list filters/sort/pagination, the single-member GET, the
 * reactivate-member POST, and the bulk role-replacement PUT.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMemberApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  // -------------------------------------------------------------------------
  // GET /organizations/{organizationId}/members — filters, sort, pagination
  // -------------------------------------------------------------------------

  #[Test]
  public function testListMembersReturns403ForCallerWithoutMembersReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447010000001';
    $callerUserId = '550e8400-e29b-41d4-a716-447010000002';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000003', ['facility.read'], $now);
    $member = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000004', $callerUserId, true, $now);
    $this->assignRole($entityManager, $member, $role, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('GET', '/api/organizations/' . $organizationId . '/members');

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testListMembersReturns422ForAnInvalidStatusFilter(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447010000011';
    $callerUserId = '550e8400-e29b-41d4-a716-447010000012';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000013', $now);
    $member = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000014', $callerUserId, true, $now);
    $this->assignRole($entityManager, $member, $role, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('GET', '/api/organizations/' . $organizationId . '/members?status=bogus');

    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testListMembersFiltersByStatusSearchAndRoleId(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447010000021';
    $callerUserId = '550e8400-e29b-41d4-a716-447010000022';
    $inactiveUserId = '550e8400-e29b-41d4-a716-447010000023';
    $otherRoleUserId = '550e8400-e29b-41d4-a716-447010000024';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $adminRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000025', $now, 'admin_role');
    $otherRole = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000026', ['facility.read'], $now, 'other_role');

    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000027', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $adminRole, $now);

    $inactiveMember = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000028', $inactiveUserId, false, $now);
    $this->assignRole($entityManager, $inactiveMember, $adminRole, $now);

    $otherRoleMember = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000029', $otherRoleUserId, true, $now);
    $this->assignRole($entityManager, $otherRoleMember, $otherRole, $now);

    $entityManager->flush();

    // Each filter below uses its own freshly authenticated client: the
    // security token set by loginUser() does not reliably survive a second
    // request on a reused client (the token storage is reset between
    // requests), so reusing one client across several assertions here would
    // intermittently authenticate only the first request.
    $client->loginUser($this->securityUser($callerUserId), 'api');

    // status=active must exclude the inactive member.
    $client->request('GET', '/api/organizations/' . $organizationId . '/members?status=active', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $members = $this->decodeMembers((string) $client->getResponse()->getContent());
    $activeUserIds = array_column($members, 'userId');
    self::assertContains($callerUserId, $activeUserIds);
    self::assertContains($otherRoleUserId, $activeUserIds);
    self::assertNotContains($inactiveUserId, $activeUserIds);

    // status=inactive must return only the inactive member.
    static::ensureKernelShutdown();
    $inactiveFilterClient = static::createClient();
    $inactiveFilterClient->loginUser($this->securityUser($callerUserId), 'api');
    $inactiveFilterClient->request('GET', '/api/organizations/' . $organizationId . '/members?status=inactive', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    self::assertSame(1, $this->decodeTotalItems((string) $inactiveFilterClient->getResponse()->getContent()));
    $members = $this->decodeMembers((string) $inactiveFilterClient->getResponse()->getContent());
    self::assertSame($inactiveUserId, $members[0]['userId']);

    // roleId must return only members holding that role.
    static::ensureKernelShutdown();
    $roleFilterClient = static::createClient();
    $roleFilterClient->loginUser($this->securityUser($callerUserId), 'api');
    $roleFilterClient->request('GET', '/api/organizations/' . $organizationId . '/members?roleId=' . $otherRole->id, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    self::assertSame(1, $this->decodeTotalItems((string) $roleFilterClient->getResponse()->getContent()));
    $members = $this->decodeMembers((string) $roleFilterClient->getResponse()->getContent());
    self::assertSame($otherRoleUserId, $members[0]['userId']);

    // search matches the member's user identifier.
    static::ensureKernelShutdown();
    $searchFilterClient = static::createClient();
    $searchFilterClient->loginUser($this->securityUser($callerUserId), 'api');
    $searchFilterClient->request('GET', '/api/organizations/' . $organizationId . '/members?search=' . $otherRoleUserId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    self::assertSame(1, $this->decodeTotalItems((string) $searchFilterClient->getResponse()->getContent()));
    $members = $this->decodeMembers((string) $searchFilterClient->getResponse()->getContent());
    self::assertSame($otherRoleUserId, $members[0]['userId']);
  }

  #[Test]
  public function testListMembersHonorsPaginationAndReportsTheFilteredTotal(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447010000041';
    $callerUserId = '550e8400-e29b-41d4-a716-447010000042';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000043', $now);

    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447010000044', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);

    // Five more active members, joining in strictly increasing order so
    // `order[joinedAt]=asc` (the default) yields a deterministic sequence.
    $memberUserIds = [];
    for ($i = 1; $i <= 5; ++$i) {
      $userId = '550e8400-e29b-41d4-a716-4470100000' . (50 + $i);
      $memberUserIds[] = $userId;
      $this->seedMember(
        $entityManager,
        $organization,
        '550e8400-e29b-41d4-a716-4470100000' . (60 + $i),
        $userId,
        true,
        $now->modify('+' . $i . ' minutes'),
      );
    }

    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');

    // Page 1: caller (joined first) + the first two seeded members.
    $client->request('GET', '/api/organizations/' . $organizationId . '/members?page=1&itemsPerPage=2&order[joinedAt]=asc', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $page1Content = (string) $client->getResponse()->getContent();
    self::assertSame(6, $this->decodeTotalItems($page1Content), 'Total must count all 6 members regardless of the page size.');
    $page1Members = $this->decodeMembers($page1Content);
    self::assertCount(2, $page1Members);
    self::assertSame($callerUserId, $page1Members[0]['userId']);
    self::assertSame($memberUserIds[0], $page1Members[1]['userId']);

    // Page 2 must return the NEXT two members, disjoint from page 1 — this is
    // the assertion that proves the provider no longer re-slices in memory
    // (it used to, and it always returned the same first page). Uses its own
    // freshly authenticated client: the token set by loginUser() does not
    // reliably survive a second request on a reused client.
    static::ensureKernelShutdown();
    $page2Client = static::createClient();
    $page2Client->loginUser($this->securityUser($callerUserId), 'api');
    $page2Client->request('GET', '/api/organizations/' . $organizationId . '/members?page=2&itemsPerPage=2&order[joinedAt]=asc', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);
    self::assertSame(200, $page2Client->getResponse()->getStatusCode(), (string) $page2Client->getResponse()->getContent());
    $page2Content = (string) $page2Client->getResponse()->getContent();
    self::assertSame(6, $this->decodeTotalItems($page2Content));
    $page2Members = $this->decodeMembers($page2Content);
    self::assertCount(2, $page2Members);
    self::assertSame($memberUserIds[1], $page2Members[0]['userId']);
    self::assertSame($memberUserIds[2], $page2Members[1]['userId']);

    $page1UserIds = array_column($page1Members, 'userId');
    $page2UserIds = array_column($page2Members, 'userId');
    self::assertEmpty(array_intersect($page1UserIds, $page2UserIds), 'Page 1 and page 2 must not overlap.');
  }

  // -------------------------------------------------------------------------
  // GET /organizations/{organizationId}/members/{memberId}
  // -------------------------------------------------------------------------

  #[Test]
  public function testGetMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID);

    $statusCode = $client->getResponse()->getStatusCode();
    self::assertNotSame(404, $statusCode);
    self::assertContains($statusCode, [401, 403]);
  }

  #[Test]
  public function testGetMemberReturns403ForCallerWithoutMembersReadPermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000001';
    $callerUserId = '550e8400-e29b-41d4-a716-447020000002';
    $targetMemberId = '550e8400-e29b-41d4-a716-447020000003';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000004', ['facility.read'], $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000005', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('GET', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId);

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGetMemberReturns404ForAnUnknownMemberId(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000011';
    $callerUserId = '550e8400-e29b-41d4-a716-447020000012';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000013', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000014', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('GET', '/api/organizations/' . $organizationId . '/members/550e8400-e29b-41d4-a716-447020009999');

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGetMemberReturns404ForAMemberBelongingToAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000021';
    $otherOrganizationId = '550e8400-e29b-41d4-a716-447020000022';
    $callerUserId = '550e8400-e29b-41d4-a716-447020000023';
    $otherOrgUserId = '550e8400-e29b-41d4-a716-447020000024';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000025', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000026', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);

    $otherOrganization = $this->seedOrganization($entityManager, $otherOrganizationId, $otherOrgUserId, $now);
    $memberInOtherOrg = $this->seedMember($entityManager, $otherOrganization, '550e8400-e29b-41d4-a716-447020000027', $otherOrgUserId, true, $now);

    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('GET', '/api/organizations/' . $organizationId . '/members/' . $memberInOtherOrg->id);

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGetMemberSucceeds(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447020000031';
    $callerUserId = '550e8400-e29b-41d4-a716-447020000032';
    $targetUserId = '550e8400-e29b-41d4-a716-447020000033';
    $targetMemberId = '550e8400-e29b-41d4-a716-447020000034';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000035', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447020000036', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, true, $now);

    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('GET', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId, server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode());

    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame($targetMemberId, $decoded['id']);
    self::assertSame($organizationId, $decoded['organizationId']);
    self::assertSame($targetUserId, $decoded['userId']);
    self::assertTrue($decoded['isActive']);
  }

  // -------------------------------------------------------------------------
  // POST /organizations/{organizationId}/members/{memberId}/reactivate
  // -------------------------------------------------------------------------

  #[Test]
  public function testReactivateMemberRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('POST', '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID . '/reactivate');

    $statusCode = $client->getResponse()->getStatusCode();
    self::assertNotSame(404, $statusCode);
    self::assertContains($statusCode, [401, 403]);
  }

  #[Test]
  public function testReactivateMemberReturns403ForCallerWithoutMembersManagePermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447030000001';
    $callerUserId = '550e8400-e29b-41d4-a716-447030000002';
    $targetMemberId = '550e8400-e29b-41d4-a716-447030000003';
    $targetUserId = '550e8400-e29b-41d4-a716-447030000004';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000005', ['organization.members.read'], $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000006', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, false, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('POST', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/reactivate');

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testReactivateMemberReturns404ForAnUnknownMemberId(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447030000011';
    $callerUserId = '550e8400-e29b-41d4-a716-447030000012';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000013', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000014', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('POST', '/api/organizations/' . $organizationId . '/members/550e8400-e29b-41d4-a716-447030009999/reactivate');

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testReactivateMemberReturns404ForAMemberBelongingToAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447030000021';
    $otherOrganizationId = '550e8400-e29b-41d4-a716-447030000022';
    $callerUserId = '550e8400-e29b-41d4-a716-447030000023';
    $otherOrgUserId = '550e8400-e29b-41d4-a716-447030000024';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000025', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000026', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);

    $otherOrganization = $this->seedOrganization($entityManager, $otherOrganizationId, $otherOrgUserId, $now);
    $memberInOtherOrg = $this->seedMember($entityManager, $otherOrganization, '550e8400-e29b-41d4-a716-447030000027', $otherOrgUserId, false, $now);

    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('POST', '/api/organizations/' . $organizationId . '/members/' . $memberInOtherOrg->id . '/reactivate');

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testReactivateMemberReturns409WhenTheMemberIsAlreadyActive(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447030000031';
    $callerUserId = '550e8400-e29b-41d4-a716-447030000032';
    $targetMemberId = '550e8400-e29b-41d4-a716-447030000033';
    $targetUserId = '550e8400-e29b-41d4-a716-447030000034';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000035', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000036', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, true, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('POST', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/reactivate');

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testReactivateMemberReturns409WhenTheOrganizationIsArchived(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447030000041';
    $callerUserId = '550e8400-e29b-41d4-a716-447030000042';
    $targetMemberId = '550e8400-e29b-41d4-a716-447030000043';
    $targetUserId = '550e8400-e29b-41d4-a716-447030000044';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now, 'archived');
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000045', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000046', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, false, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('POST', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/reactivate');

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testReactivateMemberSucceeds(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447030000051';
    $callerUserId = '550e8400-e29b-41d4-a716-447030000052';
    $targetMemberId = '550e8400-e29b-41d4-a716-447030000053';
    $targetUserId = '550e8400-e29b-41d4-a716-447030000054';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000055', $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447030000056', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, false, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('POST', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/reactivate', server: [
      'HTTP_ACCEPT' => 'application/ld+json',
    ]);

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame($targetMemberId, $decoded['id']);
    self::assertTrue($decoded['isActive']);

    $reloaded = $entityManager->find(OrganizationMemberRecord::class, $targetMemberId);
    self::assertInstanceOf(OrganizationMemberRecord::class, $reloaded);
    self::assertTrue($reloaded->isActive);
  }

  // -------------------------------------------------------------------------
  // PUT /organizations/{organizationId}/members/{memberId}/roles
  // -------------------------------------------------------------------------

  #[Test]
  public function testSetMemberRolesRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('PUT', '/api/organizations/' . self::DUMMY_UUID . '/members/' . self::DUMMY_UUID . '/roles', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: (string) json_encode(['roleIds' => []]));

    $statusCode = $client->getResponse()->getStatusCode();
    self::assertNotSame(404, $statusCode);
    self::assertContains($statusCode, [401, 403]);
  }

  #[Test]
  public function testSetMemberRolesReturns403ForCallerWithoutRolesManagePermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447040000001';
    $callerUserId = '550e8400-e29b-41d4-a716-447040000002';
    $targetMemberId = '550e8400-e29b-41d4-a716-447040000003';
    $targetUserId = '550e8400-e29b-41d4-a716-447040000004';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000005', ['organization.members.read'], $now);
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000006', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, true, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('PUT', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/roles', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: (string) json_encode(['roleIds' => []]));

    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSetMemberRolesReturns422ForANonUuidRoleId(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447040000011';
    $callerUserId = '550e8400-e29b-41d4-a716-447040000012';
    $targetMemberId = '550e8400-e29b-41d4-a716-447040000013';
    $targetUserId = '550e8400-e29b-41d4-a716-447040000014';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000015', $now, 'admin_role');
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000016', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, true, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('PUT', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/roles', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: (string) json_encode(['roleIds' => ['not-a-uuid']]));

    self::assertSame(422, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSetMemberRolesReturns404ForAnUnknownRoleId(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447040000021';
    $callerUserId = '550e8400-e29b-41d4-a716-447040000022';
    $targetMemberId = '550e8400-e29b-41d4-a716-447040000023';
    $targetUserId = '550e8400-e29b-41d4-a716-447040000024';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000025', $now, 'admin_role');
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000026', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, true, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('PUT', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/roles', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: (string) json_encode(['roleIds' => ['550e8400-e29b-41d4-a716-447040009999']]));

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSetMemberRolesReturns404ForAMemberBelongingToAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447040000031';
    $otherOrganizationId = '550e8400-e29b-41d4-a716-447040000032';
    $callerUserId = '550e8400-e29b-41d4-a716-447040000033';
    $otherOrgUserId = '550e8400-e29b-41d4-a716-447040000034';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000035', $now, 'admin_role');
    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000036', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);

    $otherOrganization = $this->seedOrganization($entityManager, $otherOrganizationId, $otherOrgUserId, $now);
    $memberInOtherOrg = $this->seedMember($entityManager, $otherOrganization, '550e8400-e29b-41d4-a716-447040000037', $otherOrgUserId, true, $now);

    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('PUT', '/api/organizations/' . $organizationId . '/members/' . $memberInOtherOrg->id . '/roles', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: (string) json_encode(['roleIds' => []]));

    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSetMemberRolesReturns409WhenItWouldStripTheLastAdministrator(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447040000041';
    $callerUserId = '550e8400-e29b-41d4-a716-447040000042';
    $callerMemberId = '550e8400-e29b-41d4-a716-447040000043';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    // The caller is the ORGANIZATION'S ONLY active member, holding the only
    // admin-granting role. Replacing their own role set with an empty one
    // must be refused: it would leave the organization without any
    // administrator.
    $role = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000044', $now, 'sole_admin_role');
    $caller = $this->seedMember($entityManager, $organization, $callerMemberId, $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $role, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('PUT', '/api/organizations/' . $organizationId . '/members/' . $callerMemberId . '/roles', server: [
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: (string) json_encode(['roleIds' => []]));

    self::assertSame(409, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSetMemberRolesSucceeds(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organizationId = '550e8400-e29b-41d4-a716-447040000051';
    $callerUserId = '550e8400-e29b-41d4-a716-447040000052';
    $targetMemberId = '550e8400-e29b-41d4-a716-447040000053';
    $targetUserId = '550e8400-e29b-41d4-a716-447040000054';

    $organization = $this->seedOrganization($entityManager, $organizationId, $callerUserId, $now);
    $adminRole = $this->seedFullAccessRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000055', $now, 'admin_role');
    $newRole = $this->seedRole($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000056', ['facility.read'], $now, 'inspector_role');

    $caller = $this->seedMember($entityManager, $organization, '550e8400-e29b-41d4-a716-447040000057', $callerUserId, true, $now);
    $this->assignRole($entityManager, $caller, $adminRole, $now);

    $target = $this->seedMember($entityManager, $organization, $targetMemberId, $targetUserId, true, $now);
    $this->assignRole($entityManager, $target, $adminRole, $now);

    $entityManager->flush();

    $client->loginUser($this->securityUser($callerUserId), 'api');
    $client->request('PUT', '/api/organizations/' . $organizationId . '/members/' . $targetMemberId . '/roles', server: [
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
    ], content: (string) json_encode(['roleIds' => [$newRole->id]]));

    $response = $client->getResponse();
    self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $decoded = $this->decodeObject((string) $response->getContent());
    self::assertSame($targetMemberId, $decoded['id']);
    self::assertSame([$newRole->id], $decoded['roleIds']);
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
   * Decodes a single-resource JSON response body.
   *
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

  /**
   * Decodes a collection response's `totalItems`.
   */
  private function decodeTotalItems(string $content): int
  {
    $body = $this->decodeObject($content);
    self::assertArrayHasKey('totalItems', $body);
    $totalItems = $body['totalItems'];
    self::assertIsInt($totalItems);

    return $totalItems;
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
    $organization->name = 'Member API Test ' . $id;
    $organization->slug = 'member-api-test-' . $id;
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
