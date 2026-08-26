<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Response\CreateInspectionResponse;

use Inspection\Application\Contract\Inspection\InspectionScope;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, InspectionResponseRepositoryPort, InterventionScopePort};
use Inspection\Application\UseCase\Command\Response\CreateInspectionResponse\{CreateInspectionResponseCommand, CreateInspectionResponseHandler};
use Inspection\Domain\Exception\{InspectionResponseClientIdAlreadyExistsException, InspectionResponseConflictException};
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\InspectionResponseId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test CreateInspectionResponseHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateInspectionResponseHandler::class)]
final class CreateInspectionResponseHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554400a1';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';
  // #endregion

  // #region Tests
  /**
   * Method testCreatesAPublishedResponseOutsideAnyIntervention.
   *
   * @return void no return value
   */
  #[Test]
  public function testCreatesAPublishedResponseOutsideAnyIntervention(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->expects(self::once())->method('save');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(null);

    $result = $this->handler($responses, $this->inspections(), $interventions)(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: '  pressure  ',
        value: ['ok' => true],
      ),
    );

    self::assertSame(self::RESPONSE_ID, $result->view->id);
    self::assertSame(self::ORGANIZATION_ID, $result->view->organizationId);
    self::assertSame(self::INSPECTION_ID, $result->view->inspectionId);
    self::assertNull($result->view->interventionId);
    self::assertSame('published', $result->view->recordStatus);
    self::assertSame(1, $result->view->revision);
    self::assertSame('pressure', $result->view->itemKey);
    self::assertSame(['ok' => true], $result->view->value);
  }

  /**
   * Method testAnInterventionScopedResponseStartsAsADraftAndTouchesIt.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInterventionScopedResponseStartsAsADraftAndTouchesIt(): void
  {
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->method('organizationIdOf')->willReturn(self::ORGANIZATION_ID);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);

    $result = $this->handler(
      inspections: $this->inspections(self::INTERVENTION_ID),
      interventions: $interventions,
    )(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: 'pressure',
        interventionId: self::INTERVENTION_ID,
      ),
    );

    self::assertSame('draft', $result->view->recordStatus);
    self::assertSame(self::INTERVENTION_ID, $result->view->interventionId);
  }

  /**
   * Method testAKnownClientIdentifierIsRejectedBeforeAnyScopeIsRead.
   *
   * The replay guard fires first on purpose: a client re-sending a creation
   * it already made must get the same answer whether or not the inspection
   * has moved since.
   *
   * @return void no return value
   */
  #[Test]
  public function testAKnownClientIdentifierIsRejectedBeforeAnyScopeIsRead(): void
  {
    $responses = $this->createMock(InspectionResponseRepositoryPort::class);
    $responses->method('existsByClientId')->willReturn(true);
    $responses->expects(self::never())->method('save');
    $inspections = $this->createMock(InspectionRepositoryPort::class);
    $inspections->expects(self::never())->method('findScope');

    $this->expectException(InspectionResponseClientIdAlreadyExistsException::class);

    $this->handler($responses, $inspections)(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: 'pressure',
        clientId: self::RESPONSE_ID,
      ),
    );
  }

  /**
   * Method testAnUnknownInspectionIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownInspectionIsAConflict(): void
  {
    $inspections = $this->createStub(InspectionRepositoryPort::class);
    $inspections->method('findScope')->willReturn(null);

    $this->expectException(InspectionResponseConflictException::class);
    $this->expectExceptionMessage('Inspection must belong to the organization.');

    $this->handler(inspections: $inspections)(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: 'pressure',
      ),
    );
  }

  /**
   * Method testAnInspectionOwnedByAnotherOrganizationIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInspectionOwnedByAnotherOrganizationIsAConflict(): void
  {
    $this->expectException(InspectionResponseConflictException::class);
    $this->expectExceptionMessage('Inspection must belong to the organization.');

    $this->handler(inspections: $this->inspections(organizationId: self::OTHER_ORGANIZATION_ID))(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: 'pressure',
      ),
    );
  }

  /**
   * Method testAnInspectionPreparedByAnotherInterventionIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInspectionPreparedByAnotherInterventionIsAConflict(): void
  {
    $this->expectException(InspectionResponseConflictException::class);
    $this->expectExceptionMessage('Inspection and response must belong to the same intervention.');

    $this->handler(inspections: $this->inspections('another-intervention'))(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: 'pressure',
        interventionId: self::INTERVENTION_ID,
      ),
    );
  }

  /**
   * Method testAnInterventionOwnedByAnotherOrganizationIsAConflict.
   *
   * An absent intervention lands here too — `organizationIdOf()` answers
   * null, which can never equal the organization asked for.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnInterventionOwnedByAnotherOrganizationIsAConflict(): void
  {
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->method('organizationIdOf')->willReturn(self::OTHER_ORGANIZATION_ID);
    $interventions->expects(self::never())->method('touchDraft');

    $this->expectException(InspectionResponseConflictException::class);
    $this->expectExceptionMessage('Intervention must belong to the organization.');

    $this->handler(
      inspections: $this->inspections(self::INTERVENTION_ID),
      interventions: $interventions,
    )(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: 'pressure',
        interventionId: self::INTERVENTION_ID,
      ),
    );
  }

  /**
   * Method testAClientChosenIdentifierIsUsedInsteadOfAGeneratedOne.
   *
   * @return void no return value
   */
  #[Test]
  public function testAClientChosenIdentifierIsUsedInsteadOfAGeneratedOne(): void
  {
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $saved = null;
    $responses = $this->createStub(InspectionResponseRepositoryPort::class);
    $responses->method('save')->willReturnCallback(
      static function (InspectionResponse $response) use (&$saved): void {
        $saved = $response;
      },
    );

    $result = $this->handler($responses, uuidFactory: $uuidFactory)(
      new CreateInspectionResponseCommand(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        itemKey: 'pressure',
        resourceId: self::RESPONSE_ID,
        clientId: self::RESPONSE_ID,
      ),
    );

    self::assertInstanceOf(InspectionResponse::class, $saved);
    self::assertSame(self::RESPONSE_ID, (string) $saved->id());
    self::assertSame(self::RESPONSE_ID, $result->view->clientId);
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @param ?InspectionResponseRepositoryPort $responses the response repository
   * @param ?InspectionRepositoryPort $inspections the inspection repository
   * @param ?InterventionScopePort $interventions the intervention scope port
   * @param ?UuidFactory $uuidFactory the uuid factory
   *
   * @return CreateInspectionResponseHandler the handler under test
   */
  private function handler(
    ?InspectionResponseRepositoryPort $responses = null,
    ?InspectionRepositoryPort $inspections = null,
    ?InterventionScopePort $interventions = null,
    ?UuidFactory $uuidFactory = null,
  ): CreateInspectionResponseHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    if (null === $uuidFactory) {
      $uuidFactory = $this->createStub(UuidFactory::class);
      $uuidFactory->method('create')->willReturn(InspectionResponseId::fromString(self::RESPONSE_ID));
    }

    return new CreateInspectionResponseHandler(
      responses: $responses ?? $this->createStub(InspectionResponseRepositoryPort::class),
      inspections: $inspections ?? $this->inspections(),
      interventions: $interventions ?? $this->createStub(InterventionScopePort::class),
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );
  }

  /**
   * Method inspections.
   *
   * @param ?string $interventionId the intervention that prepared the inspection
   * @param string $organizationId the inspection's owning organization
   *
   * @return InspectionRepositoryPort a repository answering one scope
   */
  private function inspections(?string $interventionId = null, string $organizationId = self::ORGANIZATION_ID): InspectionRepositoryPort
  {
    $inspections = $this->createStub(InspectionRepositoryPort::class);
    $inspections->method('findScope')->willReturn(new InspectionScope(
      inspectionId: self::INSPECTION_ID,
      organizationId: $organizationId,
      interventionId: $interventionId,
    ));

    return $inspections;
  }
  // #endregion
}
