<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection;

use DateTimeImmutable;
use Inspection\Application\Contract\Inspection\CanonicalInspectionReadView;
use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection\{ReadCanonicalInspectionHandler, ReadCanonicalInspectionQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ReadCanonicalInspectionHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ReadCanonicalInspectionHandler::class)]
final class ReadCanonicalInspectionHandlerTest extends TestCase
{
  // #region Constants
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440025';
  // #endregion

  // #region Tests
  /**
   * Method testTheCountComesFromTheSameGroupedQueryTheListingUses.
   *
   * The item and the list must never report a different number for the same
   * row, which is what a lazy association per read would eventually produce.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheCountComesFromTheSameGroupedQueryTheListingUses(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findReadById')->willReturn($this->view());
    $nonConformities = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformities->expects(self::once())->method('countsByInspectionIds')
      ->with([self::INSPECTION_ID])
      ->willReturn([self::INSPECTION_ID => 5]);

    $result = (new ReadCanonicalInspectionHandler($inspections, $nonConformities))(
      new ReadCanonicalInspectionQuery(self::INSPECTION_ID),
    );

    self::assertNotNull($result->view);
    self::assertSame(5, $result->view->nonConformitiesCount);
    self::assertSame(self::ORGANIZATION_ID, $result->view->organizationId);
  }

  /**
   * Method testAnInspectionWithNoDeficienciesCountsZero.
   *
   * A row absent from the grouped map means zero, not a missing key.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInspectionWithNoDeficienciesCountsZero(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findReadById')->willReturn($this->view());
    $nonConformities = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformities->method('countsByInspectionIds')->willReturn([]);

    $result = (new ReadCanonicalInspectionHandler($inspections, $nonConformities))(
      new ReadCanonicalInspectionQuery(self::INSPECTION_ID),
    );

    self::assertNotNull($result->view);
    self::assertSame(0, $result->view->nonConformitiesCount);
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
    $inspections->method('findReadById')->willReturn(null);
    $nonConformities = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformities->expects(self::never())->method('countsByInspectionIds');

    self::assertNull(
      (new ReadCanonicalInspectionHandler($inspections, $nonConformities))(
        new ReadCanonicalInspectionQuery(self::INSPECTION_ID),
      )->view,
    );
  }

  /**
   * Method testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->expects(self::never())->method('findReadById');

    self::assertNull(
      (new ReadCanonicalInspectionHandler($inspections, $this->createStub(NonConformityRepositoryPort::class)))(
        new ReadCanonicalInspectionQuery('not-a-uuid'),
      )->view,
    );
  }
  // #endregion

  // #region Helpers
  /**
   * Method view.
   *
   * @return CanonicalInspectionReadView a published inspection with a zero count
   */
  private function view(): CanonicalInspectionReadView
  {
    $now = new DateTimeImmutable('2026-08-26T10:00:00+00:00');

    return new CanonicalInspectionReadView(
      id: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      interventionId: null,
      recordStatus: 'published',
      revision: 1,
      equipmentId: self::EQUIPMENT_ID,
      facilityId: null,
      result: 'pass',
      status: 'submitted',
      performedAt: $now,
      inspectorType: 'external',
      inspectorUserId: null,
      inspectorName: 'Inspector',
      inspectorOrganizationName: null,
      checklistId: null,
      notes: null,
      signature: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }
  // #endregion
}
