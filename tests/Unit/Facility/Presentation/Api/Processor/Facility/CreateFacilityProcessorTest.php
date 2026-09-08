<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityResult};
use Facility\Domain\Exception\{FacilityCodeAlreadyExistsException, FacilityHierarchyException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Input\Facility\CreateFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Processor\Facility\CreateFacilityProcessor;
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

#[CoversClass(CreateFacilityProcessor::class)]
final class CreateFacilityProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441501';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655441502';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655441503';

  private const string CLIENT_ID = '550e8400-e29b-41d4-a716-446655441504';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655441505';

  #[Test]
  public function testProcessMapsWrappedCodeConflictToHttp409(): void
  {
    $input = new CreateFacilityInput();
    $input->type = 'site';
    $input->name = 'HQ';
    $input->code = 'SITE-001';

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441000');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), '550e8400-e29b-41d4-a716-446655441001', 'organization.facilities.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $domainException = FacilityCodeAlreadyExistsException::withCode('SITE-001');
    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateFacilityCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441001',
        type: 'site',
        name: 'HQ',
        code: 'SITE-001',
      )),
      [$domainException],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new CreateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Facility code "SITE-001" already exists for this organization.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441001'],
    );
  }

  #[Test]
  public function testProcessMapsWrappedQuotaExceededToHttp409(): void
  {
    $input = new CreateFacilityInput();
    $input->type = 'site';
    $input->name = 'HQ';

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441100');

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
      new Envelope(new CreateFacilityCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441101',
        type: 'site',
        name: 'HQ',
      )),
      [OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::FACILITIES->value, 2)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new CreateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441101'],
    );
  }

  #[Test]
  public function testProcessPassesCoordinatesThroughToCommandAndOutput(): void
  {
    $input = new CreateFacilityInput();
    $input->type = 'site';
    $input->name = 'Paris HQ';
    $input->latitude = 48.8566;
    $input->longitude = 2.3522;

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442000');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateFacilityCommand $command): bool {
        return 48.8566 === $command->latitude && 2.3522 === $command->longitude;
      }))
      ->willReturn(new CreateFacilityResult(
        facilityId: '550e8400-e29b-41d4-a716-446655442001',
        organizationId: '550e8400-e29b-41d4-a716-446655442002',
        parentFacilityId: null,
        type: 'site',
        name: 'Paris HQ',
        code: null,
        status: 'active',
        address: null,
        metadata: [],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        latitude: 48.8566,
        longitude: 2.3522,
      ));

    $processor = new CreateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655442002'],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertSame(48.8566, $output->latitude);
    self::assertSame(2.3522, $output->longitude);
  }

  #[Test]
  public function testProcessPassesLevelIndexThroughToCommandAndOutput(): void
  {
    $input = new CreateFacilityInput();
    $input->type = 'floor';
    $input->name = 'First Basement';
    $input->levelIndex = -1;

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442010');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (CreateFacilityCommand $command): bool => -1 === $command->levelIndex))
      ->willReturn(new CreateFacilityResult(
        facilityId: '550e8400-e29b-41d4-a716-446655442011',
        organizationId: '550e8400-e29b-41d4-a716-446655442012',
        parentFacilityId: null,
        type: 'floor',
        name: 'First Basement',
        code: null,
        status: 'active',
        address: null,
        metadata: [],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        levelIndex: -1,
      ));

    $processor = new CreateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655442012'],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertSame(-1, $output->levelIndex);
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateFacilityProcessor(
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
    $processor = $this->processor();

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: $this->makeInput(), operation: new Post(), uriVariables: []);
  }

  #[Test]
  public function testProcessThrowsWhenPermissionDenied(): void
  {
    $processor = $this->processor(decision: OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(AccessDeniedHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsOutsideScopeToHttp404(): void
  {
    $processor = $this->processor(decision: OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsDirectCodeConflictToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);

    $this->dispatch($this->processor(exception: FacilityCodeAlreadyExistsException::withCode('SITE-001')));
  }

  #[Test]
  public function testProcessMapsDirectNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($this->processor(exception: FacilityNotFoundException::withId(self::PARENT_ID)));
  }

  #[Test]
  public function testProcessMapsDirectHierarchyExceptionToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->dispatch($this->processor(exception: FacilityHierarchyException::cannotUseSelfAsParent()));
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedCodeConflict(): void
  {
    $this->expectException(ConflictHttpException::class);

    $this->dispatch($this->processor(exception: MessengerRuntimeException::wrap(
      FacilityCodeAlreadyExistsException::withCode('SITE-002'),
    )));
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($this->processor(exception: MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId(self::PARENT_ID),
    )));
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($this->processor(exception: $this->handlerFailure(FacilityNotFoundException::withId(self::PARENT_ID))));
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedHierarchyException(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Parent facility must belong to the same organization.');

    $this->dispatch($this->processor(exception: MessengerRuntimeException::wrap(
      FacilityHierarchyException::parentInAnotherOrganization(),
    )));
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedHierarchyException(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->dispatch($this->processor(exception: $this->handlerFailure(FacilityHierarchyException::hierarchyCycleDetected())));
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedInvalidArgument(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $this->dispatch($this->processor(exception: MessengerRuntimeException::wrap(
      new InvalidArgumentException('Wrapped invalid argument.'),
    )));
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedInvalidArgument(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Handler invalid argument.');

    $this->dispatch($this->processor(exception: $this->handlerFailure(new InvalidArgumentException('Handler invalid argument.'))));
  }

  #[Test]
  public function testProcessRethrowsUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('Transport is unavailable.');

    $this->dispatch($this->processor(exception: MessengerRuntimeException::wrap(new RuntimeException('Transport is unavailable.'))));
  }

  #[Test]
  public function testProcessAdoptsTheUriIdentifierAsTheClientIdAndAssertsCreateOnly(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (CreateFacilityCommand $command): bool => self::CLIENT_ID === $command->resourceId))
      ->willReturn($this->makeResult());

    $requestStack = new RequestStack();
    $request = Request::create('/api/facilities/' . self::CLIENT_ID, 'PUT');
    $request->headers->set('If-None-Match', '*');
    $requestStack->push($request);

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('clientIdExists')->willReturn(false);
    $gateway->method('resourceExists')->willReturn(true);
    $gateway->method('assign')->willReturn(new InterventionResourceAssignment(null, 'published', 1));

    $processor = new CreateFacilityProcessor(
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
    $request = Request::create('/api/facilities/' . self::CLIENT_ID, 'PUT');
    $request->headers->set('If-None-Match', '*');
    $requestStack->push($request);

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('clientIdExists')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new CreateFacilityProcessor(
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

    $processor = new CreateFacilityProcessor(
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

    $processor = new CreateFacilityProcessor(
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

    $processor = new CreateFacilityProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessReportsAVanishedFacilityDuringAttachmentAsNotFound(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn($this->makeResult());

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('resourceExists')->willReturn(false);

    $processor = new CreateFacilityProcessor(
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
    // The intervention belongs to a different organization than the facility.
    $gateway->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, '550e8400-e29b-41d4-a716-4466554400ff', 'draft'),
    );

    $input = $this->makeInput();
    $input->intervention = '/api/interventions/' . self::INTERVENTION_ID;

    $processor = new CreateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $this->permissiveAuthorization(),
      security: $this->authenticatedSecurity(),
      interventionResourceManager: new InterventionResourceManager($gateway),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(data: $input, operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  private function dispatch(CreateFacilityProcessor $processor): void
  {
    $processor->process(data: $this->makeInput(), operation: new Post(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  private function processor(?Throwable $exception = null, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED): CreateFacilityProcessor
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $commandBus = $this->createStub(CommandBusPort::class);

    if (null !== $exception) {
      $commandBus->method('dispatch')->willThrowException($exception);
    }

    return new CreateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->authenticatedSecurity(),
    );
  }

  private function handlerFailure(Throwable $exception): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new CreateFacilityCommand(organizationId: self::ORG_ID, type: 'site', name: 'HQ')),
      [$exception],
    ));
  }

  private function makeInput(): CreateFacilityInput
  {
    $input = new CreateFacilityInput();
    $input->type = 'site';
    $input->name = 'HQ';

    return $input;
  }

  private function makeResult(): CreateFacilityResult
  {
    $now = new DateTimeImmutable('2026-02-12T10:00:00+00:00');

    return new CreateFacilityResult(
      facilityId: self::FACILITY_ID,
      organizationId: self::ORG_ID,
      parentFacilityId: null,
      type: 'site',
      name: 'HQ',
      code: null,
      status: 'active',
      address: null,
      metadata: [],
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
