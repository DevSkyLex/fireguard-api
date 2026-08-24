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
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\{DeleteOrganizationRollbackAction, RollbackActionInterface};
use Onboarding\Domain\ValueObject\{OrganizationOnboardingState, OrganizationOnboardingStep};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsQuery;
use Organization\Domain\Exception\OrganizationNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\ResultMessage;
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
  public function testGetFlowCompletesWhenMemberAlreadyBelongsToAnOrganization(): void
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

    // The member already has a workspace — the shape of an accepted invitation.
    // Sending them to `create_organization` used to strand them there for good,
    // since onboardingRequiredGuard holds every route until the flow completes.
    self::assertSame(OrganizationOnboardingState::COMPLETED, $state->state);
    self::assertNull($state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);

    // The safety property the old expectation really guarded: this organization
    // predates the session, so no rollback may ever reach it.
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
  public function testExecuteStepCreateOrganizationIsUnreachableWhenPreExistingOrgIsPresent(): void
  {
    // A pre-existing org must never become a rollback target, or rollback would
    // destroy a production org the user did not create during onboarding. The
    // guard now bites earlier: the member already has a workspace, so the flow
    // completes and `create_organization` is no longer an available step at all.
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

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );

    // No rollback action exists over an organization the flow never created.
    self::assertFalse($service->getFlow($userId)->canRollback);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Step "create_organization" is not available.');

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
  public function testSkipStepThrowsInvalidArgumentForUnknownStep(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unsupported onboarding step "teleport_to_mars".');

    $service = $this->buildService();
    $service->skipStep(
      userId: '550e8400-e29b-41d4-a716-446655440112',
      stepKey: 'teleport_to_mars',
    );
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

  #[Test]
  public function testGetFlowDispatchesCompletionEventWhenTransitioningToCompleted(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440130';
    $orgId = '550e8400-e29b-41d4-a716-446655440180';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440230',
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
        OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT,
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
    $this->configureQueryBus($queryBus, $orgResult);

    /** @var EventDispatcherInterface&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
    );

    $state = $service->getFlow($userId);

    self::assertSame(OrganizationOnboardingState::COMPLETED, $state->state);
    self::assertNull($state->nextStep);
    self::assertSame($orgId, $state->targetOrganizationId);
  }

  #[Test]
  public function testExecuteStepSelectPlanConfirmsWithoutRollbackAction(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440131';
    $orgId = '550e8400-e29b-41d4-a716-446655440181';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440231',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::SELECT_PLAN,
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
      stepKey: OrganizationOnboardingStep::SELECT_PLAN,
      input: new ExecuteOnboardingStepPayload(),
    );

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame(OrganizationOnboardingStep::INVITE_MEMBERS, $state->nextStep);
    self::assertContains(OrganizationOnboardingStep::SELECT_PLAN, $state->completedSteps);
    // Confirming select_plan carries no rollback action of its own; the
    // create_organization rollback remains the last rollbackable step.
    self::assertTrue($state->canRollback);
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->lastRollbackableStep);
  }

  #[Test]
  public function testRollbackLastStepDeletesCreatedOrganization(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440132';
    $orgId = '550e8400-e29b-41d4-a716-446655440182';

    $existingSession = $this->buildRollbackableSession($userId, $orgId, '550e8400-e29b-41d4-a716-446655440232');

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

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn($this->createStub(ResultMessage::class));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      commandBus: $commandBus,
      transactionManager: $transactionManager,
    );

    $state = $service->rollbackLastStep($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    // The single rollback action was consumed, so nothing remains to roll back.
    self::assertFalse($state->canRollback);
    self::assertNull($state->lastRollbackableStep);
    self::assertSame($orgId, $state->targetOrganizationId);
  }

  #[Test]
  public function testRollbackSwallowsOrganizationNotFoundException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440133';
    $orgId = '550e8400-e29b-41d4-a716-446655440183';

    $existingSession = $this->buildRollbackableSession($userId, $orgId, '550e8400-e29b-41d4-a716-446655440233');

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationNotFoundException::withId($orgId));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      commandBus: $commandBus,
      transactionManager: $transactionManager,
    );

    // A missing organization means the rollback is already effectively done:
    // the exception is swallowed and the flow resolves normally.
    $state = $service->rollbackLastStep($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
  }

  #[Test]
  public function testRollbackSwallowsWrappedOrganizationNotFoundException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440134';
    $orgId = '550e8400-e29b-41d4-a716-446655440184';

    $existingSession = $this->buildRollbackableSession($userId, $orgId, '550e8400-e29b-41d4-a716-446655440234');

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(OrganizationNotFoundException::withId($orgId)));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      commandBus: $commandBus,
      transactionManager: $transactionManager,
    );

    // A NotFound wrapped inside a MessengerRuntimeException is unwrapped and
    // treated as a successful (idempotent) rollback.
    $state = $service->rollbackLastStep($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
  }

  #[Test]
  public function testRollbackRethrowsUnrelatedMessengerRuntimeException(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440135';
    $orgId = '550e8400-e29b-41d4-a716-446655440185';

    $existingSession = $this->buildRollbackableSession($userId, $orgId, '550e8400-e29b-41d4-a716-446655440235');

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(new RuntimeException('database is down')));

    $this->expectException(MessengerRuntimeException::class);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      commandBus: $commandBus,
      transactionManager: $transactionManager,
    );
    // An unrelated failure (not a NotFound) must propagate instead of being swallowed.
    $service->rollbackLastStep($userId);
  }

  #[Test]
  public function testRollbackThrowsForUnsupportedRollbackActionType(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440136';
    $orgId = '550e8400-e29b-41d4-a716-446655440186';

    $unsupportedAction = new class () implements RollbackActionInterface {
      public function step(): string
      {
        return OrganizationOnboardingStep::CREATE_ORGANIZATION;
      }

      public function actionType(): string
      {
        return 'custom_type';
      }

      /**
       * @return array<string, mixed>
       */
      public function toArray(): array
      {
        return ['action' => 'custom_type'];
      }
    };

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440236',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::SELECT_PLAN,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [$unsupportedAction],
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
    $this->configureQueryBus($queryBus, $orgResult);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Unsupported rollback action type "custom_type".');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
    );
    $service->rollbackLastStep($userId);
  }

  #[Test]
  public function testDismissHidesActivationFlow(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440137';
    $sessionId = '550e8400-e29b-41d4-a716-446655440237';

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, null);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );

    $state = $service->dismiss($userId);

    self::assertTrue($state->dismissed);
    self::assertNotNull($state->dismissedAt);
    // Dismissal is orthogonal to progression: the flow stays in progress.
    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
  }

  #[Test]
  public function testResumeClearsDismissal(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440138';
    $orgId = '550e8400-e29b-41d4-a716-446655440188';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440238',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::SELECT_PLAN,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [],
      stepHistory: [],
      createdAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-19T08:00:00+00:00'),
      dismissedAt: new DateTimeImmutable('2026-02-19T09:00:00+00:00'),
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

    $state = $service->resume($userId);

    self::assertFalse($state->dismissed);
    self::assertNull($state->dismissedAt);
  }

  #[Test]
  public function testSkipStepThrowsWhenOnboardingAlreadyCompleted(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440139';
    $orgId = '550e8400-e29b-41d4-a716-446655440189';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440239',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: null,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::SELECT_PLAN,
        OrganizationOnboardingStep::INVITE_MEMBERS,
        OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
        OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT,
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

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Onboarding is already completed. Nothing to skip.');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
    );
    $service->skipStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
    );
  }

  #[Test]
  public function testSkipStepThrowsWhenStepIsNotCurrentPending(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440140';
    $orgId = '550e8400-e29b-41d4-a716-446655440190';

    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440240',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::SELECT_PLAN,
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

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $fn): mixed => $fn());

    $sessionRepository = $this->createStub(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn($existingSession);

    $orgResult = $this->buildOrganizationResult($orgId, 'Fireguard SAS', $userId);

    $queryBus = $this->createStub(QueryBusPort::class);
    $this->configureQueryBus($queryBus, $orgResult);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Step "invite_members" is not the current pending step. Next step is "select_plan".');

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      transactionManager: $transactionManager,
    );
    $service->skipStep(
      userId: $userId,
      stepKey: OrganizationOnboardingStep::INVITE_MEMBERS,
    );
  }

  #[Test]
  public function testSkipStepCompletesFlowAndDispatchesEvent(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440141';
    $orgId = '550e8400-e29b-41d4-a716-446655440191';

    // Every step except the optional select_plan is already done; skipping it
    // is the last action needed to complete the flow.
    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440241',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::SELECT_PLAN,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [
        OrganizationOnboardingStep::CREATE_ORGANIZATION,
        OrganizationOnboardingStep::INVITE_MEMBERS,
        OrganizationOnboardingStep::CREATE_FIRST_FACILITY,
        OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT,
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
      stepKey: OrganizationOnboardingStep::SELECT_PLAN,
    );

    self::assertSame(OrganizationOnboardingState::COMPLETED, $state->state);
    self::assertNull($state->nextStep);
    self::assertContains(OrganizationOnboardingStep::SELECT_PLAN, $state->skippedSteps);
  }

  #[Test]
  public function testRollbackStackClearedWhenActionTargetsDifferentOrganization(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440142';
    $orgId = '550e8400-e29b-41d4-a716-446655440192';
    $staleOrgId = '550e8400-e29b-41d4-a716-446655440292';

    // The rollback stack references an org id that no longer matches the pinned
    // target (e.g. the org was recreated externally): the stale stack is dropped.
    $existingSession = OrganizationOnboardingSession::reconstitute(
      id: '550e8400-e29b-41d4-a716-446655440242',
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::SELECT_PLAN,
      blockedReason: null,
      targetOrganizationId: $orgId,
      targetOrganizationName: 'Fireguard SAS',
      completedSteps: [OrganizationOnboardingStep::CREATE_ORGANIZATION],
      skippedSteps: [],
      rollbackStack: [new DeleteOrganizationRollbackAction($staleOrgId)],
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
    $this->configureQueryBus($queryBus, $orgResult);

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
    );

    $state = $service->getFlow($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    self::assertSame($orgId, $state->targetOrganizationId);
    // The stale rollback action was cleared, so no rollback is offered.
    self::assertFalse($state->canRollback);
    self::assertNull($state->lastRollbackableStep);
  }

  #[Test]
  public function testGetFlowAdoptsMostRecentlyCreatedOrganization(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440143';
    $olderOrgId = '550e8400-e29b-41d4-a716-446655440193';
    $newerOrgId = '550e8400-e29b-41d4-a716-446655440293';
    $sessionId = '550e8400-e29b-41d4-a716-446655440243';

    /** @var OrganizationOnboardingSessionRepositoryPort&MockObject $sessionRepository */
    $sessionRepository = $this->createMock(OrganizationOnboardingSessionRepositoryPort::class);
    $sessionRepository->method('findByUserId')->willReturn(null);
    $sessionRepository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn($sessionId);

    // Two organizations were created during this onboarding session; the most
    // recently created one wins the adoption tiebreak.
    $olderOrg = $this->buildOrganizationResult($olderOrgId, 'Older SAS', $userId, new DateTimeImmutable('+1 hour'));
    $newerOrg = $this->buildOrganizationResult($newerOrgId, 'Newer SAS', $userId, new DateTimeImmutable('+2 hours'));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new PaginatedResult(items: [$olderOrg, $newerOrg], total: 2, limit: 100, offset: 0));

    $service = $this->buildService(
      sessionRepository: $sessionRepository,
      queryBus: $queryBus,
      uuidFactory: $uuidFactory,
    );

    $state = $service->getFlow($userId);

    self::assertSame(OrganizationOnboardingState::IN_PROGRESS, $state->state);
    // create_organization still requires explicit confirmation via executeStep.
    self::assertSame(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->nextStep);
    self::assertSame($newerOrgId, $state->targetOrganizationId);
  }

  private function buildRollbackableSession(string $userId, string $orgId, string $sessionId): OrganizationOnboardingSession
  {
    return OrganizationOnboardingSession::reconstitute(
      id: $sessionId,
      userId: $userId,
      flow: 'organization',
      state: OrganizationOnboardingState::IN_PROGRESS,
      nextStep: OrganizationOnboardingStep::SELECT_PLAN,
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
