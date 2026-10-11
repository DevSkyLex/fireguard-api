<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\Inspection;

use DateTimeImmutable;
use Facility\Application\Port\Inbound\FacilityLifecycleReferencePort;
use Facility\Application\Port\Outbound\Internal\FacilityRepositoryPort;
use Facility\Domain\Model\Facility\{Facility, FacilityLifecycle};
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityStatus, FacilityType};
use Facility\Infrastructure\Adapter\Inspection\FacilityValidationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class FacilityValidationAdapterTest
 *
 * Preserves Inspection's usable-facility refusal type and message at the owner boundary.
 *
 * @category Adapter Tests
 */
#[CoversClass(FacilityValidationAdapter::class)]
final class FacilityValidationAdapterTest extends TestCase
{
  // #region Constants
  /**
   * Constant FACILITY_ID
   */
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440301';

  /**
   * Constant ORGANIZATION_ID
   */
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440302';

  /**
   * Constant OTHER_ORGANIZATION_ID
   */
  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440303';

  /**
   * Constant INTERVENTION_ID
   */
  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440304';
  // #endregion

  // #region Methods
  /**
   * Method acceptsAnActiveFacilityAndPreservesItsInterventionScope
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function acceptsAnActiveFacilityAndPreservesItsInterventionScope(): void
  {
    $lifecycle = $this->createMock(FacilityLifecycleReferencePort::class);
    $lifecycle->expects(self::once())->method('assertReference')->with(
      self::ORGANIZATION_ID,
      self::FACILITY_ID,
      self::INTERVENTION_ID,
    );
    $adapter = new FacilityValidationAdapter($this->repository($this->facility(FacilityStatus::ACTIVE)), $lifecycle);

    $adapter->assertFacilityIsUsable(self::FACILITY_ID, self::ORGANIZATION_ID, self::INTERVENTION_ID);
  }

  /**
   * Method rejectsAnUnknownFacilityWithItsPublicValidationFailure
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function rejectsAnUnknownFacilityWithItsPublicValidationFailure(): void
  {
    $adapter = new FacilityValidationAdapter($this->repository(null));

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility with ID "' . self::FACILITY_ID . '" not found.');

    $adapter->assertFacilityIsUsable(self::FACILITY_ID, self::ORGANIZATION_ID);
  }

  /**
   * Method rejectsAForeignFacilityWithTheSamePublicValidationFailure
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function rejectsAForeignFacilityWithTheSamePublicValidationFailure(): void
  {
    $adapter = new FacilityValidationAdapter($this->repository($this->facility(FacilityStatus::ACTIVE)));

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility with ID "' . self::FACILITY_ID . '" not found.');

    $adapter->assertFacilityIsUsable(self::FACILITY_ID, self::OTHER_ORGANIZATION_ID);
  }

  /**
   * Method rejectsAnArchivedFacilityWithItsPublicValidationFailure
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function rejectsAnArchivedFacilityWithItsPublicValidationFailure(): void
  {
    $adapter = new FacilityValidationAdapter($this->repository($this->facility(FacilityStatus::ARCHIVED)));

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility with ID "' . self::FACILITY_ID . '" is archived and cannot be used.');

    $adapter->assertFacilityIsUsable(self::FACILITY_ID, self::ORGANIZATION_ID);
  }

  /**
   * Method repository
   *
   * Provides an owner-local facility without exposing its repository to Inspection.
   *
   * @access private
   *
   * @param ?Facility $facility the facility returned by the owner repository
   *
   * @return FacilityRepositoryPort the prepared repository
   */
  private function repository(?Facility $facility): FacilityRepositoryPort
  {
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->with(FacilityId::fromString(self::FACILITY_ID))->willReturn($facility);

    return $repository;
  }

  /**
   * Method facility
   *
   * Reconstitutes the facility's owner state for usability checks.
   *
   * @access private
   *
   * @param FacilityStatus $status the persisted lifecycle status
   *
   * @return Facility the facility aggregate
   */
  private function facility(FacilityStatus $status): Facility
  {
    $now = new DateTimeImmutable('2026-01-01T08:00:00+00:00');

    return Facility::reconstitute(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      type: FacilityType::BUILDING,
      name: new FacilityName('Inspection site'),
      lifecycle: new FacilityLifecycle($status, $now, $now),
    );
  }
  // #endregion
}
