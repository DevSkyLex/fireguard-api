<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration;

use DateTimeImmutable;
use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort, FacilitySpatialReadPort};
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration\{SetFacilityAttachmentCalibrationCommand, SetFacilityAttachmentCalibrationHandler};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException, FacilityRevisionMismatchException};
use Facility\Domain\Model\Attachment\{FacilityAttachment, FacilityAttachmentCreationOptions, FacilityAttachmentRestoredState};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType, PlanCalibration};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test SetFacilityAttachmentCalibrationHandlerTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetFacilityAttachmentCalibrationHandler::class)]
final class SetFacilityAttachmentCalibrationHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655460001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655460002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655460003';

  private const string BUILDING_ID = '550e8400-e29b-41d4-a716-446655460004';

  #[Test]
  public function testCalibratesKnownImageAndBumpsAttachmentRevision(): void
  {
    $repository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $repository->method('findById')->willReturn($this->attachment());
    $repository->expects(self::once())->method('saveCalibration')->with(
      self::callback(static fn (FacilityAttachment $attachment): bool => 2 === $attachment->revision() && 30.0 === $attachment->calibration()?->widthMeters && self::BUILDING_ID === $attachment->calibrationBuildingId()),
      1,
    );
    $result = $this->handler($repository)(new SetFacilityAttachmentCalibrationCommand('user-id', self::ATTACHMENT_ID, 1, $this->calibration()));

    self::assertSame(2, $result->revision);
    self::assertSame(self::ATTACHMENT_ID, $result->id);
    self::assertSame($this->calibration(), $result->calibration);
    self::assertSame(self::BUILDING_ID, $result->calibrationBuildingId);
    self::assertNull($result->calibrationIssue);
    self::assertSame(1000, $result->imageWidth);
    self::assertSame(500, $result->imageHeight);
  }

  #[Test]
  public function testClearCalibrationUsesSameRevisionContract(): void
  {
    $repository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $repository->method('findById')->willReturn($this->attachment(calibrated: true));
    $repository->expects(self::once())->method('saveCalibration')->with(self::callback(static fn (FacilityAttachment $attachment): bool => null === $attachment->calibration() && null === $attachment->calibrationBuildingId()), 1);
    $result = $this->handler($repository)(new SetFacilityAttachmentCalibrationCommand('user-id', self::ATTACHMENT_ID, 1, null));
    self::assertNull($result->calibration);
    self::assertNull($result->calibrationBuildingId);
    self::assertNull($result->calibrationIssue);
    self::assertSame(2, $result->revision);
  }

  #[Test]
  public function testCalibrationWithoutBuildingRetainsValuesAndSignalsUnverifiedFrame(): void
  {
    $repository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $repository->method('findById')->willReturn($this->attachment());
    $repository->expects(self::once())->method('saveCalibration')->with(
      self::callback(static fn (FacilityAttachment $attachment): bool => null === $attachment->calibrationBuildingId() && 30.0 === $attachment->calibration()?->widthMeters),
      1,
    );
    $result = $this->handler($repository, hasBuilding: false)(new SetFacilityAttachmentCalibrationCommand('user-id', self::ATTACHMENT_ID, 1, $this->calibration()));

    self::assertSame($this->calibration(), $result->calibration);
    self::assertNull($result->calibrationBuildingId);
    self::assertSame('unverified_frame', $result->calibrationIssue);
  }

  #[Test]
  public function testRejectsMissingPermissionBeforeMutation(): void
  {
    $repository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $repository->method('findById')->willReturn($this->attachment());
    $repository->expects(self::never())->method('saveCalibration');
    $this->expectException(FacilityAccessDeniedException::class);
    $this->handler($repository, OrganizationAccessDecision::MISSING_PERMISSION)(new SetFacilityAttachmentCalibrationCommand('user-id', self::ATTACHMENT_ID, 1, $this->calibration()));
  }

  #[Test]
  public function testHidesOutsideOrganizationAttachment(): void
  {
    $repository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $repository->method('findById')->willReturn($this->attachment());
    $repository->expects(self::never())->method('saveCalibration');
    $this->expectException(FacilityAttachmentNotFoundException::class);
    $this->handler($repository, OrganizationAccessDecision::OUTSIDE_SCOPE)(new SetFacilityAttachmentCalibrationCommand('user-id', self::ATTACHMENT_ID, 1, $this->calibration()));
  }

  #[Test]
  public function testRejectsStaleRevisionBeforeSaving(): void
  {
    $repository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $repository->method('findById')->willReturn($this->attachment());
    $repository->expects(self::never())->method('saveCalibration');
    $this->expectException(FacilityRevisionMismatchException::class);
    $this->handler($repository)(new SetFacilityAttachmentCalibrationCommand('user-id', self::ATTACHMENT_ID, 9, $this->calibration()));
  }

  private function handler(FacilityAttachmentRepositoryPort $repository, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED, bool $hasBuilding = true): SetFacilityAttachmentCalibrationHandler
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn(Facility::create(FacilityId::fromString(self::FACILITY_ID), FacilityOrganizationId::fromString(self::ORGANIZATION_ID), FacilityType::FLOOR, new FacilityName('Ground floor')));
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('user-id', self::ORGANIZATION_ID, 'organization.facilities.write')->willReturn($decision);
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $spatialRead = $this->createStub(FacilitySpatialReadPort::class);
    $spatialRead->method('readContext')->willReturn(new FacilitySpatialContext([
      self::FACILITY_ID => ['parentId' => $hasBuilding ? self::BUILDING_ID : null, 'type' => 'floor', 'recordStatus' => 'published'],
      self::BUILDING_ID => ['parentId' => null, 'type' => 'building', 'recordStatus' => 'published'],
    ]));

    return new SetFacilityAttachmentCalibrationHandler($repository, $facilityRepository, $authorization, $transactionManager, new FacilitySpatialValidityResolver($spatialRead));
  }

  private function attachment(bool $calibrated = false): FacilityAttachment
  {
    return FacilityAttachment::reconstitute(
      FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      FacilityId::fromString(self::FACILITY_ID),
      'plan.png',
      'plan.png',
      'image/png',
      100,
      new FacilityAttachmentRestoredState(new DateTimeImmutable(), new FacilityAttachmentCreationOptions(kind: AttachmentKind::FLOOR_PLAN, imageWidth: 1000, imageHeight: 500), calibration: $calibrated ? PlanCalibration::fromArray($this->calibration()) : null, calibrationBuildingId: $calibrated ? self::BUILDING_ID : null),
    );
  }

  /**
   * @return array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}
   */
  private function calibration(): array
  {
    return ['widthMeters' => 30.0, 'rotationDegrees' => 90.0, 'offsetXMeters' => -2.0, 'offsetZMeters' => 5.0];
  }
}
