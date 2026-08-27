<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Model\Equipment;

use DateTimeImmutable;
use Equipment\Domain\Exception\{CanonicalEquipmentValidationException, EquipmentRevisionMismatchException};
use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\{
  CanonicalEquipmentPatch,
  EquipmentId,
  EquipmentOrganizationId,
  EquipmentRecordStatus,
  EquipmentStatus
};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CanonicalEquipmentTest.
 *
 * The rules that used to live inside `CanonicalEquipmentMutationProcessor`,
 * asserted where they now are — no container, no mocks, no database.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalEquipment::class)]
#[CoversClass(CanonicalEquipmentPatch::class)]
final class CanonicalEquipmentTest extends TestCase
{
  // #region Constants
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440031';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440032';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440035';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440034';
  // #endregion

  // #region Tests — patch
  /**
   * Method testCommissioningStampsTheFirstCommissioningDateAndBumpsTheRevision.
   *
   * @return void no return value
   */
  #[Test]
  public function testCommissioningStampsTheFirstCommissioningDateAndBumpsTheRevision(): void
  {
    $equipment = $this->equipment(facilityId: self::FACILITY_ID);

    $previous = $equipment->applyPatch(new CanonicalEquipmentPatch(hasStatus: true, status: 'operational'));

    self::assertSame('in_stock', $previous);
    self::assertSame(EquipmentStatus::OPERATIONAL, $equipment->status());
    self::assertNotNull($equipment->commissionedAt());
    self::assertSame(4, $equipment->revision());
  }

  /**
   * Method testTheFirstCommissioningDateSurvivesARecommission.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheFirstCommissioningDateSurvivesARecommission(): void
  {
    $stamped = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
    $equipment = $this->equipment(
      status: EquipmentStatus::UNDER_MAINTENANCE,
      facilityId: self::FACILITY_ID,
      commissionedAt: $stamped,
    );

    $equipment->applyPatch(new CanonicalEquipmentPatch(hasStatus: true, status: 'operational'));

    self::assertSame($stamped, $equipment->commissionedAt());
  }

  /**
   * Method testAPatchWithoutAStatusChangeReportsNothingToAudit.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchWithoutAStatusChangeReportsNothingToAudit(): void
  {
    $equipment = $this->equipment(brand: 'Old', model: 'M1');

    self::assertNull($equipment->applyPatch(new CanonicalEquipmentPatch(hasBrand: true, brand: 'New')));
    self::assertSame('New', $equipment->brand());
    self::assertSame('M1', $equipment->model());
    self::assertSame(4, $equipment->revision());
  }

  /**
   * Method testAnExplicitNullErasesANullableFieldWhileAnAbsentKeyDoesNot.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnExplicitNullErasesANullableFieldWhileAnAbsentKeyDoesNot(): void
  {
    $equipment = $this->equipment(brand: 'Kidde', model: 'X1');

    $equipment->applyPatch(new CanonicalEquipmentPatch(hasBrand: true, brand: null));

    self::assertNull($equipment->brand());
    self::assertSame('X1', $equipment->model());
  }

  /**
   * Method testANullNonNullableFieldIsRejectedTypeFirst.
   *
   * `type` is validated before `status`, so a patch sending both as null is
   * told about `type` — the wording the processor emitted.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullNonNullableFieldIsRejectedTypeFirst(): void
  {
    $this->expectException(CanonicalEquipmentValidationException::class);
    $this->expectExceptionMessage('Equipment type cannot be null.');

    new CanonicalEquipmentPatch(hasType: true, type: null, hasStatus: true, status: null)
      ->assertNonNullableFieldsArePresent();
  }

  /**
   * Method testANullStatusAloneIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullStatusAloneIsRejected(): void
  {
    $this->expectException(CanonicalEquipmentValidationException::class);
    $this->expectExceptionMessage('Equipment status cannot be null.');

    new CanonicalEquipmentPatch(hasStatus: true, status: null)->assertNonNullableFieldsArePresent();
  }

  /**
   * Method testAnIllegalPublishedTransitionIsRejected.
   *
   * Decommissioned is terminal — an asset is never revived.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnIllegalPublishedTransitionIsRejected(): void
  {
    $this->expectException(CanonicalEquipmentValidationException::class);
    $this->expectExceptionMessage('Illegal equipment status transition from decommissioned to operational.');

    $this->equipment(status: EquipmentStatus::DECOMMISSIONED, facilityId: self::FACILITY_ID)
      ->applyPatch(new CanonicalEquipmentPatch(hasStatus: true, status: 'operational'));
  }

  /**
   * Method testInServiceEquipmentCannotLoseItsFacility.
   *
   * Checked on EVERY patch, not only on a status change: a request that
   * merely clears the facility of an operational asset is exactly the one
   * this rejects.
   *
   * @param string $status the in-service status
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('inServiceStatuses')]
  public function testInServiceEquipmentCannotLoseItsFacility(string $status): void
  {
    $this->expectException(CanonicalEquipmentValidationException::class);
    $this->expectExceptionMessage('In-service equipment must be assigned to a facility.');

    $this->equipment(status: EquipmentStatus::from($status), facilityId: self::FACILITY_ID)
      ->applyPatch(new CanonicalEquipmentPatch(hasFacility: true, facilityId: null));
  }

  /**
   * Method testAScratchpadSkipsTheLifecycleAndIsNeverAudited.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadSkipsTheLifecycleAndIsNeverAudited(): void
  {
    $equipment = $this->equipment(
      recordStatus: EquipmentRecordStatus::DRAFT,
      status: EquipmentStatus::DECOMMISSIONED,
      facilityId: self::FACILITY_ID,
      interventionId: self::INTERVENTION_ID,
    );

    self::assertNull($equipment->applyPatch(new CanonicalEquipmentPatch(hasStatus: true, status: 'operational')));
    self::assertSame(EquipmentStatus::OPERATIONAL, $equipment->status());
    self::assertNull($equipment->commissionedAt(), 'A scratchpad edit stamps no commissioning date.');
    self::assertTrue($equipment->isScratchpad());
  }

  /**
   * Method testAnUnsupportedStatusValueIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnsupportedStatusValueIsRejected(): void
  {
    $this->expectException(CanonicalEquipmentValidationException::class);
    $this->expectExceptionMessage('Equipment status "nonsense" is not a supported value.');

    $this->equipment()->applyPatch(new CanonicalEquipmentPatch(hasStatus: true, status: 'nonsense'));
  }

  /**
   * Method testAnUnknownTypeIsAcceptedBecauseTheDtoNeverConstrainedIt.
   *
   * `PatchCanonicalEquipmentInput::$type` carries `#[Assert\Length(max: 32)]`,
   * NOT `#[Assert\Choice]`. Narrowing it to `EquipmentType` here would turn
   * today's 200 into a 422 — a contract change, not a refactor's side effect.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownTypeIsAcceptedBecauseTheDtoNeverConstrainedIt(): void
  {
    $equipment = $this->equipment();

    $equipment->applyPatch(new CanonicalEquipmentPatch(hasType: true, type: 'bespoke_widget'));

    self::assertSame('bespoke_widget', $equipment->type());
  }
  // #endregion

  // #region Tests — decommission and revision
  /**
   * Method testDecommissioningReportsThePreviousStatus.
   *
   * @return void no return value
   */
  #[Test]
  public function testDecommissioningReportsThePreviousStatus(): void
  {
    $equipment = $this->equipment(status: EquipmentStatus::UNDER_MAINTENANCE, facilityId: self::FACILITY_ID);

    self::assertSame('under_maintenance', $equipment->decommission());
    self::assertSame(EquipmentStatus::DECOMMISSIONED, $equipment->status());
    self::assertSame(4, $equipment->revision());
  }

