<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\{AssignToFacilityCommand, AssignToFacilityResult};
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Presentation\Api\Dto\Input\Equipment\CreateEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\CreateEquipmentProcessor;
use Intervention\Application\Contract\Resource\{InterventionAssignmentContext, InterventionResourceAssignment};
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(CreateEquipmentProcessor::class)]
final class CreateEquipmentProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441150';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441151';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655441152';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655441153';

  private const string CLIENT_ID = '550e8400-e29b-41d4-a716-446655441154';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441155';

  #[Test]
  public function testProcessMapsWrappedSerialNumberConflictToHttp409(): void
  {
    $input = new CreateEquipmentInput();
    $input->type = 'fire_extinguisher';
    $input->serialNumber = 'EXT-2026-001';

    $organizationId = '550e8400-e29b-41d4-a716-446655441100';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441101');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $domainException = EquipmentSerialNumberAlreadyExistsException::withSerialNumber('EXT-2026-001');
    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateEquipmentCommand(
        organizationId: $organizationId,
        type: 'fire_extinguisher',
        serialNumber: 'EXT-2026-001',
      )),
      [$domainException],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Serial number "EXT-2026-001" already exists in this organization.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => $organizationId],
    );
  }

  #[Test]
  public function testProcessMapsWrappedQuotaExceededToHttp409(): void
  {
    $input = new CreateEquipmentInput();
    $input->type = 'fire_extinguisher';

    $organizationId = '550e8400-e29b-41d4-a716-446655441110';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441111');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateEquipmentCommand(organizationId: $organizationId, type: 'fire_extinguisher')),
      [OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::EQUIPMENT->value, 50)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => $organizationId],
    );
  }

  #[Test]
  public function testProcessReturnsEquipmentOutputOnSuccess(): void
  {
    $input = new CreateEquipmentInput();
    $input->type = 'smoke_detector';
    $input->brand = 'Ei Electronics';

    $organizationId = '550e8400-e29b-41d4-a716-446655441102';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441103';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441104');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new CreateEquipmentResult(
        equipmentId: $equipmentId,
        organizationId: $organizationId,
        facilityId: null,
        type: 'smoke_detector',
        subType: null,
        brand: 'Ei Electronics',
        model: null,
        serialNumber: null,
        locationLabel: null,
        status: 'in_stock',
        installedAt: null,
        commissionedAt: null,
        tags: [],
        createdAt: $now,
        updatedAt: $now,
      ));

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame($equipmentId, $output->id);
    self::assertSame('smoke_detector', $output->type);
    self::assertSame('Ei Electronics', $output->brand);
    self::assertSame('in_stock', $output->status);
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateEquipmentProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(data: $this->makeInput(), operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->processor()->process(data: $this->makeInput(), operation: new Post(), uriVariables: []);
  }

  #[Test]
  public function testProcessThrowsWhenPermissionDenied(): void
  {
    $this->expectException(AccessDeniedHttpException::class);

    $this->dispatch($this->processor(decision: OrganizationAccessDecision::MISSING_PERMISSION));
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationIsOutsideCallersScope(): void
  {
    // Not AccessDeniedHttpException: a 403 for a caller outside the organization's
    // scope would confirm the record exists across an organization boundary.
    try {
      $this->dispatch($this->processor(decision: OrganizationAccessDecision::OUTSIDE_SCOPE));
      self::fail('Expected NotFoundHttpException to be thrown.');
    } catch (NotFoundHttpException $exception) {
      self::assertSame('Organization not found.', $exception->getMessage());
    }
  }

  #[Test]
  public function testProcessMapsDirectSerialNumberConflictToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);

    $this->dispatch($this->processor(exception: EquipmentSerialNumberAlreadyExistsException::withSerialNumber('EXT-1')));
  }

  #[Test]
  public function testProcessMapsDirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Unsupported equipment type.');

    $this->dispatch($this->processor(exception: new InvalidArgumentException('Unsupported equipment type.')));
  }

  #[Test]
  public function testProcessUnwrapsWrappedInvalidArgument(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $this->dispatch($this->processor(exception: MessengerRuntimeException::wrap(
      new InvalidArgumentException('Wrapped invalid argument.'),
    )));
  }

  #[Test]
  public function testProcessRethrowsUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('Transport is unavailable.');

    $this->dispatch($this->processor(exception: MessengerRuntimeException::wrap(new RuntimeException('Transport is unavailable.'))));
  }

  #[Test]
  public function testProcessAssignsTheCreatedEquipmentToTheRequestedFacility(): void
  {
    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function (object $message) use ($now): object {
        if ($message instanceof AssignToFacilityCommand) {
          return new AssignToFacilityResult(
            equipmentId: self::EQUIPMENT_ID,
            organizationId: self::ORG_ID,
            facilityId: self::FACILITY_ID,
            type: 'smoke_detector',
            subType: null,
            brand: null,
            model: null,
            serialNumber: null,
            locationLabel: null,
            status: 'operational',
            installedAt: '2026-03-02',
            commissionedAt: null,
            tags: [],
            createdAt: $now,
            updatedAt: $now,
          );
        }

        return $this->makeResult();
      });

    $input = $this->makeInput();
    $input->facility = '/api/facilities/' . self::FACILITY_ID;

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
    );

    $output = $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);

    self::assertSame(self::FACILITY_ID, $output->facilityId);
    self::assertSame('2026-03-02', $output->installedAt);
  }

  #[Test]
  public function testProcessAdoptsTheUriIdentifierAsTheClientIdAndAssertsCreateOnly(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (CreateEquipmentCommand $command): bool => self::CLIENT_ID === $command->resourceId))
      ->willReturn($this->makeResult());

    $requestStack = new RequestStack();
    $request = Request::create('/api/equipment/' . self::CLIENT_ID, 'PUT');
    $request->headers->set('If-None-Match', '*');
    $requestStack->push($request);

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('clientIdExists')->willReturn(false);
    $gateway->method('resourceExists')->willReturn(true);
    $gateway->method('assign')->willReturn(new InterventionResourceAssignment(null, 'published', 1));

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
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

    self::assertNull($output->intervention);
    self::assertSame('published', $output->recordStatus);
    self::assertSame(1, $output->revision);
  }

  #[Test]
  public function testProcessRejectsAnOfflineCreateWhoseClientIdIsAlreadyTaken(): void
  {
    $requestStack = new RequestStack();
    $request = Request::create('/api/equipment/' . self::CLIENT_ID, 'PUT');
    $request->headers->set('If-None-Match', '*');
    $requestStack->push($request);

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('clientIdExists')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
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

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.plan')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );

    $input = $this->makeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
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

    $processor = new CreateEquipmentProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
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

    $processor = new CreateEquipmentProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessReportsAVanishedEquipmentDuringAttachmentAsNotFound(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn($this->makeResult());

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('resourceExists')->willReturn(false);

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
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
    $gateway->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, '550e8400-e29b-41d4-a716-4466554411ff', 'draft'),
    );

    $input = $this->makeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  private function dispatch(CreateEquipmentProcessor $processor): void
  {
    $processor->process(data: $this->makeInput(), operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  private function processor(?Throwable $exception = null, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED): CreateEquipmentProcessor
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $commandBus = $this->createStub(CommandBusPort::class);

    if (null !== $exception) {
      $commandBus->method('dispatch')->willThrowException($exception);
    }

    return new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->authenticatedSecurity(),
    );
  }

  private function makeInput(): CreateEquipmentInput
  {
    $input = new CreateEquipmentInput();
    $input->type = 'smoke_detector';

    return $input;
  }

  private function makeResult(): CreateEquipmentResult
  {
    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    return new CreateEquipmentResult(
      equipmentId: self::EQUIPMENT_ID,
      organizationId: self::ORG_ID,
      facilityId: null,
      type: 'smoke_detector',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: $now,
      updatedAt: $now,
    );
  }

  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser(self::USER_ID));

    return $security;
  }

  private function permissiveAuthorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return $authorization;
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
