<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\Inspection\EditInspection\EditInspectionCommand;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Domain\Exception\{InspectionAlreadyClosedException, InspectionAlreadySubmittedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Dto\Input\Inspection\EditInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Processor\Inspection\EditInspectionProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(EditInspectionProcessor::class)]
final class EditInspectionProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testProcessThrowsWhenRequestPayloadHasNoEditableFields(): void
  {
    $processor = $this->makeProcessor(
      requestStack: $this->makeRequestStack([]),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: new EditInspectionInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (EditInspectionCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::INSP_ID === $command->inspectionId
          && self::EQUIP_ID === $command->equipmentId
          && true === $command->hasEquipmentId
          && true === $command->hasNotes;
      }));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetInspectionQuery $query): bool {
        return self::ORG_ID === $query->organizationId && self::INSP_ID === $query->inspectionId;
      }))
      ->willReturn($this->makeGetInspectionResult());

    $input = new EditInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->notes = 'Updated note';

    $processor = new EditInspectionProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->makeSecurity(),
      requestStack: $this->makeRequestStack(['equipmentId' => self::EQUIP_ID, 'notes' => 'Updated note']),
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame(self::INSP_ID, $output->id);
    self::assertSame('Updated note', $output->notes);
  }

  #[Test]
  public function testProcessThrowsConflictWhenInspectionAlreadySubmitted(): void
  {
    $processor = $this->makeProcessor(
      commandException: InspectionAlreadySubmittedException::withId(self::INSP_ID),
      requestStack: $this->makeRequestStack(['notes' => 'Updated note']),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenInspectionMissing(): void
  {
    $processor = $this->makeProcessor(
      commandException: InspectionNotFoundException::withId(self::INSP_ID),
      requestStack: $this->makeRequestStack(['notes' => 'Updated note']),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsClosedFromMessengerException(): void
  {
    $processor = $this->makeProcessor(
      commandException: MessengerRuntimeException::wrap(InspectionAlreadyClosedException::withId(self::INSP_ID)),
      requestStack: $this->makeRequestStack(['notes' => 'Updated note']),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Patch(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  private function makeProcessor(?Throwable $commandException = null, ?RequestStack $requestStack = null): EditInspectionProcessor
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    if (null !== $commandException) {
      $commandBus->method('dispatch')->willThrowException($commandException);
    }

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->makeGetInspectionResult());

    return new EditInspectionProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->makeSecurity(),
      requestStack: $requestStack ?? $this->makeRequestStack(['notes' => 'Updated note']),
    );
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function makeRequestStack(array $payload): RequestStack
  {
    $request = new Request(content: json_encode($payload, JSON_THROW_ON_ERROR));
    $request->headers->set('CONTENT_TYPE', 'application/json');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function makeInput(): EditInspectionInput
  {
    $input = new EditInspectionInput();
    $input->notes = 'Updated note';

    return $input;
  }

  private function makeSecurity(): Security
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function makeGetInspectionResult(): GetInspectionResult
  {
    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    return new GetInspectionResult(
      inspectionId: self::INSP_ID,
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: null,
      result: 'pass',
      status: 'draft',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: self::USER_ID,
      inspectorOrganizationName: null,
      checklistId: null,
      notes: 'Updated note',
      signature: null,
      nonConformitiesCount: 0,
      createdAt: $now,
      updatedAt: $now,
    );
  }
}
