<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use Onboarding\Presentation\Api\Dto\Input\Onboarding\ExecuteOrganizationOnboardingStepInput;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Processor\Onboarding\ExecuteOrganizationOnboardingStepProcessor;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
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

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    // Org created after the session starts (simulates user creating the org during onboarding).
    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId, new DateTimeImmutable('+1 hour'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
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
    self::assertSame(OrganizationOnboardingStep::COMPLETE_LEGAL_PROFILE, $output->nextStep);
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

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
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

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

    $input = new ExecuteOrganizationOnboardingStepInput();

    $this->expectException(BadRequestHttpException::class);
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
