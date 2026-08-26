<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\CanonicalEquipmentRepositoryPort;
use Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\{GetCanonicalEquipmentHandler, GetCanonicalEquipmentQuery};
use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentRecordStatus, EquipmentStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCanonicalEquipmentHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCanonicalEquipmentHandler::class)]
final class GetCanonicalEquipmentHandlerTest extends TestCase
{
  // #region Constants
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440031';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440032';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440034';
  // #endregion

  // #region Tests
  /**
   * Method testProjectsTheGateInputs.
   *
   * @return void no return value
   */
  #[Test]
  public function testProjectsTheGateInputs(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment());

    $result = (new GetCanonicalEquipmentHandler($equipment))(
      new GetCanonicalEquipmentQuery(self::EQUIPMENT_ID),
    );

    self::assertNotNull($result->view);
    self::assertSame(self::EQUIPMENT_ID, $result->view->id);
    self::assertSame(self::ORGANIZATION_ID, $result->view->organizationId);
    self::assertSame('draft', $result->view->recordStatus);
    self::assertSame(self::INTERVENTION_ID, $result->view->interventionId);
    self::assertSame(3, $result->view->revision);
  }

  /**
   * Method testAnUnknownIdentifierAnswersAnEmptyView.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownIdentifierAnswersAnEmptyView(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn(null);

    self::assertNull(
      (new GetCanonicalEquipmentHandler($equipment))(new GetCanonicalEquipmentQuery(self::EQUIPMENT_ID))->view,
    );
  }

  /**
   * Method testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing.
   *
   * This is what keeps a canonical mutation on a garbage id a 404 now that
   * the identifier is a validated UUID value object. The empty-string case is
   * the one the processor produces for a non-string URI variable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->expects(self::never())->method('findById');
    $handler = new GetCanonicalEquipmentHandler($equipment);

    self::assertNull($handler(new GetCanonicalEquipmentQuery('not-a-uuid'))->view);
    self::assertNull($handler(new GetCanonicalEquipmentQuery(''))->view);
  }
  // #endregion

  // #region Helpers
  /**
   * Method equipment.
   *
   * @return CanonicalEquipment a scratchpad asset at revision 3
   */
  private function equipment(): CanonicalEquipment
  {
    return CanonicalEquipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      recordStatus: EquipmentRecordStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
      facilityId: null,
      type: 'fire_extinguisher',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: EquipmentStatus::IN_STOCK,
      commissionedAt: null,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
