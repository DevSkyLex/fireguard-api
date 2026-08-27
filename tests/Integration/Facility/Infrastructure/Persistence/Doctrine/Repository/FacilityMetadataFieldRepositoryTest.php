<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId,
  FacilityType
};
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityMetadataFieldRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityMetadataFieldRepositoryTest.
 *
 * Exercises save/find/list/count/delete against a real database, and
 * confirms the `(organization_id, field_key)` unique constraint and the
 * organization `ON DELETE CASCADE` foreign key.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityMetadataFieldRepository::class)]
final class FacilityMetadataFieldRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655470001';

  private const string FIELD_ID = '660e8400-e29b-41d4-a716-446655470002';

  private EntityManagerInterface $entityManager;

  private FacilityMetadataFieldRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new FacilityMetadataFieldRepository($this->entityManager);

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Metadata Field Repository Test';
    $organization->slug = 'facility-metadata-field-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655479000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655479000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTrips(): void
  {
    $field = $this->newField();

    $this->repository->save($field);
    $this->entityManager->clear();

    $found = $this->repository->findById(FacilityMetadataFieldId::fromString(self::FIELD_ID));

    self::assertInstanceOf(FacilityMetadataField::class, $found);
    self::assertSame('surface-m2', (string) $found->key());
    self::assertSame('m²', $found->unit());
    self::assertSame(FacilityMetadataFieldType::NUMBER, $found->fieldType());
  }

  #[Test]
  public function testSaveUpdatesAnExistingDefinitionInPlace(): void
  {
    $field = $this->newField();
    $this->repository->save($field);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(FacilityMetadataFieldId::fromString(self::FIELD_ID));
    self::assertInstanceOf(FacilityMetadataField::class, $reloaded);
    $reloaded->rename(new FacilityMetadataFieldLabel('Renamed'));

    $this->repository->save($reloaded);
    $this->entityManager->clear();

    $updated = $this->repository->findById(FacilityMetadataFieldId::fromString(self::FIELD_ID));
    self::assertInstanceOf(FacilityMetadataField::class, $updated);
    self::assertSame('Renamed', (string) $updated->label());
  }

  #[Test]
  public function testFindByOrganizationIdAndKeyFindsAMatch(): void
  {
    $this->repository->save($this->newField());
    $this->entityManager->clear();

    $found = $this->repository->findByOrganizationIdAndKey(
      FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      'surface-m2',
    );

    self::assertInstanceOf(FacilityMetadataField::class, $found);
  }

  #[Test]
  public function testFindByOrganizationIdAndKeyReturnsNullWhenAbsent(): void
  {
    $found = $this->repository->findByOrganizationIdAndKey(
      FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      'unknown-key',
    );

    self::assertNull($found);
  }

  #[Test]
  public function testUniqueConstraintRejectsADuplicateKeyForTheSameOrganization(): void
  {
    $this->repository->save($this->newField());
    $this->entityManager->clear();

    $duplicate = FacilityMetadataField::create(
      id: FacilityMetadataFieldId::fromString('660e8400-e29b-41d4-a716-446655470003'),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      key: new FacilityMetadataFieldKey('surface-m2'),
      label: new FacilityMetadataFieldLabel('Duplicate'),
      fieldType: FacilityMetadataFieldType::TEXT,
    );

    $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

    $this->repository->save($duplicate);
  }

  #[Test]
  public function testFindByOrganizationIdListsAllDefinitionsOrderedByLabel(): void
  {
    $this->repository->save(FacilityMetadataField::create(
      id: FacilityMetadataFieldId::fromString('660e8400-e29b-41d4-a716-446655470004'),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      key: new FacilityMetadataFieldKey('zed-field'),
      label: new FacilityMetadataFieldLabel('Zed field'),
      fieldType: FacilityMetadataFieldType::TEXT,
    ));
    $this->repository->save($this->newField());
    $this->entityManager->clear();

    $fields = $this->repository->findByOrganizationId(FacilityOrganizationId::fromString(self::ORGANIZATION_ID));

    self::assertCount(2, $fields);
    self::assertSame('Surface (m²)', (string) $fields[0]->label());
    self::assertSame('Zed field', (string) $fields[1]->label());
  }

  #[Test]
  public function testCountByOrganizationIdCountsOnlyThatOrganization(): void
  {
    $this->repository->save($this->newField());
    $this->entityManager->clear();

    self::assertSame(1, $this->repository->countByOrganizationId(FacilityOrganizationId::fromString(self::ORGANIZATION_ID)));
  }

  #[Test]
  public function testDeleteRemovesTheDefinitionButLeavesFacilityMetadataUntouched(): void
  {
    $this->repository->save($this->newField());
    $this->entityManager->clear();

    $this->repository->delete(FacilityMetadataFieldId::fromString(self::FIELD_ID));

    self::assertNull($this->repository->findById(FacilityMetadataFieldId::fromString(self::FIELD_ID)));
  }

  #[Test]
  public function testDeleteIsIdempotentForAnUnknownId(): void
  {
    $this->repository->delete(FacilityMetadataFieldId::fromString('660e8400-e29b-41d4-a716-446655470099'));

    $this->addToAssertionCount(1);
  }

  private function newField(): FacilityMetadataField
  {
    return FacilityMetadataField::create(
      id: FacilityMetadataFieldId::fromString(self::FIELD_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      key: new FacilityMetadataFieldKey('surface-m2'),
      label: new FacilityMetadataFieldLabel('Surface (m²)'),
      fieldType: FacilityMetadataFieldType::NUMBER,
      required: true,
      unit: 'm²',
      facilityType: FacilityType::BUILDING,
    );
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM facility_metadata_fields WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
