<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\Geocoding\AddressSuggestion;
use Facility\Application\Port\Outbound\AddressSuggestionsPort;
use Facility\Domain\Exception\FacilityAddressSuggestionsUnavailableException;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function http_build_query;
use function json_decode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Test FacilityAddressSuggestionsApiTest.
 *
 * Exercises the HTTP contract, authorization and upstream failure mapping.
 * Successful and unavailable searches replace the outbound port in the test
 * container, so no functional request reaches a public geocoding service.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAddressSuggestionsApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '650e8400-e29b-41d4-a716-449004000001';

  private const string ADMIN_USER_ID = '650e8400-e29b-41d4-a716-449004000002';

  #[Test]
  public function testSuggestionsWithoutAnAddressReturns400(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions');

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSuggestionsWithAnOverlongAddressReturns400(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions?' . http_build_query([
      'q' => str_repeat('a', 251),
    ]));

    self::assertSame(400, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function testSuggestionsRequiresAuthentication(): void
  {
    $client = static::createClient();
    $this->seedOrganizationWithFullAccessAdmin();

    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions?q=Paris');

    self::assertContains($client->getResponse()->getStatusCode(), [401, 403]);
  }

  #[Test]
  public function testSuggestionsReturns403ForAMemberWithoutTheFacilitiesWritePermission(): void
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
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions?q=Paris');

    self::assertSame(
      expected: 403,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A member with only organization.facilities.read must be refused with 403 — the geocode aid is write-gated.',
    );
  }

  #[Test]
  public function testSuggestionsReturns404ForAMemberOfAnotherOrganization(): void
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
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions?q=Paris');

    self::assertSame(
      expected: 404,
      actual: $client->getResponse()->getStatusCode(),
      message: 'A caller outside the organization must get 404, not 403 — 403 would confirm the organization exists.',
    );
  }

  #[Test]
  public function testSuggestionsSerializeConcreteMatches(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $this->seedOrganizationWithFullAccessAdmin();
    $port = $this->createMock(AddressSuggestionsPort::class);
    $port->expects(self::once())->method('suggest')->with('10 Rue de Paris')->willReturn([
      new AddressSuggestion('10 Rue de Paris, 75001 Paris, France', 48.86, 2.35),
    ]);
    static::getContainer()->set(AddressSuggestionsPort::class, $port);
    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions?q=10%20Rue%20de%20Paris', server: ['HTTP_ACCEPT' => 'application/ld+json']);
    self::assertResponseIsSuccessful();
    $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($payload);
    self::assertIsArray($payload['member']);
    self::assertCount(1, $payload['member']);
    self::assertIsArray($payload['member'][0]);
    self::assertSame(1, $payload['totalItems']);
    self::assertSame('10 Rue de Paris, 75001 Paris, France', $payload['member'][0]['displayName']);
    self::assertSame(48.86, $payload['member'][0]['latitude']);
    self::assertSame(2.35, $payload['member'][0]['longitude']);
  }

  #[Test]
  public function testEmptySearchIsSuccessfulRatherThanUnavailable(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $this->seedOrganizationWithFullAccessAdmin();
    $port = $this->createStub(AddressSuggestionsPort::class);
    $port->method('suggest')->willReturn([]);
    static::getContainer()->set(AddressSuggestionsPort::class, $port);
    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions?q=Unknown');
    self::assertResponseIsSuccessful();
    $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($payload);
    self::assertSame([], $payload['member']);
    self::assertSame(0, $payload['totalItems']);
  }

  #[Test]
  public function testProviderFailureIs503(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $this->seedOrganizationWithFullAccessAdmin();
    $port = $this->createStub(AddressSuggestionsPort::class);
    $port->method('suggest')->willThrowException(new FacilityAddressSuggestionsUnavailableException());
    static::getContainer()->set(AddressSuggestionsPort::class, $port);
    $client->loginUser($this->securityUser(self::ADMIN_USER_ID), 'api');
    $client->request('GET', '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/address-suggestions?q=Unknown');
    self::assertResponseStatusCodeSame(503);
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
