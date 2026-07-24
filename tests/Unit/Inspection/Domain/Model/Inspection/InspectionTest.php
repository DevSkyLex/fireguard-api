<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Exception\{
  InspectionAlreadyCancelledException,
  InspectionAlreadyClosedException,
  InspectionAlreadySubmittedException,
  InspectionNotSubmittedException
};
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus,
  Inspector
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;
use function usleep;

/**
 * Test InspectionTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Inspection::class)]
final class InspectionTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655440005';

  private const string NEW_EQUIP_ID = '550e8400-e29b-41d4-a716-446655440006';
  // #endregion

  // #region Methods
  #[Test]
  public function testCreateReturnsDraftStatus(): void
  {
    $inspection = $this->makeInspection();

    self::assertSame(InspectionStatus::DRAFT, $inspection->status());
  }

  #[Test]
  public function testCreateStoresAllProperties(): void
  {
    $performedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::FAIL,
      performedAt: $performedAt,
      notes: '  Some notes  ',
    );

    self::assertSame(self::INSP_ID, (string) $inspection->id());
    self::assertSame(self::ORG_ID, (string) $inspection->organizationId());
    self::assertSame(self::EQUIP_ID, (string) $inspection->equipmentId());
    self::assertSame(InspectionResult::FAIL, $inspection->result());
    self::assertSame($performedAt, $inspection->performedAt());
    self::assertSame('Some notes', $inspection->notes());
  }

  #[Test]
  public function testCreateNormalizesEmptyNotesToNull(): void
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable(),
      notes: '   ',
    );

    self::assertNull($inspection->notes());
  }

  #[Test]
  public function testCreateThrowsWhenNotesTooLong(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('notes must be at most 5000 characters');

    Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable(),
      notes: str_repeat('x', 5001),
    );
  }

  #[Test]
  public function testSubmitTransitionsDraftToSubmitted(): void
  {
    $inspection = $this->makeInspection();

    $inspection->submit();

    self::assertSame(InspectionStatus::SUBMITTED, $inspection->status());
  }

  #[Test]
  public function testSubmitThrowsWhenAlreadySubmitted(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();

    $this->expectException(InspectionAlreadySubmittedException::class);

    $inspection->submit();
  }

  #[Test]
  public function testSubmitThrowsWhenAlreadyClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();
    $inspection->close();

    $this->expectException(InspectionAlreadyClosedException::class);

    $inspection->submit();
  }

  #[Test]
  public function testCloseTransitionsSubmittedToClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();

    $inspection->close();

    self::assertSame(InspectionStatus::CLOSED, $inspection->status());
  }

  #[Test]
  public function testCloseThrowsWhenAlreadyClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();
    $inspection->close();

    $this->expectException(InspectionAlreadyClosedException::class);

    $inspection->close();
  }

  #[Test]
  public function testCloseThrowsWhenDraftNotYetSubmitted(): void
  {
    $inspection = $this->makeInspection();

    $this->expectException(InspectionNotSubmittedException::class);

    $inspection->close();
  }

  #[Test]
  public function testUpdatedAtChangesAfterSubmit(): void
  {
    $inspection = $this->makeInspection();
    $before = $inspection->updatedAt();

    // Ensure at least 1 microsecond passes
    usleep(1000);
    $inspection->submit();

    self::assertGreaterThanOrEqual($before, $inspection->updatedAt());
  }

  #[Test]
  public function testCreateInitializesTimestampsAndOptionalGetters(): void
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );

    self::assertNull($inspection->facilityId());
    self::assertNull($inspection->checklistId());
    self::assertNull($inspection->signature());
    self::assertNull($inspection->notes());
    self::assertSame('John Doe', $inspection->inspector()->name);
    self::assertEquals($inspection->createdAt(), $inspection->updatedAt());
  }

  #[Test]
  public function testReconstituteRestoresPersistedState(): void
  {
    $performedAt = new DateTimeImmutable('2026-01-10T08:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-01-11T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-12T10:00:00+00:00');

    $inspection = Inspection::reconstitute(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'Jane Roe'),
      result: InspectionResult::PARTIAL,
      status: InspectionStatus::SUBMITTED,
      performedAt: $performedAt,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      facilityId: InspectionFacilityId::fromString(self::FACILITY_ID),
      checklistId: InspectionChecklistId::fromString(self::CHECKLIST_ID),
      notes: '  raw notes kept verbatim  ',
      signature: 'signature-blob',
    );

    self::assertSame(InspectionStatus::SUBMITTED, $inspection->status());
    self::assertSame(InspectionResult::PARTIAL, $inspection->result());
    self::assertSame($performedAt, $inspection->performedAt());
    self::assertSame($createdAt, $inspection->createdAt());
    self::assertSame($updatedAt, $inspection->updatedAt());
    self::assertSame(self::FACILITY_ID, (string) $inspection->facilityId());
    self::assertSame(self::CHECKLIST_ID, (string) $inspection->checklistId());
    self::assertSame('  raw notes kept verbatim  ', $inspection->notes());
    self::assertSame('signature-blob', $inspection->signature());
    self::assertSame('Jane Roe', $inspection->inspector()->name);
  }

  #[Test]
  public function testCancelTransitionsDraftToCancelled(): void
  {
    $inspection = $this->makeInspection();

    $inspection->cancel();

    self::assertSame(InspectionStatus::CANCELLED, $inspection->status());
  }

  #[Test]
  public function testCancelTransitionsSubmittedToCancelled(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();

    $inspection->cancel();

    self::assertSame(InspectionStatus::CANCELLED, $inspection->status());
  }

  #[Test]
  public function testCancelThrowsWhenAlreadyClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();
    $inspection->close();

    $this->expectException(InspectionAlreadyClosedException::class);

    $inspection->cancel();
  }

  #[Test]
  public function testCancelThrowsWhenAlreadyCancelled(): void
  {
    $inspection = $this->makeInspection();
    $inspection->cancel();

    $this->expectException(InspectionAlreadyCancelledException::class);

    $inspection->cancel();
  }

  #[Test]
  public function testSubmitThrowsWhenCancelled(): void
  {
    $inspection = $this->makeInspection();
    $inspection->cancel();

    $this->expectException(InspectionAlreadySubmittedException::class);

    $inspection->submit();
  }

  #[Test]
  public function testCloseThrowsWhenCancelled(): void
  {
    $inspection = $this->makeInspection();
    $inspection->cancel();

    $this->expectException(InspectionNotSubmittedException::class);

    $inspection->close();
  }

  #[Test]
  public function testEditUpdatesAllProvidedFields(): void
  {
    $inspection = $this->makeInspection();
    $newPerformedAt = new DateTimeImmutable('2026-02-20T12:00:00+00:00');

    $inspection->edit(
      equipmentId: InspectionEquipmentId::fromString(self::NEW_EQUIP_ID),
      facilityId: InspectionFacilityId::fromString(self::FACILITY_ID),
      checklistId: InspectionChecklistId::fromString(self::CHECKLIST_ID),
      result: InspectionResult::FAIL,
      performedAt: $newPerformedAt,
      notes: '  Edited notes  ',
      signature: 'new-signature',
      hasEquipmentId: true,
      hasFacilityId: true,
      hasChecklistId: true,
      hasResult: true,
      hasPerformedAt: true,
      hasNotes: true,
      hasSignature: true,
    );

    self::assertSame(self::NEW_EQUIP_ID, (string) $inspection->equipmentId());
    self::assertSame(self::FACILITY_ID, (string) $inspection->facilityId());
    self::assertSame(self::CHECKLIST_ID, (string) $inspection->checklistId());
    self::assertSame(InspectionResult::FAIL, $inspection->result());
    self::assertSame($newPerformedAt, $inspection->performedAt());
    self::assertSame('Edited notes', $inspection->notes());
    self::assertSame('new-signature', $inspection->signature());
  }

  #[Test]
  public function testEditLeavesFieldsUntouchedWhenFlagsFalse(): void
  {
    $inspection = $this->makeInspection();

    $inspection->edit();

    self::assertSame(self::EQUIP_ID, (string) $inspection->equipmentId());
    self::assertNull($inspection->facilityId());
    self::assertNull($inspection->checklistId());
    self::assertSame(InspectionResult::PASS, $inspection->result());
    self::assertNull($inspection->notes());
    self::assertNull($inspection->signature());
  }

  #[Test]
  public function testEditClearsOptionalsButKeepsRequiredWhenFlaggedWithNull(): void
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
      facilityId: InspectionFacilityId::fromString(self::FACILITY_ID),
      checklistId: InspectionChecklistId::fromString(self::CHECKLIST_ID),
      notes: 'Original notes',
      signature: 'original-signature',
    );

    $inspection->edit(
      hasEquipmentId: true,
      hasFacilityId: true,
      hasChecklistId: true,
      hasResult: true,
      hasPerformedAt: true,
      hasNotes: true,
      hasSignature: true,
    );

    // Optional fields are cleared when flagged with null.
    self::assertNull($inspection->facilityId());
    self::assertNull($inspection->checklistId());
    self::assertNull($inspection->notes());
    self::assertNull($inspection->signature());
    // Required fields stay unchanged because their null value is ignored.
    self::assertSame(self::EQUIP_ID, (string) $inspection->equipmentId());
    self::assertSame(InspectionResult::PASS, $inspection->result());
  }

  #[Test]
  public function testEditThrowsWhenClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();
    $inspection->close();

    $this->expectException(InspectionAlreadyClosedException::class);

    $inspection->edit(result: InspectionResult::FAIL, hasResult: true);
  }

  #[Test]
  public function testEditThrowsWhenNotDraft(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();

    $this->expectException(InspectionAlreadySubmittedException::class);

    $inspection->edit(result: InspectionResult::FAIL, hasResult: true);
  }

  // #region Helpers
  private function makeInspection(): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }
  // #endregion
}