  /**
   * Method testDecommissioningAnAlreadyRetiredAssetIsAnIdempotentNoOp.
   *
   * No revision bump — a repeat DELETE must not invalidate the caller's
   * `If-Match`.
   *
   * @return void no return value
   */
  #[Test]
  public function testDecommissioningAnAlreadyRetiredAssetIsAnIdempotentNoOp(): void
  {
    $equipment = $this->equipment(status: EquipmentStatus::DECOMMISSIONED);

    self::assertNull($equipment->decommission());
    self::assertSame(3, $equipment->revision());
  }

  /**
   * Method testAStaleRevisionIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRejected(): void
  {
    $this->expectException(EquipmentRevisionMismatchException::class);
    $this->expectExceptionMessage('The resource revision is stale.');

    $this->equipment()->assertRevisionMatches(2);
  }
  // #endregion

  // #region Providers
  /**
   * Method inServiceStatuses.
   *
   * @return iterable<string, array{string}> the statuses requiring a facility
   */
  public static function inServiceStatuses(): iterable
  {
    yield 'operational' => ['operational'];

    yield 'under_maintenance' => ['under_maintenance'];
  }
  // #endregion

  // #region Helpers
  /**
   * Method equipment.
   *
   * @param EquipmentRecordStatus $recordStatus the record status
   * @param EquipmentStatus $status the asset status
   * @param ?string $facilityId the assigned facility
   * @param ?string $interventionId the preparing intervention
   * @param ?string $brand the stored brand
   * @param ?string $model the stored model
   * @param ?DateTimeImmutable $commissionedAt the first commissioning date
   *
   * @return CanonicalEquipment a published, in-stock asset at revision 3
   */
  private function equipment(
    EquipmentRecordStatus $recordStatus = EquipmentRecordStatus::PUBLISHED,
    EquipmentStatus $status = EquipmentStatus::IN_STOCK,
    ?string $facilityId = null,
    ?string $interventionId = null,
    ?string $brand = null,
    ?string $model = null,
    ?DateTimeImmutable $commissionedAt = null,
  ): CanonicalEquipment {
    return CanonicalEquipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      facilityId: $facilityId,
      type: 'fire_extinguisher',
      subType: null,
      brand: $brand,
      model: $model,
      serialNumber: null,
      locationLabel: null,
      status: $status,
      commissionedAt: $commissionedAt,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
