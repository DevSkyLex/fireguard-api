<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\Facility;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Facility\EquipmentPlanPositionAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test EquipmentPlanPositionAdapterTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentPlanPositionAdapter::class)]
final class EquipmentPlanPositionAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '771e8400-e29b-41d4-a716-4466552b0001';

  private const string OTHER_ORGANIZATION_ID = '771e8400-e29b-41d4-a716-4466552b0002';

  private const string ATTACHMENT_ID = '771e8400-e29b-41d4-a716-4466552b00f1';

  private const string OTHER_ATTACHMENT_ID = '771e8400-e29b-41d4-a716-4466552b00f2';

  private EntityManagerInterface $entityManager;

  private EquipmentPlanPositionAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new EquipmentPlanPositionAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'Plan Position Test', 'plan-position-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Plan Position Other', 'plan-position-other');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindEquipmentPlacedOnPlanReturnsOnlyMatchingPublishedEquipmentInTheOrganization(): void
  {
    $this->createEquipment(
      '771e8400-e29b-41d4-a716-4466552b0010',
      self::ORGANIZATION_ID,
      'operational',
      'published',
      ['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.42, 'y' => 0.17],
      'fire_extinguisher',
      'SN-1',
    );
    // Different attachment: excluded.
    $this->createEquipment(
      '771e8400-e29b-41d4-a716-4466552b0011',
      self::ORGANIZATION_ID,
      'operational',
      'published',
      ['attachmentId' => self::OTHER_ATTACHMENT_ID, 'x' => 0.1, 'y' => 0.1],
      'smoke_detector',
      null,
    );
    // Draft: excluded.
    $this->createEquipment(
      '771e8400-e29b-41d4-a716-4466552b0012',
      self::ORGANIZATION_ID,
      'operational',
      'draft',
      ['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.2, 'y' => 0.2],
      'smoke_detector',
      null,
    );
    // No plan position at all: excluded.
    $this->createEquipment(
      '771e8400-e29b-41d4-a716-4466552b0013',
      self::ORGANIZATION_ID,
      'in_stock',
      'published',
      null,
      'hydrant',
      null,
    );
    // Different organization, same attachment id: excluded by org scoping.
    $this->createEquipment(
      '771e8400-e29b-41d4-a716-4466552b0014',
      self::OTHER_ORGANIZATION_ID,
      'operational',
      'published',
      ['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.3, 'y' => 0.3],
      'fire_extinguisher',
      null,
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $items = $this->adapter->findEquipmentPlacedOnPlan(self::ORGANIZATION_ID, self::ATTACHMENT_ID);

    self::assertCount(1, $items);
    self::assertSame('771e8400-e29b-41d4-a716-4466552b0010', $items[0]['equipmentId']);
    self::assertSame('fire_extinguisher (SN-1)', $items[0]['name']);
    self::assertSame('operational', $items[0]['status']);
    self::assertSame(0.42, $items[0]['x']);
    self::assertSame(0.17, $items[0]['y']);
  }

  #[Test]
  public function testFindEquipmentPlacedOnPlanReturnsEmptyWhenNothingMatches(): void
  {
    self::assertSame([], $this->adapter->findEquipmentPlacedOnPlan(self::ORGANIZATION_ID, self::ATTACHMENT_ID));
  }

  #[Test]
  public function testFindEquipmentPlacedOnPlanFallsBackToTypeWhenNoSerialNumber(): void
  {
    $this->createEquipment(
      '771e8400-e29b-41d4-a716-4466552b0020',
      self::ORGANIZATION_ID,
      'operational',
      'published',
      ['attachmentId' => self::ATTACHMENT_ID, 'x' => 0.5, 'y' => 0.5],
      'hydrant',
      null,
    );
    $this->entityManager->flush();
    $this->entityManager->clear();

    $items = $this->adapter->findEquipmentPlacedOnPlan(self::ORGANIZATION_ID, self::ATTACHMENT_ID);

    self::assertCount(1, $items);
    self::assertSame('hydrant', $items[0]['name']);
  }

  private function createOrganization(string $id, string $name, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '771e8400-e29b-41d4-a716-4466552b9000';
    $organization->createdByUserId = '771e8400-e29b-41d4-a716-4466552b9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  /**
   * @param ?array{attachmentId: string, x: float, y: float} $planPosition
   */
  private function createEquipment(
    string $id,
    string $organizationId,
    string $status,
    string $recordStatus,
    ?array $planPosition,
    string $type,
    ?string $serialNumber,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->type = $type;
    $equipment->serialNumber = $serialNumber;
    $equipment->status = $status;
    $equipment->recordStatus = $recordStatus;
    $equipment->planPosition = $planPosition;
    $equipment->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM equipment WHERE organization_id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
