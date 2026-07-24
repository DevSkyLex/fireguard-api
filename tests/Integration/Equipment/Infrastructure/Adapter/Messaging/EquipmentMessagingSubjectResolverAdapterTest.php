<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\Messaging;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Messaging\EquipmentMessagingSubjectResolverAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test EquipmentMessagingSubjectResolverAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentMessagingSubjectResolverAdapter::class)]
final class EquipmentMessagingSubjectResolverAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '773e8400-e29b-41d4-a716-4466551d0001';

  private const string OTHER_ORGANIZATION_ID = '773e8400-e29b-41d4-a716-4466551d0002';

  private EntityManagerInterface $entityManager;

  private EquipmentMessagingSubjectResolverAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new EquipmentMessagingSubjectResolverAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'Messaging Subject Test', 'messaging-subject-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Messaging Subject Other', 'messaging-subject-other');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSupportsOnlyEquipmentSubjectType(): void
  {
    self::assertTrue($this->adapter->supports(MessagingSubjectType::EQUIPMENT));
    self::assertFalse($this->adapter->supports(MessagingSubjectType::FACILITY));
    self::assertFalse($this->adapter->supports(MessagingSubjectType::INTERVENTION));
  }

  #[Test]
  public function testResolveReturnsLabelWithSerialForPublishedEquipmentInOrganization(): void
  {
    $id = '773e8400-e29b-41d4-a716-4466551d0010';
    $this->createEquipment($id, self::ORGANIZATION_ID, 'SN-4711', 'published');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $resolution = $this->adapter->resolve(self::ORGANIZATION_ID, $id);

    self::assertTrue($resolution->exists);
    self::assertSame('fire_extinguisher (SN-4711)', $resolution->label);
    self::assertSame('organization.equipment.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveLabelFallsBackToTypeWhenSerialMissing(): void
  {
    $id = '773e8400-e29b-41d4-a716-4466551d0011';
    $this->createEquipment($id, self::ORGANIZATION_ID, null, 'published');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $resolution = $this->adapter->resolve(self::ORGANIZATION_ID, $id);

    self::assertTrue($resolution->exists);
    self::assertSame('fire_extinguisher', $resolution->label);
  }

  #[Test]
  public function testResolveDoesNotExistForOtherOrganizationOrDraftOrMissing(): void
  {
    $foreignId = '773e8400-e29b-41d4-a716-4466551d0020';
    $draftId = '773e8400-e29b-41d4-a716-4466551d0021';
    $this->createEquipment($foreignId, self::OTHER_ORGANIZATION_ID, 'SN-OTHER', 'published');
    $this->createEquipment($draftId, self::ORGANIZATION_ID, 'SN-DRAFT', 'draft');
    $this->entityManager->flush();
    $this->entityManager->clear();

    // Belongs to a different organization.
    $foreign = $this->adapter->resolve(self::ORGANIZATION_ID, $foreignId);
    self::assertFalse($foreign->exists);
    self::assertNull($foreign->label);

    // Draft scratchpad is invisible.
    self::assertFalse($this->adapter->resolve(self::ORGANIZATION_ID, $draftId)->exists);

    // Unknown id.
    self::assertFalse($this->adapter->resolve(self::ORGANIZATION_ID, '773e8400-e29b-41d4-a716-4466551d00ff')->exists);
  }

  private function createOrganization(string $id, string $name, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '773e8400-e29b-41d4-a716-4466551d9000';
    $organization->createdByUserId = '773e8400-e29b-41d4-a716-4466551d9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function createEquipment(
    string $id,
    string $organizationId,
    ?string $serialNumber,
    string $recordStatus,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->serialNumber = $serialNumber;
    $equipment->status = 'operational';
    $equipment->recordStatus = $recordStatus;
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
