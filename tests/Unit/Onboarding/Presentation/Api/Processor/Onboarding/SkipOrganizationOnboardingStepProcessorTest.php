<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Port\Inbound\Internal\OrganizationOnboardingServicePort;
use Onboarding\Application\Port\Outbound\Internal\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Application\UseCase\Command\Flow\SkipOrganizationOnboardingStep\SkipOrganizationOnboardingStepCommand;
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Processor\Onboarding\SkipOrganizationOnboardingStepProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Exception\MessengerRuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException
};

#[CoversClass(SkipOrganizationOnboardingStepProcessor::class)]
final class SkipOrganizationOnboardingStepProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new SkipOrganizationOnboardingStepProcessor(
      commandBus: new \Tests\Helper\Onboarding\OrganizationOnboardingCommandBus($this->buildFlowService()),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS]);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenStepKeyMissing(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440701';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $processor = new SkipOrganizationOnboardingStepProcessor(
      commandBus: new \Tests\Helper\Onboarding\OrganizationOnboardingCommandBus($this->buildFlowService()),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('stepKey URI parameter is required.');

    $processor->process(null, new Post(), []);
  }

  #[Test]
  public function testProcessThrowsBadRequestOnNonSkippableStep(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440702';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $flowService = $this->createStub(OrganizationOnboardingServicePort::class);
    $flowService->method('skipStep')
      ->willThrowException(new InvalidArgumentException('Step "create_organization" is not skippable.'));

    $processor = new SkipOrganizationOnboardingStepProcessor(
      commandBus: new \Tests\Helper\Onboarding\OrganizationOnboardingCommandBus($flowService),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Step "create_organization" is not skippable.');

    $processor->process(null, new Post(), ['stepKey' => OrganizationOnboardingStep::CREATE_ORGANIZATION]);
  }

  #[Test]
  public function testProcessThrowsConflictOnLogicException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440703';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $flowService = $this->createStub(OrganizationOnboardingServicePort::class);
    $flowService->method('skipStep')
      ->willThrowException(new LogicException('No onboarding session found.'));

    $processor = new SkipOrganizationOnboardingStepProcessor(
      commandBus: new \Tests\Helper\Onboarding\OrganizationOnboardingCommandBus($flowService),
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('No onboarding session found.');

    $processor->process(null, new Post(), ['stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS]);
  }

  #[Test]
  public function testProcessThrowsConflictOnMessengerRuntimeExceptionWrappingLogicException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440704';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $flowService = $this->createStub(OrganizationOnboardingServicePort::class);
    $flowService->method('skipStep')
      ->willThrowException(
        MessengerRuntimeException::wrap(new LogicException('Step already skipped.')),
      );

    $processor = new SkipOrganizationOnboardingStepProcessor(
      commandBus: new \Tests\Helper\Onboarding\OrganizationOnboardingCommandBus($flowService),
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Step already skipped.');

    $processor->process(null, new Post(), ['stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS]);
  }

  #[Test]
  public function testProcessSkipsStepSuccessfully(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440705';
    $orgId = '550e8400-e29b-41d4-a716-446655440750';
    $sessionId = '550e8400-e29b-41d4-a716-446655440799';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn($sessionId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    // We mock the port directly so we can skip a step without modelling external
    // state required to advance the flow to INVITE_MEMBERS.
    $flowService = $this->createMock(OrganizationOnboardingServicePort::class);
    $flowService
      ->expects(self::once())
      ->method('skipStep')
      ->with($userId, OrganizationOnboardingStep::INVITE_MEMBERS)
      ->willReturn(
        $this->buildFlowService(
          sessionRepository: $sessionRepository,
          queryBus: $queryBus,
          uuidFactory: $uuidFactory,
        )->getFlow($userId),
      );

    $processor = new SkipOrganizationOnboardingStepProcessor(
      commandBus: new \Tests\Helper\Onboarding\OrganizationOnboardingCommandBus($flowService),
      security: $security,
    );

    $output = $processor->process(null, new Post(), ['stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS]);

    self::assertInstanceOf(OrganizationOnboardingOutput::class, $output);
    self::assertSame('organization', $output->flow);
  }

  #[Test]
  public function testWrappedInvalidStepRemainsABadRequestAcrossTheCommandBus(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440707';
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser($userId));
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (SkipOrganizationOnboardingStepCommand $command): bool => $command->userId === $userId && 'unknown_step' === $command->stepKey,
    ))->willThrowException(MessengerRuntimeException::wrap(new InvalidArgumentException('Unknown onboarding step.')));
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Unknown onboarding step.');

    new SkipOrganizationOnboardingStepProcessor($commandBus, $security)->process(null, new Post(), ['stepKey' => 'unknown_step']);
  }

  #[Test]
  public function testProcessRethrowsAMessengerRuntimeExceptionThatWrapsNoLogicException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440706';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    $flowService = $this->createStub(OrganizationOnboardingServicePort::class);
    $flowService->method('skipStep')
      ->willThrowException(
        MessengerRuntimeException::wrap(new RuntimeException('Downstream transport is unavailable.')),
      );

    $processor = new SkipOrganizationOnboardingStepProcessor(
      commandBus: new \Tests\Helper\Onboarding\OrganizationOnboardingCommandBus($flowService),
      security: $security,
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(null, new Post(), ['stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS]);
  }

  private function buildFlowService(
    ?OrganizationOnboardingSessionRepositoryPort $sessionRepository = null,
    ?QueryBusPort $queryBus = null,
    ?CommandBusPort $commandBus = null,
    ?UuidGeneratorPort $uuidFactory = null,
    ?TransactionManagerPort $transactionManager = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): OrganizationOnboardingFlowService {
    if (null === $transactionManager) {
      $transactionManager = $this->createStub(TransactionManagerPort::class);
      $transactionManager->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    }

    return new OrganizationOnboardingFlowService(
      sessionRepository: $sessionRepository ?? $this->createStub(OrganizationOnboardingSessionRepositoryPort::class),
      organizationDirectory: new \Organization\Infrastructure\Adapter\Onboarding\OrganizationOnboardingDirectoryAdapter($queryBus ?? $this->createStub(QueryBusPort::class), $commandBus ?? $this->createStub(CommandBusPort::class)),
      steps: \Tests\Helper\Onboarding\RegisteredOrganizationOnboardingSteps::withAvailability(new \Facility\Infrastructure\Adapter\Onboarding\FacilityAvailabilityAdapter($queryBus ?? $this->createStub(QueryBusPort::class)), new \Equipment\Infrastructure\Adapter\Onboarding\EquipmentAvailabilityAdapter($queryBus ?? $this->createStub(QueryBusPort::class))),
      uuidFactory: $uuidFactory ?? $this->createStub(UuidGeneratorPort::class),
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
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
