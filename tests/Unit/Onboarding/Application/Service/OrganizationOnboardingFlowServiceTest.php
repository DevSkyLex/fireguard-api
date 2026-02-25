<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Application\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Onboarding\Application\Port\Outbound\OrganizationOnboardingSessionRepositoryPort;
use Onboarding\Application\Service\{
  ExecuteOnboardingStepPayload,
  OrganizationOnboardingFlowService
};
use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\DeleteOrganizationRollbackAction;
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(OrganizationOnboardingFlowService::class)]
final class OrganizationOnboardingFlowServiceTest extends TestCase
{
  #[Test]
  public function testGetFlowCreatesSessionWhenNoneExists(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440101';
    $sessionId = '550e8400-e29b-41d4-a716-446655440199';

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->expects(self::once())
      ->method('findByUserId')
      ->with($userId)
      ->willReturn(null);
    $sessionRepository->expects(self::once())
      ->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('generateRaw')
      ->willReturn($sessionId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new ListUserOrganizationsResult([]));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
    );

    $state = $service->getFlow($userId);

    self::assertSame('organization', $state->flow);
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->nextStep);
    self::assertNull($state->targetOrganizationId);
    self::assertSame([], $state->completedSteps);
    self::assertFalse($state->canRollback);
    self::assertNull($state->lastRollbackableStep);
  }

  #[Test]
  public function testGetFlowWithOrgExistsAndInvitePending(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440102';
    $orgId = '550e8400-e29b-41d4-a716-446655440150';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440198',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
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
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([$orgResult]));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
    );

    $state = $service->getFlow($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);
    self::assertTrue($state->canRollback);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->lastRollbackableStep);
  }

  #[Test]
  public function testStartWithResetDeletesExistingSession(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440103';
    $sessionId = '550e8400-e29b-41d4-a716-446655440197';

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->expects(self::once())
      ->method('deleteByUserId')
      ->with($userId);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([]));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
    );

    $state = $service->start($userId, true);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->nextStep);
  }

  #[Test]
  public function testGetFlowWithOrgExistsButCreateNotYetConfirmed(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440113';
    $orgId = '550e8400-e29b-41d4-a716-446655440153';
    $sessionId = '550e8400-e29b-41d4-a716-446655440189';

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([$orgResult]));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
    );

    $state = $service->getFlow($userId);

    // Org exists externally but create_organization has not been confirmed yet.
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);
    self::assertSame([], $state->completedSteps);
    self::assertFalse($state->canRollback);
  }

  #[Test]
  public function testExecuteStepThrowsInvalidArgumentForUnknownStep(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unsupported onboarding step "unknown_step".');

    $service = $this->buildService();
    $service->executeStep(
      userId: '550e8400-e29b-41d4-a716-446655440104',
      stepKey: 'unknown_step',
      input: new ExecuteOnboardingStepPayload(),
    );
  }

  #[Test]
  public function testExecuteStepThrowsLogicExceptionWhenStepIsNotNext(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440106';
    $sessionId = '550e8400-e29b-41d4-a716-446655440195';

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
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([]));

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('"invite_members" is not available');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );
    $service->executeStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
      input: new ExecuteOnboardingStepPayload(),
    );
  }

  #[Test]
  public function testExecuteStepCreateOrganizationHappyPath(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440107';
    $orgId = '550e8400-e29b-41d4-a716-446655440157';
    $sessionId = '550e8400-e29b-41d4-a716-446655440194';

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([$orgResult]));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );

    $state = $service->executeStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      input: new ExecuteOnboardingStepPayload(),
    );

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);
    self::assertContains(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->completedSteps);
    self::assertTrue($state->canRollback);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->lastRollbackableStep);
  }

  #[Test]
  public function testExecuteStepCreateOrganizationThrowsWhenNoOrgCreated(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440114';
    $sessionId = '550e8400-e29b-41d4-a716-446655440188';

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
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([]));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('No organization found.');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );
    $service->executeStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      input: new ExecuteOnboardingStepPayload(),
    );
  }

  #[Test]
  public function testRollbackLastStepThrowsWhenNoSession(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440108';

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('No onboarding session found to rollback.');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      transactionManager: $transactionManager,
    );
    $service->rollbackLastStep($userId);
  }

  #[Test]
  public function testRollbackLastStepThrowsWhenNoRollbackActions(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440109';

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440193',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      blockedReason: null,
      targetOrganizationId: null,
      targetOrganizationName: null,
      completedSteps: [],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([]));

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('No rollback action available.');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
    );
    $service->rollbackLastStep($userId);
  }

  #[Test]
  public function testExecuteStepInviteMembersHappyPath(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440110';
    $orgId = '550e8400-e29b-41d4-a716-446655440160';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440192',
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

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([$orgResult]));

    /** @var EventDispatcherInterface&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $state = $service->executeStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
      input: new ExecuteOnboardingStepPayload(),
    );

    self::assertSame(OrganizationOnboardingState::COMPLETED, $state->state);
    self::assertNull($state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);
    self::assertContains(OrganizationOnboardingStep::INVITE_MEMBERS, $state->completedSteps);
  }

  #[Test]
  public function testSkipStepInviteMembersHappyPath(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440111';
    $orgId = '550e8400-e29b-41d4-a716-446655440161';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440191',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListUserOrganizationsResult([$orgResult]));

    /** @var EventDispatcherInterface&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $state = $service->skipStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
    );

    self::assertSame(OrganizationOnboardingState::COMPLETED, $state->state);
    self::assertNull($state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);
    self::assertContains(OrganizationOnboardingStep::INVITE_MEMBERS, $state->skippedSteps);
  }

  #[Test]
  public function testSkipStepThrowsForRequiredCreateOrganizationStep(): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Step "create_organization" is required and cannot be skipped.');

    $service = $this->buildService();
    $service->skipStep(
      userId: '550e8400-e29b-41d4-a716-446655440112',
      stepKey: OrganizationOnboardingStep::CREATE_ORGANIZATION,
    );
  }

  private function buildService(
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

  private function buildOrganizationResult(string $id, string $name, string $userId): GetOrganizationResult
  {
    return new GetOrganizationResult(
      id: $id,
      name: $name,
      slug: 'fireguard-sas',
      ownerUserId: $userId,
      createdByUserId: $userId,
      status: 'active',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );
  }
}
