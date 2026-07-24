<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Application\Service;

use DateTimeImmutable;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\ListEquipmentsQuery;
use Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesQuery;
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
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
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

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

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

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

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
  public function testGetFlowWithPreExistingOrgIsNotAdoptedForFreshSession(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440113';
    $orgId = '550e8400-e29b-41d4-a716-446655440153';
    $sessionId = '550e8400-e29b-41d4-a716-446655440189';

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    // Org predates the session (pre-existing production org)
    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
    );

    $state = $service->getFlow($userId);

    // A pre-existing org must not be auto-adopted: targetOrganizationId stays null
    // and the user is required to create a new org for this onboarding session.
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->nextStep);
    self::assertNull($state->targetOrganizationId);
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

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, null);

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

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    // Org was created after the session started (simulates user creating the org during onboarding)
    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId, new DateTimeImmutable('+1 hour'));

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

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
    // select_plan is the first step proposed after the organization is created.
    self::assertSame(OrganizationOnboardingStep::SELECT_PLAN, $state->nextStep);
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

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, null);

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
  public function testExecuteStepCreateOrganizationThrowsWhenPreExistingOrgIsPresent(): void
  {
    // A pre-existing org (createdAt in the past) must not be adoptable for create_organization.
    // Without this guard, rollback would destroy a production org the user did not create
    // during onboarding.
    $userId = '550e8400-e29b-41d4-a716-446655440116';
    $orgId = '550e8400-e29b-41d4-a716-446655440156';
    $sessionId = '550e8400-e29b-41d4-a716-446655440186';

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    // Pre-existing org with a past createdAt (predates the onboarding session)
    $orgResult = $this->buildOrganizationResult($orgId, 'Legacy Corp', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

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

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
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

    $transactionManager = $this->createStub(TransactionManagerPort::class);
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

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new PaginatedResult(items: [], total: 0, limit: 100, offset: 0));

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
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::SELECT_PLAN,
      ],
      skippedSteps: [],
      rollbackStack: [new DeleteOrganizationRollbackAction($orgId)],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
    );

    $state = $service->executeStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
      input: new ExecuteOnboardingStepPayload(),
    );

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_FIRST_FACILITY, $state->nextStep);
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
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::SELECT_PLAN,
      ],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
    );

    $state = $service->skipStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
    );

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_FIRST_FACILITY, $state->nextStep);
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

  #[Test]
  public function testFullFlowCompletionFiresEvent(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440117';
    $orgId = '550e8400-e29b-41d4-a716-446655440167';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440186',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::SELECT_PLAN,
        OrganizationOnboardingStep::INVITE_MEMBERS,
        OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      ],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus(
      $queryBus,
      $orgResult,
      hasFacility: true,
      hasEquipment: true,
    );

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
      stepKey: OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT,
      input: new ExecuteOnboardingStepPayload(),
    );

    self::assertSame(OrganizationOnboardingState::COMPLETED, $state->state);
    self::assertNull($state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);
    self::assertContains(OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT, $state->completedSteps);
  }

  #[Test]
  public function testGetFlowDoesNotAutoCompleteStepsFromModuleState(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440118';
    $orgId = '550e8400-e29b-41d4-a716-446655440168';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440185',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [
        OrganizationOnboardingStep::SELECT_PLAN,
        OrganizationOnboardingStep::INVITE_MEMBERS,
      ],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    // Facility already exists externally — must NOT auto-complete the step
    $this->configureQueryBus($queryBus, $orgResult, hasFacility: true);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
    );

    $state = $service->getFlow($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_FIRST_FACILITY, $state->nextStep);
    self::assertSame([OrganizationOnboardingStep::CREATE_ORGANIZATION], $state->completedSteps);
  }

  #[Test]
  public function testGetFlowDoesNotDispatchCompletionEventFromExternalModuleStateAlone(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440122';
    $orgId = '550e8400-e29b-41d4-a716-446655440173';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440181',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::SELECT_PLAN,
        OrganizationOnboardingStep::INVITE_MEMBERS,
        OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      ],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus(
      $queryBus,
      $orgResult,
      hasFacility: true,
      hasEquipment: true,
    );

    /** @var EventDispatcherInterface&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
    );

    $state = $service->getFlow($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT, $state->nextStep);
    self::assertNotContains(OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT, $state->completedSteps);
  }

  #[Test]
  public function testPinnedOrgResetsWhenDeletedExternally(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440119';
    $orgId = '550e8400-e29b-41d4-a716-446655440169';
    $otherOrgId = '550e8400-e29b-41d4-a716-446655440170';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440184',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::INVITE_MEMBERS,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Deleted Org',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    // The pinned org no longer exists; the user has a different org
    $otherOrgResult = $this->buildOrganizationResult($otherOrgId, 'Other Org', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new PaginatedResult(items: [$otherOrgResult], total: 1, limit: 1, offset: 0));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
    );

    $state = $service->getFlow($userId);

    // Pinned org was deleted — flow resets to create_organization, does NOT switch to other org
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->nextStep);
    self::assertNull($state->targetOrganizationId);
  }

  #[Test]
  public function testExecuteAutoDetectedStepThrowsWhenModuleStateNotPresent(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440120';
    $orgId = '550e8400-e29b-41d4-a716-446655440171';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440183',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [
        OrganizationOnboardingStep::SELECT_PLAN,
        OrganizationOnboardingStep::INVITE_MEMBERS,
      ],
      rollbackStack: [new DeleteOrganizationRollbackAction($orgId)],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    // Facility does NOT exist yet
    $this->configureQueryBus($queryBus, $orgResult, hasFacility: false);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot confirm step "create_first_facility"');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
    );
    $service->executeStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      input: new ExecuteOnboardingStepPayload(),
    );
  }

  #[Test]
  public function testSkippedStepsAreClearedWhenPinnedOrgIsDeleted(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440121';
    $orgId = '550e8400-e29b-41d4-a716-446655440172';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440182',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Deleted Org',
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
      ],
      skippedSteps: [OrganizationOnboardingStep::INVITE_MEMBERS],
      rollbackStack: [],
      stepHistory: [
        new \Onboarding\Domain\Model\OrganizationOnboardingSession\StepHistoryEntry(
          stepKey: OrganizationOnboardingStep::CREATE_ORGANIZATION,
          occurredAt: '2026-02-19T08:00:00+00:00',
          skipped: false,
        ),
        new \Onboarding\Domain\Model\OrganizationOnboardingSession\StepHistoryEntry(
          stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
          occurredAt: '2026-02-19T09:00:00+00:00',
          skipped: true,
        ),
      ],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
    );

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);
    $sessionRepository->expects(self::once())->method('save');

    $queryBus = $this->createStub(QueryBusPort::class);
    // No organizations returned — pinned org was deleted externally
    $this->configureQueryBus($queryBus, null);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
    );

    $state = $service->getFlow($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->nextStep);
    self::assertNull($state->targetOrganizationId);
    // skippedSteps must be cleared so invite_members is not pre-skipped for the new org
    self::assertSame([], $state->skippedSteps);
    self::assertSame([], $state->completedSteps);
    self::assertSame([], $state->stepHistory);
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
    $date = $createdAt ?? new DateTimeImmutable('2026-02-19T08:00:00+00:00');

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

  /**
   * Configures the QueryBus mock to route queries by type.
   */
  private function configureQueryBus(
    QueryBusPort&Stub $queryBus,
    ?GetOrganizationResult $orgResult,
    bool $hasFacility = false,
    bool $hasEquipment = false,
  ): void {
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query) use ($orgResult, $hasFacility, $hasEquipment): mixed {
        if ($query instanceof ListUserOrganizationsQuery) {
          if (null === $orgResult) {
            return new PaginatedResult(items: [], total: 0, limit: 100, offset: 0);
          }

          return new PaginatedResult(items: [$orgResult], total: 1, limit: 1, offset: 0);
        }

        if ($query instanceof ListFacilitiesQuery) {
          return new PaginatedResult(items: [], total: $hasFacility ? 1 : 0, limit: 1, offset: 0);
        }

        if ($query instanceof ListEquipmentsQuery) {
          return new PaginatedResult(items: [], total: $hasEquipment ? 1 : 0, limit: 1, offset: 0);
        }

        throw new RuntimeException('Unexpected query type: ' . $query::class);
      },
    );
  }
}
