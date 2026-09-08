<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\SearchOrganization;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Contract\Search\OrganizationSearchHit;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentSearchPort, FacilitySearchPort, InspectionSearchPort, InterventionSearchPort, NonConformitySearchPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\SearchOrganization\{SearchOrganizationHandler, SearchOrganizationQuery, SearchOrganizationResult};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Test SearchOrganizationHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SearchOrganizationHandler::class)]
final class SearchOrganizationHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655448801';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655448802';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655448803';

  #[Test]
  public function testInvokeSearchesEveryTypeWhenCallerHoldsEveryPermission(): void
  {
    $equipmentHit = new OrganizationSearchHit(id: 'eq-1', title: 'Acme X200', subtitle: 'SN-1');
    $facilityHit = new OrganizationSearchHit(id: 'fa-1', title: 'Main Site', subtitle: 'MS');
    $interventionHit = new OrganizationSearchHit(id: 'in-1', title: 'Setup', subtitle: '#42');
    $inspectionHit = new OrganizationSearchHit(id: 'ip-1', title: 'CHK-01', subtitle: 'closed');
    $nonConformityHit = new OrganizationSearchHit(id: 'nc-1', title: 'Broken seal', subtitle: 'high');

    $equipmentSearch = $this->createMock(EquipmentSearchPort::class);
    $equipmentSearch->expects(self::once())
      ->method('search')
      ->with(self::ORGANIZATION_ID, 'extinguisher', 5)
      ->willReturn([$equipmentHit]);

    $facilitySearch = $this->createMock(FacilitySearchPort::class);
    $facilitySearch->expects(self::once())
      ->method('search')
      ->with(self::ORGANIZATION_ID, 'extinguisher', 5)
      ->willReturn([$facilityHit]);

    $interventionSearch = $this->createMock(InterventionSearchPort::class);
    $interventionSearch->expects(self::once())
      ->method('search')
      ->with(self::ORGANIZATION_ID, 'extinguisher', 5)
      ->willReturn([$interventionHit]);

    $inspectionSearch = $this->createMock(InspectionSearchPort::class);
    $inspectionSearch->expects(self::once())
      ->method('search')
      ->with(self::ORGANIZATION_ID, 'extinguisher', 5)
      ->willReturn([$inspectionHit]);

    $nonConformitySearch = $this->createMock(NonConformitySearchPort::class);
    $nonConformitySearch->expects(self::once())
      ->method('search')
      ->with(self::ORGANIZATION_ID, 'extinguisher', 5)
      ->willReturn([$nonConformityHit]);

    $handler = $this->makeHandler(
      permissions: [
        'organization.equipment.read' => true,
        'organization.facilities.read' => true,
        'organization.interventions.read' => true,
        'organization.inspection.read' => true,
      ],
      equipmentSearch: $equipmentSearch,
      facilitySearch: $facilitySearch,
      interventionSearch: $interventionSearch,
      inspectionSearch: $inspectionSearch,
      nonConformitySearch: $nonConformitySearch,
    );

    $result = $handler->__invoke(new SearchOrganizationQuery(self::ORGANIZATION_ID, self::USER_ID, 'extinguisher'));

    self::assertInstanceOf(SearchOrganizationResult::class, $result);
    self::assertSame([$equipmentHit], $result->equipments);
    self::assertSame([$facilityHit], $result->facilities);
    self::assertSame([$interventionHit], $result->interventions);
    self::assertSame([$inspectionHit], $result->inspections);
    self::assertSame([$nonConformityHit], $result->nonConformities);
  }

  #[Test]
  public function testInvokeOmitsEachTypeTheCallerMayNotRead(): void
  {
    $equipmentSearch = $this->createMock(EquipmentSearchPort::class);
    $equipmentSearch->expects(self::never())->method('search');

    $facilitySearch = $this->createMock(FacilitySearchPort::class);
    $facilitySearch->expects(self::once())
      ->method('search')
      ->willReturn([new OrganizationSearchHit(id: 'fa-1', title: 'Main Site')]);

    $interventionSearch = $this->createMock(InterventionSearchPort::class);
    $interventionSearch->expects(self::never())->method('search');

    $inspectionSearch = $this->createMock(InspectionSearchPort::class);
    $inspectionSearch->expects(self::never())->method('search');

    $nonConformitySearch = $this->createMock(NonConformitySearchPort::class);
    $nonConformitySearch->expects(self::never())->method('search');

    $handler = $this->makeHandler(
      permissions: [
        'organization.equipment.read' => false,
        'organization.facilities.read' => true,
        'organization.interventions.read' => false,
        'organization.inspection.read' => false,
      ],
      equipmentSearch: $equipmentSearch,
      facilitySearch: $facilitySearch,
      interventionSearch: $interventionSearch,
      inspectionSearch: $inspectionSearch,
      nonConformitySearch: $nonConformitySearch,
    );

    $result = $handler->__invoke(new SearchOrganizationQuery(self::ORGANIZATION_ID, self::USER_ID, 'site'));

    self::assertSame([], $result->equipments);
    self::assertCount(1, $result->facilities);
    self::assertSame([], $result->interventions);
    self::assertSame([], $result->inspections);
    self::assertSame([], $result->nonConformities);
  }

  #[Test]
  public function testInvokeGatesInspectionsAndNonConformitiesOnTheSamePermission(): void
  {
    $inspectionSearch = $this->createMock(InspectionSearchPort::class);
    $inspectionSearch->expects(self::once())->method('search')->willReturn([]);

    $nonConformitySearch = $this->createMock(NonConformitySearchPort::class);
    $nonConformitySearch->expects(self::once())->method('search')->willReturn([]);

    $handler = $this->makeHandler(
      permissions: [
        'organization.equipment.read' => false,
        'organization.facilities.read' => false,
        'organization.interventions.read' => false,
        'organization.inspection.read' => true,
      ],
      inspectionSearch: $inspectionSearch,
      nonConformitySearch: $nonConformitySearch,
    );

    $handler->__invoke(new SearchOrganizationQuery(self::ORGANIZATION_ID, self::USER_ID, 'chk'));
  }

  #[Test]
  public function testInvokeRejectsATermShorterThanTwoCharactersAfterTrimming(): void
  {
    $handler = $this->makeHandler(permissions: []);

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new SearchOrganizationQuery(self::ORGANIZATION_ID, self::USER_ID, '  a  '));
  }

  #[Test]
  public function testInvokeRejectsATermLongerThanOneHundredCharacters(): void
  {
    $handler = $this->makeHandler(permissions: []);

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new SearchOrganizationQuery(self::ORGANIZATION_ID, self::USER_ID, str_repeat('a', 101)));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationDoesNotExist(): void
  {
    $handler = $this->makeHandler(permissions: [], organizationExists: false);

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new SearchOrganizationQuery(self::ORGANIZATION_ID, self::USER_ID, 'term'));
  }

  #[Test]
  public function testInvokeThrowsWhenCallerHasNoActiveMembership(): void
  {
    $handler = $this->makeHandler(permissions: [], memberIsActive: false);

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new SearchOrganizationQuery(self::ORGANIZATION_ID, self::USER_ID, 'term'));
  }

  /**
   * Method makeHandler.
   *
   * @param array<string, bool> $permissions granted state per permission name
   */
  private function makeHandler(
    array $permissions,
    bool $organizationExists = true,
    bool $memberIsActive = true,
    ?EquipmentSearchPort $equipmentSearch = null,
    ?FacilitySearchPort $facilitySearch = null,
    ?InterventionSearchPort $interventionSearch = null,
    ?InspectionSearchPort $inspectionSearch = null,
    ?NonConformitySearchPort $nonConformitySearch = null,
  ): SearchOrganizationHandler {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organizationExists ? $this->makeOrganization() : null);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn($this->makeMember($memberIsActive));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(
      static fn (string $userId, string $organizationId, string $permission): bool => $permissions[$permission] ?? false,
    );

    return new SearchOrganizationHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      authorization: $authorization,
      equipmentSearch: $equipmentSearch ?? $this->emptySearchStub(EquipmentSearchPort::class),
      facilitySearch: $facilitySearch ?? $this->emptySearchStub(FacilitySearchPort::class),
      interventionSearch: $interventionSearch ?? $this->emptySearchStub(InterventionSearchPort::class),
      inspectionSearch: $inspectionSearch ?? $this->emptySearchStub(InspectionSearchPort::class),
      nonConformitySearch: $nonConformitySearch ?? $this->emptySearchStub(NonConformitySearchPort::class),
    );
  }

  /**
   * Method emptySearchStub.
   *
   * @template T of object
   *
   * @param class-string<T> $portClass
   *
   * @return T&Stub
   */
  private function emptySearchStub(string $portClass): object
  {
    $stub = $this->createStub($portClass);
    $stub->method('search')->willReturn([]);

    return $stub;
  }

  private function makeOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Bordeaux'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
    );
  }

  private function makeMember(bool $isActive): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      userId: self::USER_ID,
      isActive: $isActive,
      joinedAt: new DateTimeImmutable('-5 days'),
    );
  }
}
