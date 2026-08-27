<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\ExportFacilities;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Query\ExportFacilities\{ExportFacilitiesHandler, ExportFacilitiesQuery, ExportFacilitiesResult};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityExportTooLargeException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ExportFacilitiesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportFacilitiesHandler::class)]
final class ExportFacilitiesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449301';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449302';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655449303';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('countByOrganizationId');
    $repository->expects(self::never())->method('findByOrganizationId');

    $handler = new ExportFacilitiesHandler(facilityRepository: $repository, authorization: $authorization);

    $this->expectException(FacilityAccessDeniedException::class);

    $handler->__invoke(new ExportFacilitiesQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('countByOrganizationId');

    $handler = new ExportFacilitiesHandler(facilityRepository: $repository, authorization: $authorization);

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new ExportFacilitiesQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(ExportFacilitiesHandler::MAX_EXPORT_ROWS + 1);
    $repository->expects(self::never())->method('findByOrganizationId');

    $handler = new ExportFacilitiesHandler(facilityRepository: $repository, authorization: $authorization);

    $this->expectException(FacilityExportTooLargeException::class);

    $handler->__invoke(new ExportFacilitiesQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeResolvesParentCodeInBulkAndFallsBackToNullWhenUnresolved(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $organizationId = FacilityOrganizationId::fromString(self::ORG_ID);

    $child = Facility::create(
      id: FacilityId::fromString('550e8400-e29b-41d4-a716-446655449304'),
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 1'),
      parentFacilityId: FacilityId::fromString(self::PARENT_ID),
      code: 'FL-1',
      address: '1 Rue de Paris',
      coordinates: new FacilityCoordinates(48.8566, 2.3522),
    );
    $orphan = Facility::create(
      id: FacilityId::fromString('550e8400-e29b-41d4-a716-446655449305'),
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
      parentFacilityId: FacilityId::fromString('550e8400-e29b-41d4-a716-446655449306'),
      code: 'SITE-1',
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(2);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$child, $orphan]);
    $repository->expects(self::once())
      ->method('getFacilityCodesByIds')
      ->with($organizationId, [self::PARENT_ID, '550e8400-e29b-41d4-a716-446655449306'])
      ->willReturn([self::PARENT_ID => 'PARENT-CODE']);

    $handler = new ExportFacilitiesHandler(facilityRepository: $repository, authorization: $authorization);

    $result = $handler->__invoke(new ExportFacilitiesQuery(self::USER_ID, self::ORG_ID, []));

    self::assertInstanceOf(ExportFacilitiesResult::class, $result);
    self::assertSame(2, $result->total);
    self::assertCount(2, $result->rows);
    self::assertSame('FL-1', $result->rows[0]->code);
    self::assertSame('PARENT-CODE', $result->rows[0]->parentCode);
    self::assertSame(48.8566, $result->rows[0]->latitude);
    self::assertSame(2.3522, $result->rows[0]->longitude);
    self::assertSame('SITE-1', $result->rows[1]->code);
    self::assertNull($result->rows[1]->parentCode, 'An unresolvable parent id must resolve to a null parentCode, not an empty string.');
  }
}
