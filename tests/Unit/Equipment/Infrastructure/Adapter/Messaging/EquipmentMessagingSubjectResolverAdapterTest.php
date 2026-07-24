<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Messaging;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Adapter\Messaging\EquipmentMessagingSubjectResolverAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentMessagingSubjectResolverAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentMessagingSubjectResolverAdapter::class)]
final class EquipmentMessagingSubjectResolverAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testSupportsOnlyEquipmentSubjectType(): void
  {
    $adapter = new EquipmentMessagingSubjectResolverAdapter($this->createStub(EntityManagerInterface::class));

    self::assertTrue($adapter->supports(MessagingSubjectType::EQUIPMENT));
    self::assertFalse($adapter->supports(MessagingSubjectType::INTERVENTION));
  }

  #[Test]
  public function testResolveBuildsALabelFromTypeAndSerialNumber(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('fire_extinguisher', 'SN-001', 'published', self::ORG_ID));

    $resolution = new EquipmentMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::EQUIPMENT_ID);

    self::assertTrue($resolution->exists);
    self::assertSame('fire_extinguisher (SN-001)', $resolution->label);
    self::assertSame('organization.equipment.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveFallsBackToTypeOnlyWithoutASerialNumber(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('smoke_detector', null, 'published', self::ORG_ID));

    $resolution = new EquipmentMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::EQUIPMENT_ID);

    self::assertSame('smoke_detector', $resolution->label);
  }

  #[Test]
  public function testResolveEnforcesOrganizationIsolation(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('fire_extinguisher', 'SN-001', 'published', '550e8400-e29b-41d4-a716-446655440099'));

    $resolution = new EquipmentMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::EQUIPMENT_ID);

    self::assertFalse($resolution->exists);
  }

  private function record(string $type, ?string $serialNumber, string $recordStatus, string $organizationId): EquipmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $record = new EquipmentRecord();
    $record->id = self::EQUIPMENT_ID;
    $record->organization = $organization;
    $record->recordStatus = $recordStatus;
    $record->type = $type;
    $record->serialNumber = $serialNumber;
    $record->status = 'in_stock';
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();

    return $record;
  }
}
