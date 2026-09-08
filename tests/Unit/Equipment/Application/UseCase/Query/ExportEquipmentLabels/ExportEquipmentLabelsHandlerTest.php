<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\ExportEquipmentLabels;

use Equipment\Application\Contract\Export\EquipmentExportCandidate;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort};
use Equipment\Application\UseCase\Query\ExportEquipmentLabels\{ExportEquipmentLabelsHandler, ExportEquipmentLabelsQuery, ExportEquipmentLabelsResult};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentLabelExportTooLargeException, EquipmentNotFoundException};
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test ExportEquipmentLabelsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportEquipmentLabelsHandler::class)]
final class ExportEquipmentLabelsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655450301';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655450302';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655450303';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655450304';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('countEquipmentLabelCandidates');
    $repository->expects(self::never())->method('listEquipmentLabelCandidates');

    $handler = $this->createHandler($repository, $authorization);

    $this->expectException(EquipmentAccessDeniedException::class);

    $handler->__invoke(new ExportEquipmentLabelsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('countEquipmentLabelCandidates');

    $handler = $this->createHandler($repository, $authorization);

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new ExportEquipmentLabelsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeRejectsAmbiguousSelection(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('countEquipmentLabelCandidates');

    $handler = $this->createHandler($repository, $this->grantingAuthorization());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Provide either ids[] or facilityId, not both.');

    $handler->__invoke(new ExportEquipmentLabelsQuery(
      userId: self::USER_ID,
      organizationId: self::ORG_ID,
      equipmentIds: [self::EQUIPMENT_ID],
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsAnEmptyIdList(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('countEquipmentLabelCandidates');

    $handler = $this->createHandler($repository, $this->grantingAuthorization());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('ids[] must not be empty when provided.');

    $handler->__invoke(new ExportEquipmentLabelsQuery(
      userId: self::USER_ID,
      organizationId: self::ORG_ID,
      equipmentIds: [],
    ));
  }

  #[Test]
  public function testInvokeRejectsAnIdListLargerThanTheCapWithoutTouchingTheRepository(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('countEquipmentLabelCandidates');
    $repository->expects(self::never())->method('listEquipmentLabelCandidates');

    $handler = $this->createHandler($repository, $this->grantingAuthorization());

    $ids = [];
    for ($index = 0; $index <= ExportEquipmentLabelsHandler::MAX_LABELS; ++$index) {
      $ids[] = sprintf('id-%d', $index);
    }

    $this->expectException(EquipmentLabelExportTooLargeException::class);

    $handler->__invoke(new ExportEquipmentLabelsQuery(
      userId: self::USER_ID,
      organizationId: self::ORG_ID,
      equipmentIds: $ids,
    ));
  }

  #[Test]
  public function testInvokeRejectsASelectionMatchingMoreThanTheCapBeforeFetchingRows(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countEquipmentLabelCandidates')
      ->willReturn(ExportEquipmentLabelsHandler::MAX_LABELS + 1);
    $repository->expects(self::never())->method('listEquipmentLabelCandidates');

    $handler = $this->createHandler($repository, $this->grantingAuthorization());

    $this->expectException(EquipmentLabelExportTooLargeException::class);

    $handler->__invoke(new ExportEquipmentLabelsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeResolvesFacilityNamesInOneBulkRoundTrip(): void
  {
    $candidate = $this->createCandidate(facilityId: self::FACILITY_ID);

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countEquipmentLabelCandidates')
      ->with(
        self::callback(static fn (EquipmentOrganizationId $id): bool => self::ORG_ID === (string) $id),
        null,
        self::FACILITY_ID,
      )
      ->willReturn(1);
    $repository->expects(self::once())
      ->method('listEquipmentLabelCandidates')
      ->with(
        self::callback(static fn (EquipmentOrganizationId $id): bool => self::ORG_ID === (string) $id),
        null,
        self::FACILITY_ID,
      )
      ->willReturn([$candidate]);

    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([self::FACILITY_ID])
      ->willReturn([self::FACILITY_ID => 'Main Warehouse']);

    $handler = new ExportEquipmentLabelsHandler(
      repository: $repository,
      facilityNaming: $facilityNaming,
      authorization: $this->grantingAuthorization(),
    );

    $result = $handler->__invoke(new ExportEquipmentLabelsQuery(
      userId: self::USER_ID,
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertInstanceOf(ExportEquipmentLabelsResult::class, $result);
    self::assertSame(1, $result->total);
    self::assertSame('facility', $result->selection);
    self::assertCount(1, $result->rows);
    self::assertSame(self::EQUIPMENT_ID, $result->rows[0]->id);
    self::assertSame('Main Warehouse', $result->rows[0]->facilityName);
  }

  #[Test]
  public function testInvokeDeduplicatesTheExplicitIdListAndReportsTheIdsSelection(): void
  {
    $candidate = $this->createCandidate(facilityId: null);

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countEquipmentLabelCandidates')
      ->with(self::anything(), [self::EQUIPMENT_ID], null)
      ->willReturn(1);
    $repository->expects(self::once())
      ->method('listEquipmentLabelCandidates')
      ->with(self::anything(), [self::EQUIPMENT_ID], null)
      ->willReturn([$candidate]);

    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())->method('findNamesByIds')->with([])->willReturn([]);

    $handler = new ExportEquipmentLabelsHandler(
      repository: $repository,
      facilityNaming: $facilityNaming,
      authorization: $this->grantingAuthorization(),
    );

    $result = $handler->__invoke(new ExportEquipmentLabelsQuery(
      userId: self::USER_ID,
      organizationId: self::ORG_ID,
      equipmentIds: [self::EQUIPMENT_ID, self::EQUIPMENT_ID],
    ));

    self::assertSame('ids', $result->selection);
    self::assertNull($result->rows[0]->facilityName);
  }

  private function grantingAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return $authorization;
  }

  private function createHandler(
    EquipmentRepositoryPort $repository,
    OrganizationAuthorizationPort $authorization,
  ): ExportEquipmentLabelsHandler {
    return new ExportEquipmentLabelsHandler(
      repository: $repository,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      authorization: $authorization,
    );
  }

  private function createCandidate(?string $facilityId): EquipmentExportCandidate
  {
    return new EquipmentExportCandidate(
      id: self::EQUIPMENT_ID,
      type: 'fire_extinguisher',
      subType: 'CO2',
      brand: 'Acme',
      model: 'X100',
      serialNumber: 'SN-1',
      locationLabel: 'Hallway',
      status: 'operational',
      facilityId: $facilityId,
      installedAt: '2026-01-01T00:00:00+00:00',
      commissionedAt: '2026-01-02T00:00:00+00:00',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );
  }
}
