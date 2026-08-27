<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\CanonicalInspectionRepositoryPort;
use Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection\{GetCanonicalInspectionHandler, GetCanonicalInspectionQuery};
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionRecordStatus,
  InspectionResult,
  InspectionStatus
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCanonicalInspectionHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCanonicalInspectionHandler::class)]
final class GetCanonicalInspectionHandlerTest extends TestCase
{
  // #region Constants
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440025';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';
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
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection());

    $result = (new GetCanonicalInspectionHandler($inspections))(
      new GetCanonicalInspectionQuery(self::INSPECTION_ID),
    );

    self::assertNotNull($result->view);
    self::assertSame(self::INSPECTION_ID, $result->view->id);
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
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn(null);

    self::assertNull(
      (new GetCanonicalInspectionHandler($inspections))(new GetCanonicalInspectionQuery(self::INSPECTION_ID))->view,
    );
  }

  /**
   * Method testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing.
   *
   * This is what keeps a canonical mutation on a garbage id a 404 now that
   * the identifier is a validated UUID value object. The empty-string case
   * is the one the processor produces for a non-string URI variable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->expects(self::never())->method('findById');
    $handler = new GetCanonicalInspectionHandler($inspections);

    self::assertNull($handler(new GetCanonicalInspectionQuery('not-a-uuid'))->view);
    self::assertNull($handler(new GetCanonicalInspectionQuery(''))->view);
  }
  // #endregion

  // #region Helpers
  /**
   * Method inspection.
   *
   * @return CanonicalInspection a scratchpad inspection at revision 3
   */
  private function inspection(): CanonicalInspection
  {
    return CanonicalInspection::reconstitute(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      recordStatus: InspectionRecordStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
      status: InspectionStatus::DRAFT,
      result: InspectionResult::PASS,
      notes: null,
      signature: null,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
