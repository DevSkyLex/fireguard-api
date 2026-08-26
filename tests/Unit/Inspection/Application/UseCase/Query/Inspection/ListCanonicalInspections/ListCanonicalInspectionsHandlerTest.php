<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\ListCanonicalInspections;

use DateTimeImmutable;
use Inspection\Application\Contract\Inspection\CanonicalInspectionReadView;
use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Inspection\ListCanonicalInspections\{ListCanonicalInspectionsHandler, ListCanonicalInspectionsQuery};
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListCanonicalInspectionsHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListCanonicalInspectionsHandler::class)]
final class ListCanonicalInspectionsHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string FIRST_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string SECOND_ID = '550e8400-e29b-41d4-a716-4466554400a2';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440025';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';
  // #endregion

  // #region Tests
  /**
   * Method testAnOrganizationScopedListDefaultsToPublished.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnOrganizationScopedListDefaultsToPublished(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->expects(self::once())->method('countReadByFilters')
      ->with(self::isInstanceOf(InspectionOrganizationId::class), null, null, 'published')
      ->willReturn(4);
    $inspections->expects(self::once())->method('findReadByFilters')
      ->with(self::anything(), null, null, 'published', 50, 0)
      ->willReturn([$this->view(self::FIRST_ID)]);

    $result = (new ListCanonicalInspectionsHandler($inspections, $this->counts([])))(
      new ListCanonicalInspectionsQuery(self::ORGANIZATION_ID),
    );

    self::assertSame(4, $result->total);
    self::assertCount(1, $result->views);
    self::assertSame(self::FIRST_ID, $result->views[0]->id);
  }

  /**
   * Method testAnInterventionScopedListDefaultsToDrafts.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInterventionScopedListDefaultsToDrafts(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->expects(self::once())->method('countReadByFilters')
      ->with(self::anything(), self::INTERVENTION_ID, null, 'draft')
      ->willReturn(0);
    $inspections->method('findReadByFilters')->willReturn([]);

    (new ListCanonicalInspectionsHandler($inspections, $this->counts([])))(
      new ListCanonicalInspectionsQuery(self::ORGANIZATION_ID, interventionId: self::INTERVENTION_ID),
    );
  }

  /**
   * Method testAnExplicitRecordStatusAndAnEquipmentFilterAreForwarded.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnExplicitRecordStatusAndAnEquipmentFilterAreForwarded(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('countReadByFilters')->willReturn(0);
    $inspections->expects(self::once())->method('findReadByFilters')
      ->with(self::anything(), self::INTERVENTION_ID, self::EQUIPMENT_ID, 'published', 50, 0)
      ->willReturn([]);

    (new ListCanonicalInspectionsHandler($inspections, $this->counts([])))(
      new ListCanonicalInspectionsQuery(
        self::ORGANIZATION_ID,
        interventionId: self::INTERVENTION_ID,
        equipmentId: self::EQUIPMENT_ID,
        recordStatus: 'published',
      ),
    );
  }

  /**
   * Method testTheNonConformityCountsComeFromOneGroupedQuery.
   *
   * The provider used to reach `$record->nonConformities->count()` per row —
   * an N+1. One call, for the whole page, and a row absent from the map means
   * zero rather than a missing key.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheNonConformityCountsComeFromOneGroupedQuery(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('countReadByFilters')->willReturn(2);
    $inspections->method('findReadByFilters')->willReturn([
      $this->view(self::FIRST_ID),
      $this->view(self::SECOND_ID),
    ]);

    $nonConformities = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformities->expects(self::once())->method('countsByInspectionIds')
      ->with([self::FIRST_ID, self::SECOND_ID])
      ->willReturn([self::FIRST_ID => 3]);

    $result = (new ListCanonicalInspectionsHandler($inspections, $nonConformities))(
      new ListCanonicalInspectionsQuery(self::ORGANIZATION_ID),
    );

    self::assertSame(3, $result->views[0]->nonConformitiesCount);
    self::assertSame(0, $result->views[1]->nonConformitiesCount);
  }

  /**
   * Method testAnEmptyPageAsksForNoCountsAtAll.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnEmptyPageAsksForNoCountsAtAll(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('countReadByFilters')->willReturn(12);
    $inspections->method('findReadByFilters')->willReturn([]);
    $nonConformities = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformities->expects(self::never())->method('countsByInspectionIds');

    $result = (new ListCanonicalInspectionsHandler($inspections, $nonConformities))(
      new ListCanonicalInspectionsQuery(self::ORGANIZATION_ID, page: 99),
    );

    self::assertSame([], $result->views);
    self::assertSame(12, $result->total);
  }

  /**
   * Method testThePageOffsetIsDerivedFromTheOneBasedPageNumber.
   *
   * @return void no return value
   */
  #[Test]
  public function testThePageOffsetIsDerivedFromTheOneBasedPageNumber(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('countReadByFilters')->willReturn(30);
    $inspections->expects(self::once())->method('findReadByFilters')
      ->with(self::anything(), null, null, 'published', 10, 20)
      ->willReturn([]);

    $result = (new ListCanonicalInspectionsHandler($inspections, $this->counts([])))(
      new ListCanonicalInspectionsQuery(self::ORGANIZATION_ID, page: 3, itemsPerPage: 10),
    );

    self::assertSame(3, $result->page);
    self::assertSame(10, $result->itemsPerPage);
  }
  // #endregion

  // #region Helpers
  /**
   * Method counts.
   *
   * @param array<string, int> $counts the grouped counts to answer with
   *
   * @return NonConformityRepositoryPort the stubbed repository
   */
  private function counts(array $counts): NonConformityRepositoryPort
  {
    $nonConformities = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformities->method('countsByInspectionIds')->willReturn($counts);

    return $nonConformities;
  }

  /**
   * Method view.
   *
   * @param string $id the inspection identifier
   *
   * @return CanonicalInspectionReadView a published inspection
   */
  private function view(string $id): CanonicalInspectionReadView
  {
    $now = new DateTimeImmutable('2026-08-26T10:00:00+00:00');

    return new CanonicalInspectionReadView(
      id: $id,
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
