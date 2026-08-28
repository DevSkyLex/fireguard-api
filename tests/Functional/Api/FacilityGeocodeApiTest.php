<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;
use function str_repeat;

/**
 * Test FacilityGeocodeApiTest.
 *
 * `GET /api/organizations/{organizationId}/facilities/geocode?address=…` —
 * the server-side address-geocoding input aid. Covers the denial paths: 400
 * for a missing address, 401 for an unauthenticated caller, 403 for a member
 * without `organization.facilities.write` (write, not read — geocoding
 * exists to fill a facility's coordinates), and 404 for a member of another
 * organization.
 *
 * **The 200 path deliberately has no functional test.** It would require a
 * real outbound HTTP call, and calling the actual Nominatim from CI would
 * violate its usage policy (and make the suite network-dependent).
 * `.env.test` pins `GEOCODING_BASE_URL` to an unroutable address so no test
 * can ever reach the real service; the success contract is covered instead
 * by `Tests\Unit\Facility\Application\UseCase\Query\GeocodeAddress\GeocodeAddressHandlerTest`
 * (handler success shape) and
 * `Tests\Unit\Facility\Infrastructure\Adapter\Geocoding\NominatimGeocodingAdapterTest`
 * (jsonv2 parsing against `MockHttpClient`). Same trade the export endpoint
 * makes for its row-cap path. The 404-address-unknown path is likewise a
 * handler unit concern.
 *
 * All four requests here fail before the adapter (limiter → auth → address
 * presence → permission/scope in the handler), so none consumes the outbound
 * geocoding budget even locally.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityGeocodeApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '650e8400-e29b-41d4-a716-449004000001';

  private const string ADMIN_USER_ID = '650e8400-e29b-41d4-a716-449004000002';

  #[Test]
  public function testGeocodeWithoutAnAddressReturns400(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/geocode');

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGeocodeWithAnOverlongAddressReturns400(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/geocode?' . http_build_query([
      'address' => str_repeat('a', 301),
    ]));

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testGeocodeRequiresAuthentication(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/geocode?address=Paris');

    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testGeocodeReturns403ForAMemberWithoutTheFacilitiesWritePermission(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $adminRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449004000010', ['*'], $now, 'full_access');
    $admin = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449004000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $admin, $adminRole, $now);

    // `organization.facilities.read` on purpose: the geocode endpoint is
    // gated on `.write` (it is a data-entry aid), so a read-only member is
    // exactly the caller that must be refused.
    $readOnlyUserId = '650e8400-e29b-41d4-a716-449004000040';
    $readOnlyRole = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449004000041', ['organization.facilities.read'], $now, 'facilities_read_only');
    $readOnlyMember = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449004000042', $readOnlyUserId, $now);
    $this->assignRole($entityManager, $readOnlyMember, $readOnlyRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($readOnlyUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/geocode?address=Paris');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member with only organization.facilities.read must be refused with 403 — the geocode aid is write-gated.',
    );
  }

  #[Test]
  public function testGeocodeReturns404ForAMemberOfAnotherOrganization(): void
  {
    $client = static::createClient();
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $this->seedOrganizationWithFullAccessAdmin();

    $outsiderUserId = '650e8400-e29b-41d4-a716-449004000050';
    $otherOrganization = $this->seedOrganization($entityManager, '650e8400-e29b-41d4-a716-449004000051', $outsiderUserId, $now);
    $outsiderRole = $this->seedRole($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449004000052', ['*'], $now, 'other_org_full_access');
    $outsiderMember = $this->seedMember($entityManager, $otherOrganization, '650e8400-e29b-41d4-a716-449004000053', $outsiderUserId, $now);
    $this->assignRole($entityManager, $outsiderMember, $outsiderRole, $now);
    $entityManager->flush();

    $client->loginUser($this->securityUser($outsiderUserId), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/geocode?address=Paris');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the organization must get 404, not 403 — 403 would confirm the organization exists.',
    );
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

  private function seedOrganizationWithFullAccessAdmin(): void
  {
    $entityManager = $this->entityManager();
    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->seedOrganization($entityManager, self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $role = $this->seedRole($entityManager, $organization, '650e8400-e29b-41d4-a716-449004000010', ['*'], $now, 'full_access');
    $member = $this->seedMember($entityManager, $organization, '650e8400-e29b-41d4-a716-449004000011', self::ADMIN_USER_ID, $now);
    $this->assignRole($entityManager, $member, $role, $now);
    $entityManager->flush();
  }

  private function seedOrganization(
    EntityManagerInterface $entityManager,
    string $id,
    string $ownerUserId,
    DateTimeImmutable $now,
  ): OrganizationRecord {
    $existing = $entityManager->find(OrganizationRecord::class, $id);
    if ($existing instanceof OrganizationRecord) {
      $entityManager->remove($existing);
      $entityManager->flush();
    }

    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Geocode API Test ' . $id;
    $organization->slug = 'facility-geocode-api-test-' . $id;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
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
    string $name,
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

  private function seedMember(
    EntityManagerInterface $entityManager,
    OrganizationRecord $organization,
    string $id,
    string $userId,
    DateTimeImmutable $joinedAt,
  ): OrganizationMemberRecord {
    $member = new OrganizationMemberRecord();
    $member->id = $id;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
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
