<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Domain\Exception\FacilityHasActiveDependentsException;
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId
};
use Facility\Infrastructure\Adapter\Intervention\FacilityInterventionResourceAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Domain\Exception\InterventionConflictException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;

/**
 * Test FacilityInterventionResourceAdapter::apply().
 *
 * Focuses on the proposal-application surface (apply + its private helpers id(),
 * assertPatchFields(), assertNoParentCycle() and the archival-guard branch) that
 * the sibling FacilityInterventionResourceAdapterTest deliberately leaves out.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityInterventionResourceAdapter::class)]
final class FacilityInterventionResourceAdapterApplyTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440000';

  private const string OTHER_ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440001';

  private const string OWNER_ID = '660e8400-e29b-41d4-a716-446655449000';

  private const string TARGET_ID = '660e8400-e29b-41d4-a716-446655440010';

  private const string PARENT_ID = '660e8400-e29b-41d4-a716-446655440011';

  private const string ARCHIVED_PARENT_ID = '660e8400-e29b-41d4-a716-446655440012';

  private const string ARCHIVED_CHILD_ID = '660e8400-e29b-41d4-a716-446655440013';

  private const string DRAFT_ID = '660e8400-e29b-41d4-a716-446655440014';

  private const string MISSING_ID = '660e8400-e29b-41d4-a716-446655440099';

  private const string CREATED_AT = '2026-02-12T10:00:00+00:00';

  private EntityManagerInterface $entityManager;

  private FacilityRepositoryPort $facilityRepository;

  private FacilityInterventionResourceAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    /** @var FacilityRepositoryPort $facilityRepository */
    $facilityRepository = static::getContainer()->get(FacilityRepositoryPort::class);
    $this->facilityRepository = $facilityRepository;

    $this->cleanup();

    // The archival guard is a non-DB collaborator; a stub keeps every non-archiving
    // branch deterministic. The archiving branches inject their own guard double.
    $guard = self::createStub(FacilityArchivalGuardPort::class);
    $this->adapter = new FacilityInterventionResourceAdapter($this->entityManager, $guard, $this->facilityRepository, $this->permissiveMetadataSchemaGuard());

    $this->createOrganization();
    $this->createFacility(self::TARGET_ID, 'building', 'Warehouse', 'active', 'published');
    $this->createFacility(self::PARENT_ID, 'site', 'Campus', 'active', 'published');
    $this->createFacility(self::ARCHIVED_PARENT_ID, 'site', 'Retired Site', 'archived', 'published');
    $this->createFacility(self::ARCHIVED_CHILD_ID, 'building', 'Retired Wing', 'archived', 'published', self::ARCHIVED_PARENT_ID);
    $this->createFacility(self::DRAFT_ID, 'zone', 'Draft Zone', 'active', 'draft');
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testApplyRejectsUnknownPatchFields(): void
  {
    $exception = $this->assertApplyConflict(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['colour' => 'red']);

    self::assertStringContainsString('Unsupported facility patch fields: colour', $exception->getMessage());
  }

  #[Test]
  public function testApplyRejectsMalformedResourceIri(): void
  {
    $exception = $this->assertApplyConflict(self::ORGANIZATION_ID, '/api/equipment/' . self::TARGET_ID, []);

    self::assertSame('Invalid facility resource IRI.', $exception->getMessage());
  }

  #[Test]
  public function testApplyRejectsInvalidTargetState(): void
  {
    // Missing record.
    self::assertSame(
      'Proposed facility change target is invalid.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $this->iri(self::MISSING_ID), ['name' => 'X'])->getMessage(),
    );
    // Owned by another organization (tenant isolation).
    self::assertSame(
      'Proposed facility change target is invalid.',
      $this->assertApplyConflict(self::OTHER_ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['name' => 'X'])->getMessage(),
    );
    // Still a draft scratchpad record, not the published canonical row.
    self::assertSame(
      'Proposed facility change target is invalid.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $this->iri(self::DRAFT_ID), ['name' => 'X'])->getMessage(),
    );
  }

  #[Test]
  public function testApplyRejectsInvalidFieldValues(): void
  {
    $target = $this->iri(self::TARGET_ID);

    self::assertSame(
      'Proposed facility type is invalid.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['type' => 'spaceship'])->getMessage(),
    );
    self::assertSame(
      'Facility name cannot be empty.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['name' => '   '])->getMessage(),
    );
    self::assertSame(
      'Facility field "code" must be a string or null.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['code' => 123])->getMessage(),
    );
    self::assertSame(
      'Proposed facility metadata must be an object.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['metadata' => 'not-an-object'])->getMessage(),
    );
    self::assertSame(
      'Proposed facility status is invalid.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['status' => 'frozen'])->getMessage(),
    );
    self::assertSame(
      'Proposed parent facility must be an IRI or null.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['parent' => 42])->getMessage(),
    );
  }

  #[Test]
  public function testApplyRejectsInvalidCoordinates(): void
  {
    $target = $this->iri(self::TARGET_ID);

    // One half of the pair, mirroring the canonical mutation processor.
    self::assertSame(
      'Facility latitude and longitude must be provided together.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['latitude' => 48.8566])->getMessage(),
    );
    self::assertSame(
      'Facility latitude and longitude must be provided together.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['longitude' => 2.3522])->getMessage(),
    );
    self::assertSame(
      'Facility latitude and longitude must be provided together.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['latitude' => 48.8566, 'longitude' => null])->getMessage(),
    );
    self::assertSame(
      'Facility latitude must be a number or null.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['latitude' => '48.85', 'longitude' => 2.3522])->getMessage(),
    );
    self::assertSame(
      'Facility longitude must be a number or null.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['latitude' => 48.8566, 'longitude' => '2.35'])->getMessage(),
    );
    self::assertSame(
      'Facility coordinates are out of range.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['latitude' => 91.0, 'longitude' => 2.3522])->getMessage(),
    );
    self::assertSame(
      'Facility coordinates are out of range.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $target, ['latitude' => 48.8566, 'longitude' => -180.5])->getMessage(),
    );
  }

  #[Test]
  public function testApplySetsThenClearsCoordinates(): void
  {
    $this->adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), [
      'latitude' => 48.8566,
      'longitude' => 2.3522,
    ]);

    $record = $this->entityManager->find(FacilityRecord::class, self::TARGET_ID);
    self::assertInstanceOf(FacilityRecord::class, $record);
    self::assertSame(48.8566, $record->latitude);
    self::assertSame(2.3522, $record->longitude);
    self::assertSame(2, $record->revision);

    $this->adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), [
      'latitude' => null,
      'longitude' => null,
    ]);
    self::assertNull($record->latitude);
    self::assertNull($record->longitude);
    self::assertSame(3, $record->revision);
  }

  #[Test]
  public function testApplyUpdatesScalarFieldsAndBumpsRevision(): void
  {
    $this->adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), [
      'type' => 'zone',
      'name' => '  Renamed Warehouse  ',
      'code' => '  Z-9  ',
      // null path of the code/address loop is exercised alongside the string path.
      'address' => null,
      'metadata' => ['floors' => 3],
      'status' => 'active',
    ]);

    $record = $this->entityManager->find(FacilityRecord::class, self::TARGET_ID);
    self::assertInstanceOf(FacilityRecord::class, $record);
    self::assertSame('zone', $record->type);
    self::assertSame('Renamed Warehouse', $record->name);
    self::assertSame('Z-9', $record->code);
    self::assertNull($record->address);
    self::assertSame(['floors' => 3], $record->metadata);
    self::assertSame('active', $record->status);
    self::assertSame(2, $record->revision);
    self::assertGreaterThan($record->createdAt, $record->updatedAt);
  }

  #[Test]
  public function testApplyAssignsThenClearsParent(): void
  {
    $this->adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['parent' => $this->iri(self::PARENT_ID)]);

    $record = $this->entityManager->find(FacilityRecord::class, self::TARGET_ID);
    self::assertInstanceOf(FacilityRecord::class, $record);
    self::assertInstanceOf(FacilityRecord::class, $record->parentFacility);
    self::assertSame(self::PARENT_ID, $record->parentFacility->id);
    self::assertSame(2, $record->revision);

    $this->adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['parent' => null]);
    self::assertNull($record->parentFacility);
    self::assertSame(3, $record->revision);
  }

  #[Test]
  public function testApplyRejectsInvalidParentAssignments(): void
  {
    // Parent does not exist.
    self::assertSame(
      'Proposed parent facility is invalid.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['parent' => $this->iri(self::MISSING_ID)])->getMessage(),
    );
    // Parent is archived.
    self::assertSame(
      'Proposed parent facility is archived.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['parent' => $this->iri(self::ARCHIVED_PARENT_ID)])->getMessage(),
    );
    // A facility cannot become its own parent (direct cycle).
    self::assertSame(
      'Proposed parent facility would create a hierarchy cycle.',
      $this->assertApplyConflict(self::ORGANIZATION_ID, $this->iri(self::PARENT_ID), ['parent' => $this->iri(self::PARENT_ID)])->getMessage(),
    );
  }

  #[Test]
  public function testApplyArchivingConsultsGuardAndSucceedsWhenNoDependents(): void
  {
    $this->adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['status' => 'archived']);

    $record = $this->entityManager->find(FacilityRecord::class, self::TARGET_ID);
    self::assertInstanceOf(FacilityRecord::class, $record);
    self::assertSame('archived', $record->status);
    self::assertSame(2, $record->revision);
  }

  #[Test]
  public function testApplyArchivingIsRejectedWhenGuardReportsActiveDependents(): void
  {
    $guard = self::createMock(FacilityArchivalGuardPort::class);
    $guard->expects(self::once())
      ->method('assertNoActiveDependents')
      ->with(self::ORGANIZATION_ID, self::TARGET_ID)
      ->willThrowException(FacilityHasActiveDependentsException::withActiveEquipment(self::TARGET_ID));
    $adapter = new FacilityInterventionResourceAdapter($this->entityManager, $guard, $this->facilityRepository, $this->permissiveMetadataSchemaGuard());

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('cannot be archived while it has active equipment assigned');

    $adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['status' => 'archived']);
  }

  #[Test]
  public function testApplyRefusesParentAssignmentThatWouldExceedTheDepthCap(): void
  {
    // Builds a straight published chain 8 levels deep (root = level 1), so
    // the deepest node sits exactly at the default FACILITY_MAX_DEPTH cap.
    $chain = [];
    $previousId = null;
    for ($level = 1; $level <= 8; ++$level) {
      $id = $this->chainFacilityId($level);
      $this->createFacility($id, 'zone', 'Chain Level ' . $level, 'active', 'published', $previousId);
      $chain[$level] = $id;
      $previousId = $id;
    }
    $this->entityManager->flush();
    $this->entityManager->clear();

    // TARGET is currently a root-level (depth 1, height 0) facility. Reparenting
    // it under the level-8 leaf would push it to depth 9 — over the cap.
    $exception = $this->assertApplyConflict(
      self::ORGANIZATION_ID,
      $this->iri(self::TARGET_ID),
      ['parent' => $this->iri($chain[8])],
    );
    self::assertSame('Facility hierarchy depth cap of 8 levels exceeded.', $exception->getMessage());

    // Reparenting under the level-7 node lands TARGET at depth 8 — exactly the cap.
    $this->adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['parent' => $this->iri($chain[7])]);

    $record = $this->entityManager->find(FacilityRecord::class, self::TARGET_ID);
    self::assertInstanceOf(FacilityRecord::class, $record);
    self::assertInstanceOf(FacilityRecord::class, $record->parentFacility);
    self::assertSame($chain[7], $record->parentFacility->id);
  }

  #[Test]
  public function testApplyRejectsMetadataFailingTheOrganizationSchema(): void
  {
    $repository = self::createStub(FacilityMetadataFieldRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([
      FacilityMetadataField::reconstitute(
        id: FacilityMetadataFieldId::fromString('660e8400-e29b-41d4-a716-446655440020'),
        organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
        key: new FacilityMetadataFieldKey('surface-m2'),
        label: new FacilityMetadataFieldLabel('Surface (m²)'),
        fieldType: FacilityMetadataFieldType::NUMBER,
        required: false,
        createdAt: new DateTimeImmutable(self::CREATED_AT),
        updatedAt: new DateTimeImmutable(self::CREATED_AT),
      ),
    ]);
    $guard = self::createStub(FacilityArchivalGuardPort::class);
    $adapter = new FacilityInterventionResourceAdapter($this->entityManager, $guard, $this->facilityRepository, new FacilityMetadataSchemaGuard($repository));

    try {
      $adapter->apply(self::ORGANIZATION_ID, $this->iri(self::TARGET_ID), ['metadata' => ['surface-m2' => 'not-a-number']]);
      self::fail('Expected InterventionConflictException was not thrown.');
    } catch (InterventionConflictException $exception) {
      self::assertStringContainsString('surface-m2', $exception->getMessage());
    }
  }

  #[Test]
  public function testApplyRefusesRestoringUnderAnArchivedParent(): void
  {
    $exception = $this->assertApplyConflict(
      self::ORGANIZATION_ID,
      $this->iri(self::ARCHIVED_CHILD_ID),
      ['status' => 'active'],
    );

    self::assertSame('Cannot restore a facility while its parent is archived.', $exception->getMessage());
  }

  private function permissiveMetadataSchemaGuard(): FacilityMetadataSchemaGuard
  {
    $repository = self::createStub(FacilityMetadataFieldRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([]);

    return new FacilityMetadataSchemaGuard($repository);
  }

  /**
   * Asserts apply() rejects the given patch with an InterventionConflictException,
   * then clears the identity map so the unflushed mutation cannot leak into the
   * next assertion.
   *
   * @param array<string, mixed> $patch the proposed patch
   */
  private function assertApplyConflict(string $organizationId, string $resource, array $patch): InterventionConflictException
  {
    try {
      $this->adapter->apply($organizationId, $resource, $patch);
    } catch (InterventionConflictException $exception) {
      $this->entityManager->clear();

      return $exception;
    }

    self::fail('Expected InterventionConflictException was not thrown.');
  }

  private function iri(string $facilityId): string
  {
    return '/api/facilities/' . $facilityId;
  }

  private function chainFacilityId(int $level): string
  {
    return sprintf('660e8400-e29b-41d4-a716-44665544%04d', 2000 + $level);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Intervention Apply Test';
    $organization->slug = 'facility-intervention-apply-test';
    $organization->ownerUserId = self::OWNER_ID;
    $organization->createdByUserId = self::OWNER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable(self::CREATED_AT);
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createFacility(
    string $id,
    string $type,
    string $name,
    string $status,
    string $recordStatus,
    ?string $parentId = null,
  ): void {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);
    $facility->parentFacility = null === $parentId
      ? null
      : $this->entityManager->getReference(FacilityRecord::class, $parentId);
    $facility->type = $type;
    $facility->name = $name;
    $facility->code = null;
    $facility->status = $status;
    $facility->recordStatus = $recordStatus;
    $facility->revision = 1;
    $facility->address = null;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable(self::CREATED_AT);
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    // Break the self-referencing parent links before deleting so the DELETE is
    // FK-safe regardless of row order.
    $connection->executeStatement(
      'UPDATE facilities SET parent_facility_id = NULL WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM facilities WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
