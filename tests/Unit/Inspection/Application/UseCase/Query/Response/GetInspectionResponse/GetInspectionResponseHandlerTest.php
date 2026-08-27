<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Response\GetInspectionResponse;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionResponseRepositoryPort;
use Inspection\Application\UseCase\Query\Response\GetInspectionResponse\{GetInspectionResponseHandler, GetInspectionResponseQuery};
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId, InspectionResponseStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetInspectionResponseHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetInspectionResponseHandler::class)]
final class GetInspectionResponseHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';
  // #endregion

  // #region Tests
  /**
   * Method testFlattensTheStoredResponse.
   *
   * @return void no return value
   */
  #[Test]
  public function testFlattensTheStoredResponse(): void
  {
    $responses = $this->createStub(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn($this->stored());

    $result = (new GetInspectionResponseHandler($responses))(new GetInspectionResponseQuery(self::RESPONSE_ID));

    self::assertNotNull($result->view);
    self::assertSame(self::RESPONSE_ID, $result->view->id);
    self::assertSame(self::ORGANIZATION_ID, $result->view->organizationId);
    self::assertSame(7, $result->view->revision);
    self::assertSame('draft', $result->view->recordStatus);
  }

  /**
   * Method testAnUnknownIdentifierAnswersAnEmptyView.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownIdentifierAnswersAnEmptyView(): void
  {
    $responses = $this->createStub(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn(null);

    self::assertNull(
      (new GetInspectionResponseHandler($responses))(new GetInspectionResponseQuery(self::RESPONSE_ID))->view,
    );
  }

  /**
   * Method testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing.
   *
   * This is what keeps `GET /inspection-responses/garbage` a 404 now that the
   * identifier is a validated UUID value object.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierAnswersAnEmptyViewRatherThanThrowing(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->expects(self::never())->method('findById');

    self::assertNull(
      (new GetInspectionResponseHandler($responses))(new GetInspectionResponseQuery('not-a-uuid'))->view,
    );
  }
  // #endregion

  // #region Helpers
  /**
   * Method stored.
   *
   * @return InspectionResponse a draft response at revision 7
   */
  private function stored(): InspectionResponse
  {
    $now = new DateTimeImmutable('2026-08-26T10:00:00+00:00');

    return InspectionResponse::reconstitute(
      id: InspectionResponseId::fromString(self::RESPONSE_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      interventionId: null,
      clientId: null,
      status: InspectionResponseStatus::DRAFT,
      revision: 7,
      itemKey: 'pressure',
      value: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }
  // #endregion
}
