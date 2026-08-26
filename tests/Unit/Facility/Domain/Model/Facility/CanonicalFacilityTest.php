<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\Exception\{CanonicalFacilityValidationException, FacilityRevisionMismatchException};
use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\{
  CanonicalFacilityParent,
  CanonicalFacilityPatch,
  FacilityId,
  FacilityOrganizationId,
  FacilityRecordStatus,
  FacilityStatus,
  FacilityType
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CanonicalFacilityTest.
 *
 * The rules that used to live inside `CanonicalFacilityMutationProcessor`,
 * asserted where they now are — no container, no mocks, no database.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalFacility::class)]
#[CoversClass(CanonicalFacilityPatch::class)]
final class CanonicalFacilityTest extends TestCase
{
  // #region Constants
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440041';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440042';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655440045';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440044';
  // #endregion

  // #region Tests — patch
  /**
   * Method testAPatchReportsOnlyTheFieldsThatActuallyDiffer.
   *
   * Resending the current value is a no-op: the changed-field list drives an
   * audit event, and an event saying "name changed" when it did not is worse
   * than no event.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchReportsOnlyTheFieldsThatActuallyDiffer(): void
  {
    $facility = $this->facility(name: 'Main site', code: 'S-1');

    $change = $facility->applyPatch(new CanonicalFacilityPatch(
      hasName: true,
      name: 'Main site',
      hasCode: true,
      code: 'S-2',
    ));

    self::assertSame(['code'], $change->changedFields);
    self::assertSame('S-2', $facility->code());
    self::assertSame(4, $facility->revision());
  }

  /**
   * Method testNameAndCodeAreTrimmed.
   *
   * @return void no return value
   */
  #[Test]
  public function testNameAndCodeAreTrimmed(): void
  {
    $facility = $this->facility();

    $facility->applyPatch(new CanonicalFacilityPatch(
      hasName: true,
      name: '  Padded site  ',
      hasCode: true,
      code: '  S-9  ',
      hasAddress: true,
      address: '  1 Rue  ',
    ));

    self::assertSame('Padded site', $facility->name());
    self::assertSame('S-9', $facility->code());
    self::assertSame('1 Rue', $facility->address());
  }

  /**
   * Method testAnExplicitNullErasesANullableFieldWhileAnAbsentKeyDoesNot.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnExplicitNullErasesANullableFieldWhileAnAbsentKeyDoesNot(): void
  {
    $facility = $this->facility(code: 'S-1', address: '1 Rue');

    $facility->applyPatch(new CanonicalFacilityPatch(hasCode: true, code: null));

    self::assertNull($facility->code());
    self::assertSame('1 Rue', $facility->address());
  }

  /**
   * Method testANullTypeIsRejectedBeforeANullName.
   *
   * The order is the processor's, and therefore what a client sending both
   * mistakes at once observes.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullTypeIsRejectedBeforeANullName(): void
  {
    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Facility type cannot be null.');

    new CanonicalFacilityPatch(hasType: true, type: null, hasName: true, name: null)
      ->assertDescriptiveFieldsAreValid();
  }

  /**
   * Method testANullNameAloneIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullNameAloneIsRejected(): void
  {
    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Facility name cannot be null.');

    new CanonicalFacilityPatch(hasName: true, name: null)->assertDescriptiveFieldsAreValid();
  }

  /**
   * Method testASingleCoordinateKeyIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testASingleCoordinateKeyIsRejected(): void
  {
    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Facility latitude and longitude must be provided together.');

    new CanonicalFacilityPatch(hasLatitude: true, latitude: 48.85)->assertDescriptiveFieldsAreValid();
  }

  /**
   * Method testAHalfNullCoordinatePairIsRejected.
   *
   * Both keys present is not enough — one value null and the other set puts
   * the facility on no map.
   *
   * @return void no return value
   */
  #[Test]
  public function testAHalfNullCoordinatePairIsRejected(): void
  {
    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Facility latitude and longitude must be provided together.');

    new CanonicalFacilityPatch(hasLatitude: true, latitude: 48.85, hasLongitude: true, longitude: null)
      ->assertDescriptiveFieldsAreValid();
  }

