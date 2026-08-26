<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionResponseRepositoryPort, InterventionScopePort};
use Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse\{DeleteInspectionResponseCommand, DeleteInspectionResponseHandler};
use Inspection\Domain\Exception\{InspectionResponseConflictException, InspectionResponseNotFoundException, InspectionResponseRevisionMismatchException};
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId, InspectionResponseStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test DeleteInspectionResponseHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteInspectionResponseHandler::class)]
final class DeleteInspectionResponseHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';
  // #endregion

  // #region Tests
  /**
   * Method testDeletingADraftTouchesItsIntervention.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeletingADraftTouchesItsIntervention(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn($this->stored());
    $responses->expects(self::once())->method('delete');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);

    $result = $this->handler($responses, $interventions)(
      new DeleteInspectionResponseCommand(self::RESPONSE_ID, 2),
    );

    self::assertSame(self::RESPONSE_ID, $result->responseId);
    self::assertSame(self::INSPECTION_ID, $result->inspectionId);
  }

  /**
   * Method testAnUnknownResponseIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownResponseIsNotFound(): void
  {
    $responses = $this->createStub(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn(null);

    $this->expectException(InspectionResponseNotFoundException::class);

    $this->handler($responses)(new DeleteInspectionResponseCommand(self::RESPONSE_ID, 1));
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeTheDraftCheck.
   *
   * The order matters: a stale `If-Match` on a published response answers
   * 412, not the 409 the immutability rule would give.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeTheDraftCheck(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn($this->stored(InspectionResponseStatus::PUBLISHED));
    $responses->expects(self::never())->method('delete');

    $this->expectException(InspectionResponseRevisionMismatchException::class);

    $this->handler($responses)(new DeleteInspectionResponseCommand(self::RESPONSE_ID, 1));
  }

  /**
   * Method testAPublishedResponseCannotBeDeleted.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPublishedResponseCannotBeDeleted(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn($this->stored(InspectionResponseStatus::PUBLISHED));
    $responses->expects(self::never())->method('delete');

    $this->expectException(InspectionResponseConflictException::class);
    $this->expectExceptionMessage('Published inspection responses cannot be deleted.');

    $this->handler($responses)(new DeleteInspectionResponseCommand(self::RESPONSE_ID, 2));
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @param ?InspectionResponseRepositoryPort $responses the response repository
   * @param ?InterventionScopePort $interventions the intervention scope port
   *
   * @return DeleteInspectionResponseHandler the handler under test
   */
  private function handler(
    ?InspectionResponseRepositoryPort $responses = null,
    ?InterventionScopePort $interventions = null,
  ): DeleteInspectionResponseHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new DeleteInspectionResponseHandler(
      responses: $responses ?? $this->createStub(InspectionResponseRepositoryPort::class),
      interventions: $interventions ?? $this->createStub(InterventionScopePort::class),
      transactionManager: $transactionManager,
    );
  }

  /**
   * Method stored.
   *
   * @param InspectionResponseStatus $status the stored lifecycle status
   *
   * @return InspectionResponse a response at revision 2
   */
  private function stored(InspectionResponseStatus $status = InspectionResponseStatus::DRAFT): InspectionResponse
  {
    $now = new DateTimeImmutable('2026-08-26T10:00:00+00:00');

    return InspectionResponse::reconstitute(
      id: InspectionResponseId::fromString(self::RESPONSE_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      interventionId: self::INTERVENTION_ID,
      clientId: null,
      status: $status,
      revision: 2,
      itemKey: 'pressure',
      value: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }
  // #endregion
}
