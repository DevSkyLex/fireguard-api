<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Exception\{CanonicalInspectionConflictException, CanonicalInspectionValidationException, InspectionRevisionMismatchException};
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\{
  CanonicalInspectionPatch,
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionRecordStatus,
  InspectionResult,
  InspectionStatus
};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CanonicalInspectionTest.
 *
 * The rules that used to live inside `CanonicalInspectionMutationProcessor`,
 * asserted where they now are — no container, no mocks, no database.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalInspection::class)]
final class CanonicalInspectionTest extends TestCase
{
  // #region Constants
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440025';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';
  // #endregion

  // #region Tests — patch
  /**
   * Method testAPatchBumpsTheRevisionAndReportsAPublishedStatusChange.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchBumpsTheRevisionAndReportsAPublishedStatusChange(): void
  {
    $inspection = $this->inspection();

    $previous = $inspection->applyPatch(new CanonicalInspectionPatch(hasStatus: true, status: 'closed'));

    self::assertSame('submitted', $previous);
    self::assertSame(InspectionStatus::CLOSED, $inspection->status());
    self::assertSame(4, $inspection->revision());
  }

  /**
   * Method testAPatchWithoutAStatusChangeReportsNothingToAudit.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchWithoutAStatusChangeReportsNothingToAudit(): void
  {
    $inspection = $this->inspection();

    self::assertNull($inspection->applyPatch(new CanonicalInspectionPatch(hasNotes: true, notes: 'Updated')));
    self::assertSame('Updated', $inspection->notes());
    self::assertSame(4, $inspection->revision());
  }

  /**
   * Method testAnExplicitNullErasesANullableFieldWhileAnAbsentKeyDoesNot.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnExplicitNullErasesANullableFieldWhileAnAbsentKeyDoesNot(): void
  {
    $inspection = $this->inspection(notes: 'Previous notes', signature: 'Previous signature');

    $inspection->applyPatch(new CanonicalInspectionPatch(
      hasNotes: true,
      notes: 'Updated notes',
      hasSignature: true,
      signature: null,
    ));

    self::assertSame('Updated notes', $inspection->notes());
    self::assertNull($inspection->signature());

    $inspection->applyPatch(new CanonicalInspectionPatch());

    self::assertSame('Updated notes', $inspection->notes());
  }

  /**
   * Method testANullNonNullableFieldIsRejected.
   *
   * `result` is validated before `status`, so a patch sending both as null is
   * told about `result` — the wording the processor emitted.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullNonNullableFieldIsRejected(): void
  {
    $this->expectException(CanonicalInspectionValidationException::class);
    $this->expectExceptionMessage('Inspection result cannot be null.');

    $this->inspection()->applyPatch(new CanonicalInspectionPatch(
      hasResult: true,
      result: null,
      hasStatus: true,
      status: null,
    ));
  }

  /**
   * Method testANullStatusAloneIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullStatusAloneIsRejected(): void
  {
    $this->expectException(CanonicalInspectionValidationException::class);
    $this->expectExceptionMessage('Inspection status cannot be null.');

    $this->inspection()->applyPatch(new CanonicalInspectionPatch(hasStatus: true, status: null));
  }

  /**
   * Method testATerminalStateRefusesEveryPatch.
   *
   * @param string $status the terminal status
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('terminalStatuses')]
  public function testATerminalStateRefusesEveryPatch(string $status): void
  {
    $this->expectException(CanonicalInspectionConflictException::class);
    $this->expectExceptionMessage('Closed or cancelled inspections are immutable.');

    $this->inspection(status: InspectionStatus::from($status))
      ->applyPatch(new CanonicalInspectionPatch(hasNotes: true, notes: 'Anything'));
  }

  /**
   * Method testAnIllegalPublishedTransitionIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnIllegalPublishedTransitionIsRejected(): void
  {
    $this->expectException(CanonicalInspectionValidationException::class);
    $this->expectExceptionMessage('Illegal inspection status transition from draft to closed.');

    $this->inspection(status: InspectionStatus::DRAFT)
      ->applyPatch(new CanonicalInspectionPatch(hasStatus: true, status: 'closed'));
  }

  /**
   * Method testAScratchpadSkipsTheLifecycleAndIsNeverAudited.
   *
   * A draft record is an intervention preparation, not a compliance record:
   * an otherwise-illegal jump is allowed and reports nothing to audit.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadSkipsTheLifecycleAndIsNeverAudited(): void
  {
    $inspection = $this->inspection(
      recordStatus: InspectionRecordStatus::DRAFT,
      status: InspectionStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
    );

    self::assertNull($inspection->applyPatch(new CanonicalInspectionPatch(hasStatus: true, status: 'closed')));
    self::assertSame(InspectionStatus::CLOSED, $inspection->status());
    self::assertTrue($inspection->isScratchpad());
  }

  /**
   * Method testAnUnsupportedEnumValueIsRejected.
   *
   * Unreachable over HTTP — `#[Assert\Choice]` guards both fields — but a
   * direct dispatch must not persist the string.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnsupportedEnumValueIsRejected(): void
  {
    $this->expectException(CanonicalInspectionValidationException::class);
    $this->expectExceptionMessage('Inspection status "nonsense" is not a supported value.');

    $this->inspection()->applyPatch(new CanonicalInspectionPatch(hasStatus: true, status: 'nonsense'));
  }
  // #endregion

  // #region Tests — cancel and revision
  /**
   * Method testCancellingAPublishedInspectionReportsItsPreviousStatus.
   *
   * @return void no return value
   */
  #[Test]
  public function testCancellingAPublishedInspectionReportsItsPreviousStatus(): void
  {
    $inspection = $this->inspection();

    self::assertSame('submitted', $inspection->cancel());
    self::assertSame(InspectionStatus::CANCELLED, $inspection->status());
    self::assertSame(4, $inspection->revision());
  }