  /**
   * Method testBothCoordinatesSentAsNullClearThePair.
   *
   * @return void no return value
   */
  #[Test]
  public function testBothCoordinatesSentAsNullClearThePair(): void
  {
    $facility = $this->facility(latitude: 48.85, longitude: 2.35);
    $patch = new CanonicalFacilityPatch(hasLatitude: true, latitude: null, hasLongitude: true, longitude: null);
    $patch->assertDescriptiveFieldsAreValid();

    $change = $facility->applyPatch($patch);

    self::assertNull($facility->latitude());
    self::assertNull($facility->longitude());
    self::assertSame(['coordinates'], $change->changedFields);
  }

  /**
   * Method testANullStatusIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullStatusIsRejected(): void
  {
    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Facility status cannot be null.');

    new CanonicalFacilityPatch(hasStatus: true, status: null)->assertStatusIsPresent();
  }

  /**
   * Method testArchivingAPublishedFacilityIsReported.
   *
   * @return void no return value
   */
  #[Test]
  public function testArchivingAPublishedFacilityIsReported(): void
  {
    $change = $this->facility()->applyPatch(new CanonicalFacilityPatch(hasStatus: true, status: 'archived'));

    self::assertTrue($change->archived);
    self::assertFalse($change->restored);
  }

  /**
   * Method testRestoringAPublishedFacilityIsReported.
   *
   * @return void no return value
   */
  #[Test]
  public function testRestoringAPublishedFacilityIsReported(): void
  {
    $change = $this->facility(status: FacilityStatus::ARCHIVED)
      ->applyPatch(new CanonicalFacilityPatch(hasStatus: true, status: 'active'));

    self::assertTrue($change->restored);
    self::assertFalse($change->archived);
  }

  /**
   * Method testRestoringUnderAnArchivedParentIsRejected.
   *
   * Reactivating a facility into an archived subtree would leave it visible
   * under an invisible ancestor.
   *
   * @return void no return value
   */
  #[Test]
  public function testRestoringUnderAnArchivedParentIsRejected(): void
  {
    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Cannot restore a facility while its parent is archived.');

    $this->facility(status: FacilityStatus::ARCHIVED, parentFacilityId: self::PARENT_ID)->applyPatch(
      new CanonicalFacilityPatch(hasStatus: true, status: 'active'),
      new CanonicalFacilityParent(self::PARENT_ID, FacilityStatus::ARCHIVED),
    );
  }

  /**
   * Method testASameParentMoveReportsNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testASameParentMoveReportsNothing(): void
  {
    $facility = $this->facility(parentFacilityId: self::PARENT_ID);

    $change = $facility->applyPatch(
      new CanonicalFacilityPatch(hasParent: true, parentFacilityId: self::PARENT_ID),
      new CanonicalFacilityParent(self::PARENT_ID, FacilityStatus::ACTIVE),
    );

    self::assertFalse($change->parentMoved);
  }

  /**
   * Method testDetachingTheParentIsAMove.
   *
   * @return void no return value
   */
  #[Test]
  public function testDetachingTheParentIsAMove(): void
  {
    $facility = $this->facility(parentFacilityId: self::PARENT_ID);

    $change = $facility->applyPatch(new CanonicalFacilityPatch(hasParent: true, parentFacilityId: null));

    self::assertTrue($change->parentMoved);
    self::assertSame(self::PARENT_ID, $change->previousParentFacilityId);
    self::assertNull($change->newParentFacilityId);
    self::assertNull($facility->parentFacilityId());
  }

