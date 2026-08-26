<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Response\ListInspectionResponses;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionResponseRepositoryPort;
use Inspection\Application\UseCase\Query\Response\ListInspectionResponses\{ListInspectionResponsesHandler, ListInspectionResponsesQuery};
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId, InspectionResponseStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListInspectionResponsesHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListInspectionResponsesHandler::class)]
final class ListInspectionResponsesHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';
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
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->expects(self::once())->method('countByFilters')
      ->with(self::isInstanceOf(InspectionOrganizationId::class), null, null, 'published')
      ->willReturn(3);
    $responses->expects(self::once())->method('findByFilters')
      ->with(self::isInstanceOf(InspectionOrganizationId::class), null, null, 'published', 50, 0)
      ->willReturn([$this->response()]);

    $result = (new ListInspectionResponsesHandler($responses))(
      new ListInspectionResponsesQuery(self::ORGANIZATION_ID),
    );

    self::assertSame(3, $result->total);
    self::assertSame(1, $result->page);
    self::assertSame(50, $result->itemsPerPage);
    self::assertCount(1, $result->views);
    self::assertSame(self::RESPONSE_ID, $result->views[0]->id);
  }

  /**
   * Method testAnInterventionScopedListDefaultsToDrafts.
   *
   * The default is the endpoint's contract: a caller scoped to an
   * intervention is looking at what a field client is preparing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInterventionScopedListDefaultsToDrafts(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->expects(self::once())->method('countByFilters')
      ->with(self::anything(), self::INTERVENTION_ID, null, 'draft')
      ->willReturn(1);
    $responses->method('findByFilters')->willReturn([]);

    (new ListInspectionResponsesHandler($responses))(
      new ListInspectionResponsesQuery(self::ORGANIZATION_ID, interventionId: self::INTERVENTION_ID),
    );
  }

  /**
   * Method testAnExplicitRecordStatusWinsOverTheDefault.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnExplicitRecordStatusWinsOverTheDefault(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->expects(self::once())->method('countByFilters')
      ->with(self::anything(), self::INTERVENTION_ID, null, 'published')
      ->willReturn(0);
    $responses->method('findByFilters')->willReturn([]);

    (new ListInspectionResponsesHandler($responses))(
      new ListInspectionResponsesQuery(
        self::ORGANIZATION_ID,
        interventionId: self::INTERVENTION_ID,
        recordStatus: 'published',
      ),
    );
  }

  /**
   * Method testThePageOffsetIsDerivedFromTheOneBasedPageNumber.
   *
   * Off-by-one here is a listing that silently skips or repeats a row.
   *
   * @return void no return value
   */
  #[Test]
  public function testThePageOffsetIsDerivedFromTheOneBasedPageNumber(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('countByFilters')->willReturn(30);
    $responses->expects(self::once())->method('findByFilters')
      ->with(self::anything(), null, self::INSPECTION_ID, 'published', 10, 20)
      ->willReturn([]);

    $result = (new ListInspectionResponsesHandler($responses))(
      new ListInspectionResponsesQuery(
        self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        page: 3,
        itemsPerPage: 10,
      ),
    );

    self::assertSame(3, $result->page);
    self::assertSame(10, $result->itemsPerPage);
    self::assertSame(30, $result->total);
  }

  /**
   * Method testAnEmptyPageStillCarriesItsTotal.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnEmptyPageStillCarriesItsTotal(): void
  {
    $responses = $this->createStub(InspectionResponseRepositoryPort::class);
    $responses->method('countByFilters')->willReturn(12);
    $responses->method('findByFilters')->willReturn([]);

    $result = (new ListInspectionResponsesHandler($responses))(
      new ListInspectionResponsesQuery(self::ORGANIZATION_ID, page: 99),
    );

    self::assertSame([], $result->views);
    self::assertSame(12, $result->total);
  }
  // #endregion

  // #region Helpers
  /**
   * Method response.
   *
   * @return InspectionResponse a draft response
   */
  private function response(): InspectionResponse
  {
    $now = new DateTimeImmutable('2026-08-26T10:00:00+00:00');

    return InspectionResponse::reconstitute(
      id: InspectionResponseId::fromString(self::RESPONSE_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      interventionId: null,
      clientId: null,
      status: InspectionResponseStatus::PUBLISHED,
      revision: 1,
      itemKey: 'pressure',
      value: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }
  // #endregion
}
