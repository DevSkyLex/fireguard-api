<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Presentation\Api\Processor\Onboarding;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use LogicException;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\OrganizationOnboardingFlowService;
use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\DeleteOrganizationRollbackAction;
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use Onboarding\Presentation\Api\Dto\Output\Onboarding\OrganizationOnboardingOutput;
use Onboarding\Presentation\Api\Processor\Onboarding\RollbackOrganizationOnboardingProcessor;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(RollbackOrganizationOnboardingProcessor::class)]
final class RollbackOrganizationOnboardingProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new RollbackOrganizationOnboardingProcessor(
      flowService: $this->buildFlowService(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessDelegatesAndMapsResultOnSuccess(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440301';
    $orgId = '550e8400-e29b-41d4-a716-446655440350';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440399',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [new DeleteOrganizationRollbackAction($orgId)],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->method('save');

    // The flow during rollback is:
    // 1. First synchronizeSessionFromCurrentState → org found (preserves rollback stack)
    // 2. applyRollbackAction/rollbackDeleteOrganization → org found (user is member → delete dispatched)
    // 3. Second synchronizeSessionFromCurrentState → org gone (deleted) → returns CREATE_ORGANIZATION
    $orgResult = new GetOrganizationResult(
      id: $orgId,
      name: 'Fireguard SAS',
      slug: 'fireguard-sas',
      ownerUserId: $userId,
      createdByUserId: $userId,
      status: 'active',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    $listOrgCallCount = 0;
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturnCallback(static function (mixed $query) use ($orgResult, &$listOrgCallCount): mixed {
        ++$listOrgCallCount;
        if ($listOrgCallCount <= 2) {
          return new PaginatedResult(items: [$orgResult], total: 1, limit: 1, offset: 0);
        }

        return new PaginatedResult(items: [], total: 0, limit: 100, offset: 0);
      });

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->method('dispatch');

    $processor = new RollbackOrganizationOnboardingProcessor(
      flowService: $this->buildFlowService(
        sessionRepository: $sessionRepository,
        queryBus: $queryBus,
        commandBus: $commandBus,
        transactionManager: $transactionManager,
      ),
      security: $security,
    );

    $output = $processor->process(null, new Post());

    self::assertInstanceOf(OrganizationOnboardingOutput::class, $output);
    self::assertSame('organization', $output->flow);
  }

  #[Test]
  public function testProcessThrowsConflictOnLogicException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440302';

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

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('No onboarding session found to rollback.');

    $processor = new RollbackOrganizationOnboardingProcessor(
      flowService: $this->buildFlowService(
        sessionRepository: $sessionRepository,
        transactionManager: $transactionManager,
      ),
      security: $security,
    );

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessThrowsConflictOnMessengerRuntimeExceptionWrappingLogicException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440303';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static function (callable $fn): mixed {
        throw MessengerRuntimeException::wrap(
          new LogicException('No rollback action available.'),
        );
      });

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('No rollback action available.');

    $processor = new RollbackOrganizationOnboardingProcessor(
      flowService: $this->buildFlowService(
        transactionManager: $transactionManager,
      ),
      security: $security,
    );

    $processor->process(null, new Post());
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