  /**
   * Method testAScratchpadReportsNothingAtAll.
   *
   * A draft record is an intervention preparation, not a compliance record:
   * no status event, no move event, no changed-field list.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadReportsNothingAtAll(): void
  {
    $facility = $this->facility(recordStatus: FacilityRecordStatus::DRAFT, interventionId: self::INTERVENTION_ID);

    $change = $facility->applyPatch(new CanonicalFacilityPatch(
      hasStatus: true,
      status: 'archived',
      hasName: true,
      name: 'Renamed',
    ));

    self::assertFalse($change->archived);
    self::assertSame([], $change->changedFields);
    self::assertSame(FacilityStatus::ARCHIVED, $facility->status());
    self::assertTrue($facility->isScratchpad());
  }

  /**
   * Method testAnUnsupportedTypeValueIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnsupportedTypeValueIsRejected(): void
  {
    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Facility type "nonsense" is not a supported value.');

    $this->facility()->applyPatch(new CanonicalFacilityPatch(hasType: true, type: 'nonsense'));
  }
  // #endregion

  // #region Tests — archive and revision
  /**
   * Method testArchivingReportsTrueOnceAndFalseAfterwards.
   *
   * A repeat DELETE must not bump the revision — it must not invalidate the
   * caller's `If-Match`.
   *
   * @return void no return value
   */
  #[Test]
  public function testArchivingReportsTrueOnceAndFalseAfterwards(): void
  {
    $facility = $this->facility();

    self::assertTrue($facility->archive());
    self::assertSame(4, $facility->revision());

    self::assertFalse($facility->archive());
    self::assertSame(4, $facility->revision());
    self::assertTrue($facility->isAlreadyArchived());
  }

  /**
   * Method testAStaleRevisionIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRejected(): void
  {
    $this->expectException(FacilityRevisionMismatchException::class);
    $this->expectExceptionMessage('The resource revision is stale.');

    $this->facility()->assertRevisionMatches(2);
  }

  /**
   * Method testWouldRestoreOnlyRecognisesAPublishedArchivedToActivePatch.
   *
   * It is what decides whether the handler pays for an extra parent read.
   *
   * @return void no return value
   */
  #[Test]
  public function testWouldRestoreOnlyRecognisesAPublishedArchivedToActivePatch(): void
  {
    $restore = new CanonicalFacilityPatch(hasStatus: true, status: 'active');

    self::assertTrue($this->facility(status: FacilityStatus::ARCHIVED)->wouldRestore($restore));
    self::assertFalse($this->facility()->wouldRestore($restore));
    self::assertFalse($this->facility(status: FacilityStatus::ARCHIVED)->wouldRestore(new CanonicalFacilityPatch()));
    self::assertFalse(
      $this->facility(recordStatus: FacilityRecordStatus::DRAFT, status: FacilityStatus::ARCHIVED)->wouldRestore($restore),
    );
  }
  // #endregion

  // #region Helpers
  /**
   * Method facility.
   *
   * @param FacilityRecordStatus $recordStatus the record status
   * @param FacilityStatus $status the facility status
   * @param ?string $parentFacilityId the parent facility
   * @param ?string $interventionId the preparing intervention
   * @param string $name the stored name
   * @param ?string $code the stored code
   * @param ?string $address the stored address
   * @param ?float $latitude the stored latitude
   * @param ?float $longitude the stored longitude
   *
   * @return CanonicalFacility a published, active site at revision 3
   */
  private function facility(
    FacilityRecordStatus $recordStatus = FacilityRecordStatus::PUBLISHED,
    FacilityStatus $status = FacilityStatus::ACTIVE,
    ?string $parentFacilityId = null,
    ?string $interventionId = null,
    string $name = 'Main site',
    ?string $code = null,
    ?string $address = null,
    ?float $latitude = null,
    ?float $longitude = null,
  ): CanonicalFacility {
    return CanonicalFacility::reconstitute(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      parentFacilityId: $parentFacilityId,
      type: FacilityType::SITE,
      name: $name,
      code: $code,
      address: $address,
      latitude: $latitude,
      longitude: $longitude,
      metadata: [],
      status: $status,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
