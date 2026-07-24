<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\Messaging;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Messaging\FacilityMessagingSubjectResolverAdapter;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityMessagingSubjectResolverAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityMessagingSubjectResolverAdapter::class)]
final class FacilityMessagingSubjectResolverAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655440099';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testSupportsOnlyFacilitySubjectType(): void
  {
    $adapter = new FacilityMessagingSubjectResolverAdapter($this->createStub(EntityManagerInterface::class));

    self::assertTrue($adapter->supports(MessagingSubjectType::FACILITY));
    self::assertFalse($adapter->supports(MessagingSubjectType::EQUIPMENT));
  }

  #[Test]
  public function testResolveReturnsTheFacilityNameAsLabelWhenPublishedAndOrgMatches(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('Site nord', 'published', self::ORG_ID));

    $resolution = new FacilityMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::FACILITY_ID);

    self::assertTrue($resolution->exists);
    self::assertSame('Site nord', $resolution->label);
    self::assertSame('organization.facilities.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveDoesNotExistForADraftRecord(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('Site nord', 'draft', self::ORG_ID));

    $resolution = new FacilityMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::FACILITY_ID);

    self::assertFalse($resolution->exists);
    self::assertNull($resolution->label);
  }

  #[Test]
  public function testResolveEnforcesOrganizationIsolation(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('Site nord', 'published', self::OTHER_ORG_ID));

    $resolution = new FacilityMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::FACILITY_ID);

    self::assertFalse($resolution->exists);
  }

  #[Test]
  public function testResolveDoesNotExistWhenRecordIsMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $resolution = new FacilityMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::FACILITY_ID);

    self::assertFalse($resolution->exists);
  }

  private function record(string $name, string $recordStatus, string $organizationId): FacilityRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $record = new FacilityRecord();
    $record->id = self::FACILITY_ID;
    $record->organization = $organization;
    $record->recordStatus = $recordStatus;
    $record->type = 'site';
    $record->name = $name;
    $record->status = 'active';
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();

    return $record;
  }
}
