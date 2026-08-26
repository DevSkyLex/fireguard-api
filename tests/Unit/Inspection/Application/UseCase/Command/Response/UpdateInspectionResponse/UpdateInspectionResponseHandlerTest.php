<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionResponseRepositoryPort, InterventionScopePort};
use Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse\{UpdateInspectionResponseCommand, UpdateInspectionResponseHandler};
use Inspection\Domain\Exception\{InspectionResponseConflictException, InspectionResponseNotFoundException, InspectionRevisionMismatchException};
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, InspectionResponseId, InspectionResponseStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test UpdateInspectionResponseHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateInspectionResponseHandler::class)]
final class UpdateInspectionResponseHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';
  // #endregion

  // #region Tests
  /**
   * Method testUpdatingADraftBumpsTheRevisionAndTouchesTheIntervention.
   *
   * @return void no return value
   */
  #[Test]
  public function testUpdatingADraftBumpsTheRevisionAndTouchesTheIntervention(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn($this->stored());
    $responses->expects(self::once())->method('save');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);

    $result = $this->handler($responses, $interventions)(
      new UpdateInspectionResponseCommand(
        responseId: self::RESPONSE_ID,
        expectedRevision: 2,
        value: ['ok' => false],
      ),
    );

    self::assertSame(3, $result->view->revision);
    self::assertSame(['ok' => false], $result->view->value);
    self::assertSame('draft', $result->view->recordStatus);
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
    $this->expectExceptionMessage('Inspection response not found.');

    $this->handler($responses)(new UpdateInspectionResponseCommand(self::RESPONSE_ID, 1));
  }

  /**
   * Method testAMalformedIdentifierIsNotFoundRatherThanInvalid.
   *
   * `GET`, `PATCH` and `DELETE` answered 404 for an unparseable id before the
   * identifier became a value object. Only the creation routes narrowed to
   * 400; these must not follow.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierIsNotFoundRatherThanInvalid(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->expects(self::never())->method('findById');

    $this->expectException(InspectionResponseNotFoundException::class);

    $this->handler($responses)(new UpdateInspectionResponseCommand('not-a-uuid', 1));
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeTheValueIsTouched.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeTheValueIsTouched(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn($this->stored());
    $responses->expects(self::never())->method('save');

    $this->expectException(InspectionRevisionMismatchException::class);
    $this->expectExceptionMessage('The resource revision is stale.');

    $this->handler($responses)(new UpdateInspectionResponseCommand(self::RESPONSE_ID, 1));
  }

  /**
   * Method testAPublishedResponseIsImmutable.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPublishedResponseIsImmutable(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('findById')->willReturn($this->stored(InspectionResponseStatus::PUBLISHED));
    $responses->expects(self::never())->method('save');

    $this->expectException(InspectionResponseConflictException::class);
    $this->expectExceptionMessage('Published inspection responses are immutable.');

    $this->handler($responses)(new UpdateInspectionResponseCommand(self::RESPONSE_ID, 2));
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @param ?InspectionResponseRepositoryPort $responses the response repository
   * @param ?InterventionScopePort $interventions the intervention scope port
   *
   * @return UpdateInspectionResponseHandler the handler under test
   */
  private function handler(
    ?InspectionResponseRepositoryPort $responses = null,
    ?InterventionScopePort $interventions = null,
  ): UpdateInspectionResponseHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new UpdateInspectionResponseHandler(
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
      value: ['ok' => true],
      createdAt: $now,
      updatedAt: $now,
    );
  }
  // #endregion
}