  /**
   * Method testCancellingAnAlreadyCancelledInspectionIsAnIdempotentNoOp.
   *
   * No revision bump — a repeat DELETE must not invalidate the caller's
   * `If-Match`, matching the facility and equipment canonical surfaces.
   *
   * @return void no return value
   */
  #[Test]
  public function testCancellingAnAlreadyCancelledInspectionIsAnIdempotentNoOp(): void
  {
    $inspection = $this->inspection(status: InspectionStatus::CANCELLED);

    self::assertNull($inspection->cancel());
    self::assertSame(InspectionStatus::CANCELLED, $inspection->status());
    self::assertSame(3, $inspection->revision());
  }

  /**
   * Method testAClosedInspectionCannotBeCancelled.
   *
   * @return void no return value
   */
  #[Test]
  public function testAClosedInspectionCannotBeCancelled(): void
  {
    $this->expectException(CanonicalInspectionConflictException::class);
    $this->expectExceptionMessage('Closed inspections are immutable.');

    $this->inspection(status: InspectionStatus::CLOSED)->cancel();
  }

  /**
   * Method testAStaleRevisionIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRejected(): void
  {
    $this->expectException(InspectionRevisionMismatchException::class);
    $this->expectExceptionMessage('The resource revision is stale.');

    $this->inspection()->assertRevisionMatches(2);
  }
  // #endregion

  // #region Providers
  /**
   * Method terminalStatuses.
   *
   * @return iterable<string, array{string}> the terminal statuses
   */
  public static function terminalStatuses(): iterable
  {
    yield 'closed' => ['closed'];

    yield 'cancelled' => ['cancelled'];
  }
  // #endregion

  // #region Helpers
  /**
   * Method inspection.
   *
   * @param InspectionRecordStatus $recordStatus the record status
   * @param InspectionStatus $status the lifecycle status
   * @param ?string $interventionId the preparing intervention
   * @param ?string $notes the stored notes
   * @param ?string $signature the stored signature
   *
   * @return CanonicalInspection a published, submitted inspection at revision 3
   */
  private function inspection(
    InspectionRecordStatus $recordStatus = InspectionRecordStatus::PUBLISHED,
    InspectionStatus $status = InspectionStatus::SUBMITTED,
    ?string $interventionId = null,
    ?string $notes = null,
    ?string $signature = null,
  ): CanonicalInspection {
    return CanonicalInspection::reconstitute(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      status: $status,
      result: InspectionResult::PASS,
      notes: $notes,
      signature: $signature,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
