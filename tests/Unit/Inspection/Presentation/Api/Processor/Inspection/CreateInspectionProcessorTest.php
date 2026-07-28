<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{CreateInspectionCommand, CreateInspectionResult};
use Inspection\Presentation\Api\Dto\Input\Inspection\CreateInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Processor\Inspection\CreateInspectionProcessor;
use Intervention\Application\Contract\Resource\{InterventionAssignmentContext, InterventionResourceAssignment};
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(CreateInspectionProcessor::class)]
final class CreateInspectionProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string CLIENT_ID = '550e8400-e29b-41d4-a716-446655440004';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440005';

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new CreateInspectionInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: new CreateInspectionInput(),
      operation: new Post(),
      uriVariables: [],
    );
  }

  #[Test]
  public function testProcessThrowsWhenPermissionDenied(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new CreateInspectionInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateInspectionCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::EQUIP_ID === $command->equipmentId
          && 'pass' === $command->result
          && 'user' === $command->inspectorType;
      }))
      ->willReturn(new CreateInspectionResult(
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
        notes: null,
        signature: null,
        createdAt: $now,
        updatedAt: $now,
      ));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';
    $input->inspectorUserId = self::USER_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame(self::INSP_ID, $output->id);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame('draft', $output->status);
    self::assertSame('pass', $output->result);
    self::assertSame(0, $output->nonConformitiesCount);
  }

  #[Test]
  public function testProcessThrowsBadRequestOnInvalidArgument(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(new InvalidArgumentException('Invalid input.'));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid input.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessUnwrapsInvalidArgumentFromMessengerException(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(
        new InvalidArgumentException('Wrapped invalid argument.'),
      ));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessDefaultsInspectorUserIdToAuthenticatedUserForUserType(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateInspectionCommand $command): bool {
        return 'user' === $command->inspectorType
          && self::USER_ID === $command->inspectorUserId;
      }))
      ->willReturn(new CreateInspectionResult(
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
        notes: null,
        signature: null,
        createdAt: $now,
        updatedAt: $now,
      ));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';
    // inspectorUserId intentionally left null: the processor must attribute the
    // inspection to the authenticated user for the USER inspector type.

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame(self::INSP_ID, $output->id);
  }

  #[Test]
  public function testProcessMapsWrappedQuotaExceededToHttp409(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateInspectionCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        result: 'pass',
        performedAt: '2026-01-15T10:00:00+00:00',
        inspectorType: 'user',
        inspectorName: 'John Doe',
        inspectorUserId: self::USER_ID,
      )),
      [OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::INSPECTIONS, 100)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';
    $input->inspectorUserId = self::USER_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Transport is unavailable.')),
    );

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(data: $this->makeInput(), operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessAdoptsTheUriIdentifierAsTheClientIdAndAssertsCreateOnly(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (CreateInspectionCommand $command): bool => self::CLIENT_ID === $command->resourceId))
      ->willReturn($this->makeResult());

    $requestStack = new RequestStack();
    $request = Request::create('/api/inspections/' . self::CLIENT_ID, 'PUT');
    $request->headers->set('If-None-Match', '*');
    $requestStack->push($request);

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('clientIdExists')->willReturn(false);
    $gateway->method('resourceExists')->willReturn(true);
    $gateway->method('assign')->willReturn(new InterventionResourceAssignment(null, 'published', 1));

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
      creationPreconditionGuard: new CreationPreconditionGuard($requestStack),
    );

    $output = $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'id' => self::CLIENT_ID],
    );

    self::assertSame('published', $output->recordStatus);
    self::assertSame(1, $output->revision);
  }

  #[Test]
  public function testProcessRejectsAnOfflineCreateWhoseClientIdIsAlreadyTaken(): void
  {
    $requestStack = new RequestStack();
    $request = Request::create('/api/inspections/' . self::CLIENT_ID, 'PUT');
    $request->headers->set('If-None-Match', '*');
    $requestStack->push($request);

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('clientIdExists')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
      creationPreconditionGuard: new CreationPreconditionGuard($requestStack),
    );

    $this->expectException(ClientResourceAlreadyExistsHttpException::class);

    $processor->process(
      data: $this->makeInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'id' => self::CLIENT_ID],
    );
  }

  #[Test]
  public function testProcessWrapsAnInterventionScopedCreationInATransactionAndAttachesIt(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->willReturn($this->makeResult());

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'draft'),
    );
    $gateway->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'draft'),
    );
    $gateway->method('resourceExists')->willReturn(true);
    $gateway->method('assign')->willReturn(new InterventionResourceAssignment(self::INTERVENTION_ID, 'draft', 1));

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.plan')
      ->willReturn(true);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );

    $input = $this->makeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $authorization,
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
      entityManager: $entityManager,
    );

    $output = $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);

    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $output->intervention);
    self::assertSame('draft', $output->recordStatus);
  }

  #[Test]
  public function testProcessReportsAnUnknownInterventionAsNotFound(): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionMutationContext')->willReturn(null);

    $input = $this->makeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessReportsAnImmutableInterventionAsConflict(): void
  {
    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'submitted'),
    );

    $input = $this->makeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      outputMapper: $this->createOutputMapper(),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessReportsAVanishedInspectionDuringAttachmentAsNotFound(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn($this->makeResult());

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('resourceExists')->willReturn(false);

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(data: $this->makeInput(), operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessReportsACrossOrganizationAttachmentAsConflict(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn($this->makeResult());

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORG_ID, 'draft'),
    );
    $gateway->method('resourceExists')->willReturn(true);
    // The intervention belongs to a different organization than the inspection.
    $gateway->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, '550e8400-e29b-41d4-a716-4466554400ff', 'draft'),
    );

    $input = $this->makeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $processor = new CreateInspectionProcessor(
      commandBus: $commandBus,
      outputMapper: $this->createOutputMapper(),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  private function makeInput(): CreateInspectionInput
  {
    $input = new CreateInspectionInput();
    $input->equipmentId = self::EQUIP_ID;
    $input->result = 'pass';
    $input->performedAt = '2026-01-15T10:00:00+00:00';
    $input->inspectorType = 'user';
    $input->inspectorName = 'John Doe';
    $input->inspectorUserId = self::USER_ID;

    return $input;
  }

  private function makeResult(): CreateInspectionResult
  {
    $now = new DateTimeImmutable('2026-01-15T11:00:00+00:00');

    return new CreateInspectionResult(
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
      notes: null,
      signature: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    return $security;
  }

  private function permissiveAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return $authorization;
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function createOutputMapper(): InspectionOutputFactory
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('User lookup unavailable.'));

    return new InspectionOutputFactory($queryBus);
  }
}
