<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Domain\Exception\OnboardingStepNotExecutableException;
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use Onboarding\Presentation\Api\Dto\Input\Onboarding\ExecuteOrganizationOnboardingStepInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Processor\Onboarding\ExecuteOrganizationOnboardingStepProcessor;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationSlugAlreadyExistsException};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use ValueError;

#[CoversClass(ExecuteOrganizationOnboardingStepProcessor::class)]
final class ExecuteOrganizationOnboardingStepProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new ExecuteOrganizationOnboardingStepProcessor(
      flowService: $this->buildFlowService(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: new ExecuteOrganizationOnboardingStepInput(),
      operation: new Post(),
      uriVariables: ['stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenStepKeyMissing(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440401';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $processor = new ExecuteOrganizationOnboardingStepProcessor(
      flowService: $this->buildFlowService(),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('stepKey URI parameter is required.');

    $processor->process(
      data: new ExecuteOrganizationOnboardingStepInput(),
      operation: new Post(),
      uriVariables: [],
    );
  }

  #[Test]
  public function testProcessBuildsPayloadAndMapsResultOnSuccess(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440402';
    $orgId = '550e8400-e29b-41d4-a716-446655440450';
    $sessionId = '550e8400-e29b-41d4-a716-446655440499';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    // Org created after the session starts (simulates user creating the org during onboarding).
    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [$orgResult], total: 1, limit: 1, offset: 0));

    $input = new ExecuteOrganizationOnboardingStepInput();

    $processor = new ExecuteOrganizationOnboardingStepProcessor(
      flowService: $this->buildFlowService(
        sessionRepository: $sessionRepository,
        queryBus: $queryBus,
        uuidFactory: $uuidFactory,
        transactionManager: $transactionManager,
      ),
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION],
    );

    self::assertInstanceOf(OrganizationOnboardingOutput::class, $output);
    self::assertSame('organization', $output->flow);
    self::assertSame('in_progress', $output->state);
    self::assertSame(OrganizationOnboardingStep::SELECT_PLAN, $output->nextStep);
  }

  #[Test]
  public function testProcessThrowsConflictOnLogicException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440403';
    $sessionId = '550e8400-e29b-41d4-a716-446655440498';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    $input = new ExecuteOrganizationOnboardingStepInput();

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('"invite_members" is not available');

    $processor = new ExecuteOrganizationOnboardingStepProcessor(
      flowService: $this->buildFlowService(
        sessionRepository: $sessionRepository,
        queryBus: $queryBus,
        uuidFactory: $uuidFactory,
        transactionManager: $transactionManager,
      ),
      security: $security,
    );

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS],
    );
  }

  #[Test]
  public function testProcessThrowsBadRequestOnInvalidArgumentException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440404';
    $sessionId = '550e8400-e29b-41d4-a716-446655440497';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    $input = new ExecuteOrganizationOnboardingStepInput();

    // The processor no longer maps this one: the flow service throws a domain
    // exception, `api_platform.exception_to_status` carries its 400, and
    // `BusFailureUnwrappingSubscriber` unwraps the envelope. The unit's job is
    // to let it through untouched.
    $this->expectException(OnboardingStepNotExecutableException::class);
    $this->expectExceptionMessage('No organization found.');

    $processor = new ExecuteOrganizationOnboardingStepProcessor(
      flowService: $this->buildFlowService(
        sessionRepository: $sessionRepository,
        queryBus: $queryBus,
        uuidFactory: $uuidFactory,
        transactionManager: $transactionManager,
      ),
      security: $security,
    );

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION],
    );
  }

  #[Test]
  public function testProcessMapsASlugConflictToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('already exists');

    $this->processWithFlowFailure(OrganizationSlugAlreadyExistsException::withSlug('fireguard-sas'));
  }

  #[Test]
  public function testProcessMapsAMissingOrganizationToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('not found');

    $this->processWithFlowFailure(OrganizationNotFoundException::withId('org-1'));
  }

  #[Test]
  public function testProcessMapsAnInvalidValueToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('slug is malformed');

    $this->processWithFlowFailure(InvalidValueException::because('slug is malformed'));
  }

  #[Test]
  public function testProcessMapsAValueErrorToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('unknown step');

    $this->processWithFlowFailure(new ValueError('unknown step'));
  }

  #[Test]
  public function testProcessMapsAWrappedSlugConflictToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('already exists');

    $this->processWithFlowFailure(MessengerRuntimeException::wrap(
      OrganizationSlugAlreadyExistsException::withSlug('fireguard-sas'),
    ));
  }

  #[Test]
  public function testProcessMapsAWrappedMissingOrganizationToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('not found');

    $this->processWithFlowFailure(MessengerRuntimeException::wrap(
      OrganizationNotFoundException::withId('org-1'),
    ));
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('stepKey is unknown');

    $this->processWithFlowFailure(MessengerRuntimeException::wrap(
      new InvalidArgumentException('stepKey is unknown'),
    ));
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidValueToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('slug is malformed');

    $this->processWithFlowFailure(MessengerRuntimeException::wrap(
      InvalidValueException::because('slug is malformed'),
    ));
  }

  #[Test]
  public function testProcessMapsAWrappedValueErrorToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('unknown step');

    $this->processWithFlowFailure(MessengerRuntimeException::wrap(new ValueError('unknown step')));
  }

  #[Test]
  public function testProcessMapsAWrappedLogicExceptionToHttp409(): void
  {
    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('step is not available');

    $this->processWithFlowFailure(MessengerRuntimeException::wrap(
      new LogicException('step is not available'),
    ));
  }

  #[Test]
  public function testProcessRethrowsAMessengerFailureWithNoRecognisedCause(): void
  {
    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('transport down');

    $this->processWithFlowFailure(MessengerRuntimeException::wrap(new RuntimeException('transport down')));
  }

  private function processWithFlowFailure(Throwable $failure): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655440405'));

    $flowService = $this->createStub(OrganizationOnboardingServicePort::class);
    $flowService->method('executeStep')->willThrowException($failure);

    $processor = new ExecuteOrganizationOnboardingStepProcessor(
      flowService: $flowService,
      security: $security,
    );

    $processor->process(
      data: new ExecuteOrganizationOnboardingStepInput(),
      operation: new Post(),
      uriVariables: ['stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION],
    );
  }

  private function buildFlowService(
    ?OrganizationOnboardingSessionRepositoryPort $sessionRepository = null,
    ?QueryBusPort $queryBus = null,
    ?CommandBusPort $commandBus = null,
    ?UuidFactory $uuidFactory = null,
    ?TransactionManagerPort $transactionManager = null,
    ?EventDispatcherInterface $eventDispatcher = null,
  ): OrganizationOnboardingFlowService {
    return new OrganizationOnboardingFlowService(
      sessionRepository: $sessionRepository ?? $this->createStub(OrganizationOnboardingSessionRepositoryPort::class),
      queryBus: $queryBus ?? $this->createStub(QueryBusPort::class),
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      uuidFactory: $uuidFactory ?? $this->createStub(UuidFactory::class),
      transactionManager: $transactionManager ?? $this->createStub(TransactionManagerPort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
    );
  }

  private function buildOrganizationResult(
    string $id,
    string $name,
    string $userId,
    ?DateTimeImmutable $createdAt = null,
  ): GetOrganizationResult {
    $date = $createdAt ?? new DateTimeImmutable('+1 hour');

    return new GetOrganizationResult(
      id: $id,
      name: $name,
      slug: 'fireguard-sas',
      ownerUserId: $userId,
      createdByUserId: $userId,
      status: 'active',
      isActive: true,
      createdAt: $date,
      updatedAt: $date,
    );
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
