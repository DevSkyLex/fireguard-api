<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\Inspection;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Inspection\EquipmentNamingAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test EquipmentNamingAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentNamingAdapter::class)]
final class EquipmentNamingAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '774e8400-e29b-41d4-a716-4466551e0001';

  private const string EQUIPMENT_WITH_SERIAL = '774e8400-e29b-41d4-a716-4466551e0010';

  private const string EQUIPMENT_WITHOUT_SERIAL = '774e8400-e29b-41d4-a716-4466551e0011';

  private EntityManagerInterface $entityManager;

  private EquipmentNamingAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new EquipmentNamingAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'Naming Adapter Test', 'naming-adapter-test');
    $this->createEquipment(self::EQUIPMENT_WITH_SERIAL, 'SN-9001');
    $this->createEquipment(self::EQUIPMENT_WITHOUT_SERIAL, null);
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindSerialNumbersByIdsReturnsOnlyEquipmentWithSerial(): void
  {
    $this->entityManager->clear();

    $serials = $this->adapter->findSerialNumbersByIds([
      self::EQUIPMENT_WITH_SERIAL,
      self::EQUIPMENT_WITHOUT_SERIAL,
      '774e8400-e29b-41d4-a716-4466551e00ff',
    ]);

    self::assertSame([self::EQUIPMENT_WITH_SERIAL => 'SN-9001'], $serials);
  }

  #[Test]
  public function testFindSerialNumbersByIdsReturnsEmptyForEmptyInput(): void
  {
    self::assertSame([], $this->adapter->findSerialNumbersByIds([]));
  }

  private function createOrganization(string $id, string $name, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '774e8400-e29b-41d4-a716-4466551e9000';
    $organization->createdByUserId = '774e8400-e29b-41d4-a716-4466551e9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createEquipment(string $id, ?string $serialNumber): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->serialNumber = $serialNumber;
    $equipment->status = 'operational';
    $equipment->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM equipment WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
