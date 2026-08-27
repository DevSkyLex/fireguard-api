<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function json_encode;

/**
 * Test CanonicalEquipmentApiTest.
 *
 * The flat `PATCH`/`DELETE /api/equipment/{id}` contract, frozen while its
 * mutations moved out of `CanonicalEquipmentMutationProcessor` into use
 * cases. Every status asserted here is one the processor emitted before the
 * move.
 *
 * One HTTP request per test: the token `loginUser()` sets does not reliably
 * survive a second one in this suite, and a stray 401 would hide the
 * difference being measured.
 *
 * @category Functional Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalEquipmentApiTest extends WebTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '890e8400-e29b-41d4-a716-446655493001';

  private const string OUTSIDER_ORGANIZATION_ID = '890e8400-e29b-41d4-a716-446655493002';

  private const string ADMIN_USER_ID = '890e8400-e29b-41d4-a716-446655493003';

  private const string READ_ONLY_USER_ID = '890e8400-e29b-41d4-a716-446655493004';

  private const string OUTSIDER_USER_ID = '890e8400-e29b-41d4-a716-446655493005';

  private const string FACILITY_ID = '890e8400-e29b-41d4-a716-446655493006';

  private const string OUTSIDER_FACILITY_ID = '890e8400-e29b-41d4-a716-446655493007';

  private const string IN_STOCK_ID = '890e8400-e29b-41d4-a716-446655493010';

  private const string OPERATIONAL_ID = '890e8400-e29b-41d4-a716-446655493011';

  private const string DECOMMISSIONED_ID = '890e8400-e29b-41d4-a716-446655493012';

  private const string SCRATCHPAD_ID = '890e8400-e29b-41d4-a716-446655493013';

  private const string OUTSIDER_EQUIPMENT_ID = '890e8400-e29b-41d4-a716-446655493014';

  private const string UNKNOWN_ID = '890e8400-e29b-41d4-a716-4466554930ff';
  // #endregion

  // #region Tests
  /**
   * Method testPatchCommissionsAnAssetAndBumpsTheRevision.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchCommissionsAnAssetAndBumpsTheRevision(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/equipment/' . self::IN_STOCK_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => 'operational', 'facility' => '/api/facilities/' . self::FACILITY_ID]),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertSame('operational', $payload['status']);
    self::assertSame(2, $payload['revision']);
  }

  /**
   * Method testPatchErasesABrandSentAsNullAndKeepsAnAbsentField.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchErasesABrandSentAsNullAndKeepsAnAbsentField(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/equipment/' . self::IN_STOCK_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['brand' => null]),
    );

    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    $payload = $this->payload($client);
    self::assertNull($payload['brand']);
    self::assertSame('Seeded model', $payload['model']);
  }

  /**
   * Method testAnIllegalPublishedTransitionIsUnprocessable.
   *
   * Decommissioned is terminal — an asset is never revived.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnIllegalPublishedTransitionIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/equipment/' . self::DECOMMISSIONED_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => 'operational', 'facility' => '/api/facilities/' . self::FACILITY_ID]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString(
      'Illegal equipment status transition from decommissioned to operational.',
      (string) $client->getResponse()->getContent(),
    );
  }

  /**
   * Method testANullStatusIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullStatusIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/equipment/' . self::IN_STOCK_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['status' => null]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Equipment status cannot be null.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAFacilityFromAnotherOrganizationIsUnprocessable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAFacilityFromAnotherOrganizationIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/equipment/' . self::IN_STOCK_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['facility' => '/api/facilities/' . self::OUTSIDER_FACILITY_ID]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('Facility must belong to the same organization.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testClearingTheFacilityOfAnInServiceAssetIsUnprocessable.
   *
   * Checked on every patch, not only on a status change.
   *
   * @return void no return value
   */
  #[Test]
  public function testClearingTheFacilityOfAnInServiceAssetIsUnprocessable(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'PATCH',
      '/api/equipment/' . self::OPERATIONAL_ID,
      server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-1"']),
      content: (string) json_encode(['facility' => null]),
    );

    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertStringContainsString('In-service equipment must be assigned to a facility.', (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteDecommissionsAPublishedAsset.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteDecommissionsAPublishedAsset(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/equipment/' . self::OPERATIONAL_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteIsIdempotentOnAnAlreadyDecommissionedAsset.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteIsIdempotentOnAnAlreadyDecommissionedAsset(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/equipment/' . self::DECOMMISSIONED_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testDeleteHardDeletesAScratchpadRow.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteHardDeletesAScratchpadRow(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/equipment/' . self::SCRATCHPAD_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(204, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAStaleRevisionAnswersPreconditionFailed.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionAnswersPreconditionFailed(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/equipment/' . self::IN_STOCK_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-99"']),
    );

    self::assertSame(412, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAnUnknownAssetAnswersNotFoundEvenWithoutIfMatch.
   *
   * The ordering contract: absent answers 404 before `RevisionGuard` gets to
   * raise its 428 for the missing header.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownAssetAnswersNotFoundEvenWithoutIfMatch(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('DELETE', '/api/equipment/' . self::UNKNOWN_ID, server: $this->headers());

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAMalformedIdentifierAnswersNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersNotFound(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request('DELETE', '/api/equipment/not-a-uuid', server: $this->headers());

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAForeignAssetAnswersNotFoundRatherThanForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAForeignAssetAnswersNotFoundRatherThanForbidden(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::ADMIN_USER_ID, 'admin@example.com');

    $client->request(
      'DELETE',
      '/api/equipment/' . self::OUTSIDER_EQUIPMENT_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  /**
   * Method testAMemberWithoutWritePermissionIsForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMemberWithoutWritePermissionIsForbidden(): void
  {
    $client = static::createClient();
    $this->seed();
    $this->loginAs($client, self::READ_ONLY_USER_ID, 'reader@example.com');

    $client->request(
      'DELETE',
      '/api/equipment/' . self::IN_STOCK_ID,
      server: $this->headers(['HTTP_IF_MATCH' => '"revision-1"']),
    );

    self::assertSame(403, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }
  // #endregion

  // #region Helpers
  /**
   * Method headers.
   *
   * @param array<string, string> $extra additional server parameters
   *
   * @return array<string, string> the server parameters
   */
  private function headers(array $extra = []): array
  {
    // `$extra` first: `+` keeps the LEFT operand's keys, so defaults placed
    // first would silently ignore a CONTENT_TYPE override.
    return $extra + ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
  }

  /**
   * Method payload.
   *
   * @param KernelBrowser $client the browser holding the response
   *
   * @return array<string, mixed> the decoded body
   */
  private function payload(KernelBrowser $client): array
  {
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $client->getResponse()->getContent(), true);

    return $decoded;
  }

  /**
   * Method loginAs.
   *
   * @param KernelBrowser $client the browser to authenticate
   * @param string $userId the user identifier
   * @param string $email the user email
   */
  private function loginAs(KernelBrowser $client, string $userId, string $email): void
  {
    $client->loginUser(
      new SecurityUser(id: $userId, email: $email, password: 'hashed-password', roles: ['ROLE_USER']),
      'api',
    );
  }

  /**
   * Method seed.
   *
   * Rebuilds the fixture set on every test: two organizations with a facility
   * each, three callers, and one asset per canonical state.
   */
  private function seed(): void
  {
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

    $organization = $this->newOrganization(self::ORGANIZATION_ID, self::ADMIN_USER_ID, $now);
    $outsiderOrganization = $this->newOrganization(self::OUTSIDER_ORGANIZATION_ID, self::OUTSIDER_USER_ID, $now);
    $entityManager->persist($organization);
    $entityManager->persist($outsiderOrganization);

    $entityManager->persist($this->newRole('890e8400-e29b-41d4-a716-446655493040', $organization, 'canonical-equipment-full', ['*'], $now));
    $entityManager->persist($this->newRole('890e8400-e29b-41d4-a716-446655493041', $organization, 'canonical-equipment-read', ['organization.equipment.read'], $now));
    $entityManager->persist($this->newRole('890e8400-e29b-41d4-a716-446655493042', $outsiderOrganization, 'canonical-equipment-outsider', ['*'], $now));
    $entityManager->flush();

    $this->assignMember($entityManager, '890e8400-e29b-41d4-a716-446655493050', $organization, self::ADMIN_USER_ID, '890e8400-e29b-41d4-a716-446655493040', $now);
    $this->assignMember($entityManager, '890e8400-e29b-41d4-a716-446655493051', $organization, self::READ_ONLY_USER_ID, '890e8400-e29b-41d4-a716-446655493041', $now);
    $this->assignMember($entityManager, '890e8400-e29b-41d4-a716-446655493052', $outsiderOrganization, self::OUTSIDER_USER_ID, '890e8400-e29b-41d4-a716-446655493042', $now);
    $entityManager->flush();

    $entityManager->persist($this->newFacility(self::FACILITY_ID, $organization, $now));
    $entityManager->persist($this->newFacility(self::OUTSIDER_FACILITY_ID, $outsiderOrganization, $now));
    $entityManager->flush();

    $entityManager->persist($this->newEquipment(self::IN_STOCK_ID, $organization, 'in_stock', 'published', null, $now));
    $entityManager->persist($this->newEquipment(self::OPERATIONAL_ID, $organization, 'operational', 'published', self::FACILITY_ID, $now));
    $entityManager->persist($this->newEquipment(self::DECOMMISSIONED_ID, $organization, 'decommissioned', 'published', null, $now));
    $entityManager->persist($this->newEquipment(self::SCRATCHPAD_ID, $organization, 'in_stock', 'draft', null, $now));
    $entityManager->persist($this->newEquipment(self::OUTSIDER_EQUIPMENT_ID, $outsiderOrganization, 'in_stock', 'published', null, $now));
    $entityManager->flush();
    $entityManager->clear();
  }

  /**
   * Method newOrganization.
   *
   * @param string $id the organization identifier
   * @param string $ownerUserId the owner user identifier
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return OrganizationRecord the organization record
   */
  private function newOrganization(string $id, string $ownerUserId, DateTimeImmutable $now): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Canonical Equipment API Test Org ' . $id;
    $organization->slug = 'canonical-equipment-api-test-' . $id;
    $organization->ownerUserId = $ownerUserId;
    $organization->createdByUserId = $ownerUserId;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;

    return $organization;
  }

  /**
   * Method newRole.
   *
   * @param string $id the role identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $name the role name
   * @param list<string> $permissions the granted permissions
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return OrganizationRoleRecord the role record
   */
  private function newRole(string $id, OrganizationRecord $organization, string $name, array $permissions, DateTimeImmutable $now): OrganizationRoleRecord
  {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->permissions = $permissions;
    $role->description = 'Functional-test-only role.';
    $role->isSystem = false;
    $role->createdAt = $now;

    return $role;
  }

  /**
   * Method assignMember.
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   * @param string $memberId the membership identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $userId the user identifier
   * @param string $roleId the role to assign
   * @param DateTimeImmutable $now the fixed clock
   */
  private function assignMember(
    EntityManagerInterface $entityManager,
    string $memberId,
    OrganizationRecord $organization,
    string $userId,
    string $roleId,
    DateTimeImmutable $now,
  ): void {
    $member = new OrganizationMemberRecord();
    $member->id = $memberId;
    $member->organization = $organization;
    $member->userId = $userId;
    $member->isActive = true;
    $member->joinedAt = $now;
    $entityManager->persist($member);

    $role = $entityManager->find(OrganizationRoleRecord::class, $roleId);
    self::assertInstanceOf(OrganizationRoleRecord::class, $role);

    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $entityManager->persist($assignment);
  }

  /**
   * Method newFacility.
   *
   * @param string $id the facility identifier
   * @param OrganizationRecord $organization the owning organization
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return FacilityRecord the facility record
   */
  private function newFacility(string $id, OrganizationRecord $organization, DateTimeImmutable $now): FacilityRecord
  {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Canonical Equipment Test Site ' . $id;
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;

    return $facility;
  }

  /**
   * Method newEquipment.
   *
   * @param string $id the equipment identifier
   * @param OrganizationRecord $organization the owning organization
   * @param string $status the asset status
   * @param string $recordStatus the record status
   * @param ?string $facilityId the assigned facility
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return EquipmentRecord the equipment record
   */
  private function newEquipment(
    string $id,
    OrganizationRecord $organization,
    string $status,
    string $recordStatus,
    ?string $facilityId,
    DateTimeImmutable $now,
  ): EquipmentRecord {
    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->recordStatus = $recordStatus;
    $equipment->revision = 1;
    $equipment->facilityId = $facilityId;
    $equipment->type = 'fire_extinguisher';
    $equipment->brand = 'Seeded brand';
    $equipment->model = 'Seeded model';
    $equipment->status = $status;
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;

    return $equipment;
  }
  // #endregion
}
