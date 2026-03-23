<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Port\Inbound\OrganizationOnboardingServicePort;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Processor\Onboarding\SkipOrganizationOnboardingStepProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException
};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
      flowService: $this->buildFlowService(),
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
      flowService: $this->buildFlowService(),
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

    /** @var OrganizationOnboardingServicePort&MockObject $flowService */
    $flowService = $this->createMock(OrganizationOnboardingServicePort::class);
    $flowService->method('skipStep')
      ->willThrowException(new InvalidArgumentException('Step "create_organization" is not skippable.'));

    $processor = new SkipOrganizationOnboardingStepProcessor(
      flowService: $flowService,
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

    /** @var OrganizationOnboardingServicePort&MockObject $flowService */
    $flowService = $this->createMock(OrganizationOnboardingServicePort::class);
    $flowService->method('skipStep')
      ->willThrowException(new LogicException('No onboarding session found.'));

    $processor = new SkipOrganizationOnboardingStepProcessor(
      flowService: $flowService,
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

    /** @var OrganizationOnboardingServicePort&MockObject $flowService */
    $flowService = $this->createMock(OrganizationOnboardingServicePort::class);
    $flowService->method('skipStep')
      ->willThrowException(
        MessengerRuntimeException::wrap(new LogicException('Step already skipped.')),
      );

    $processor = new SkipOrganizationOnboardingStepProcessor(
      flowService: $flowService,
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

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    // We mock the port directly so we can skip a step without modelling external
    // state required to advance the flow to INVITE_MEMBERS.
    /** @var OrganizationOnboardingServicePort&MockObject $flowService */
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
      flowService: $flowService,
      security: $security,
    );

    $output = $processor->process(null, new Post(), ['stepKey' => OrganizationOnboardingStep::INVITE_MEMBERS]);

    self::assertInstanceOf(OrganizationOnboardingOutput::class, $output);
    self::assertSame('organization', $output->flow);
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
      sessionRepository: $sessionRepository ?? $this->createMock(OrganizationOnboardingSessionRepositoryPort::class),
      queryBus: $queryBus ?? $this->createMock(QueryBusPort::class),
      commandBus: $commandBus ?? $this->createMock(CommandBusPort::class),
      uuidFactory: $uuidFactory ?? $this->createMock(UuidFactory::class),
      transactionManager: $transactionManager ?? $this->createMock(TransactionManagerPort::class),
      eventDispatcher: $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
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
