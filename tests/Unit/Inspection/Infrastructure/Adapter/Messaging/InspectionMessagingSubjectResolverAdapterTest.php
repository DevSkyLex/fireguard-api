<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Messaging;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Messaging\InspectionMessagingSubjectResolverAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function mb_strlen;

/**
 * Test InspectionMessagingSubjectResolverAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionMessagingSubjectResolverAdapter::class)]
final class InspectionMessagingSubjectResolverAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testSupportsOnlyNonConformitySubjectType(): void
  {
    $adapter = new InspectionMessagingSubjectResolverAdapter($this->createStub(EntityManagerInterface::class));

    self::assertTrue($adapter->supports(MessagingSubjectType::NON_CONFORMITY));
    self::assertFalse($adapter->supports(MessagingSubjectType::FACILITY));
  }

  #[Test]
  public function testResolveTruncatesALongDescriptionForTheLabel(): void
  {
    $description = 'A very long non-conformity description that should be truncated to a compact label because it exceeds the eighty character budget by a fair margin.';

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record($description, self::ORG_ID));

    $resolution = new InspectionMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::NON_CONFORMITY_ID);

    self::assertTrue($resolution->exists);
    self::assertLessThanOrEqual(80, mb_strlen((string) $resolution->label));
    self::assertSame('organization.inspection.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveEnforcesOrganizationIsolationThroughTheParentInspection(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('Broken extinguisher seal', '550e8400-e29b-41d4-a716-446655440099'));

    $resolution = new InspectionMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::NON_CONFORMITY_ID);

    self::assertFalse($resolution->exists);
  }

  private function record(string $description, string $organizationId): NonConformityRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $inspection = new InspectionRecord();
    $inspection->id = 'inspection-1';
    $inspection->organization = $organization;

    $record = new NonConformityRecord();
    $record->id = self::NON_CONFORMITY_ID;
    $record->inspection = $inspection;
    $record->description = $description;
    $record->severity = 'high';
    $record->status = 'open';
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();

    return $record;
  }
}
