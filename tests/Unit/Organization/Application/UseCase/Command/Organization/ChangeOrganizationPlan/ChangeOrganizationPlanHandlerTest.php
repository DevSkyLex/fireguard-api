<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use DateTimeImmutable;
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{
  ChangeOrganizationPlanCommand,
  ChangeOrganizationPlanHandler,
  ChangeOrganizationPlanResult
};
use Organization\Domain\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Domain\Exception\{OrganizationPlanUsageExceededException, PlanNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationQuotaResource, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

use function in_array;

#[CoversClass(ChangeOrganizationPlanHandler::class)]
final class ChangeOrganizationPlanHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  private const string PREVIOUS_PLAN_ID = '11111111-1111-4111-8111-111111111111';

  #[Test]
  public function testChangesPlanAndPersistsOrganization(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Organization $saved): bool => null !== $saved->planId()
        && self::PLAN_ID === (string) $saved->planId()));

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof OrganizationPlanChangedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::PLAN_ID === $event->planId
        && null === $event->previousPlanId));

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));

    self::assertInstanceOf(ChangeOrganizationPlanResult::class, $result);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function testDispatchesEventWithPreviousPlanId(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')
      ->willReturn($this->organization(PlanId::fromString(self::PREVIOUS_PLAN_ID)));
    $organizationRepository->expects(self::once())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof OrganizationPlanChangedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::PLAN_ID === $event->planId
        && self::PREVIOUS_PLAN_ID === $event->previousPlanId));

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));
  }

  #[Test]
  public function testThrowsWhenPlanNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());
    $organizationRepository->expects(self::never())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(PlanNotFoundException::class);

    $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));
  }

  #[Test]
  public function testThrowsWhenPlanIsInactive(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());
    $organizationRepository->expects(self::never())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(false));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));
  }

  #[Test]
  public function testRefusesUnacknowledgedChangeWhenUsageExceedsPlanLimits(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')
      ->willReturn($this->organization(PlanId::fromString(self::PREVIOUS_PLAN_ID)));
    $organizationRepository->expects(self::never())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    // Pins the refusal happening BEFORE the mutation phase: the transaction
    // is never even opened, so save::never cannot be vacuously satisfied.
    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $this->quotaWithUsage(members: 60),
      notificationPort: $notificationPort,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationPlanUsageExceededException::class);
    $this->expectExceptionMessage('members 60/50');

    $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));
  }

  #[Test]
  public function testAcknowledgedOveruseAppliesPlanRecordsOverageAndNotifiesOwner(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')
      ->willReturn($this->organization(PlanId::fromString(self::PREVIOUS_PLAN_ID)));
    $organizationRepository->expects(self::once())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof OrganizationPlanChangedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::PLAN_ID === $event->planId
        && self::PREVIOUS_PLAN_ID === $event->previousPlanId
        && ['members', 'facilities'] === $event->overQuotaResources));

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => NotificationType::ORGANIZATION_PLAN_OVER_QUOTA === $request->type
        && self::OWNER_USER_ID === $request->recipientUserId
        && in_array(NotificationChannel::EMAIL, $request->channels, true)
        && in_array(NotificationChannel::MERCURE, $request->channels, true)
        && self::ORGANIZATION_ID === ($request->payload['organizationId'] ?? null)
        && ['members', 'facilities'] === ($request->payload['overQuotaResources'] ?? null)
        && self::ORGANIZATION_ID === $request->organizationId))
      ->willReturn($this->sentNotification());

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $this->quotaWithUsage(members: 60, facilities: 10, equipment: 999),
      notificationPort: $notificationPort,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
      userRepository: $this->userRepositoryWithOwner(true),
    );

    $result = $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
      acknowledgeOveruse: true,
    ));

    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function testMissingOwnerDegradesNotificationToMercureOnly(): void
  {
    // A dangling ownerUserId (owner account deleted) must not abort the whole
    // notification: the EMAIL channel is dropped so the in-app record persists.
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')
      ->willReturn($this->organization(PlanId::fromString(self::PREVIOUS_PLAN_ID)));
    $organizationRepository->expects(self::once())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => NotificationType::ORGANIZATION_PLAN_OVER_QUOTA === $request->type
        && [NotificationChannel::MERCURE] === $request->channels
        && null === $request->recipientEmail
        && self::OWNER_USER_ID === $request->recipientUserId
        && self::ORGANIZATION_ID === $request->organizationId))
      ->willReturn($this->sentNotification());

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $this->quotaWithUsage(members: 60),
      notificationPort: $notificationPort,
      transactionManager: $transactionManager,
      userRepository: $this->userRepositoryWithOwner(false),
    );

    $result = $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
      acknowledgeOveruse: true,
    ));

    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function testNotificationFailureNeverFailsAcknowledgedPlanChange(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')
      ->willReturn($this->organization(PlanId::fromString(self::PREVIOUS_PLAN_ID)));
    $organizationRepository->expects(self::once())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('Notification delivery failed.'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $this->quotaWithUsage(members: 60),
      notificationPort: $notificationPort,
      logger: $logger,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
      acknowledgeOveruse: true,
    ));

    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function testUpgradeWithoutViolationsSendsNoNotification(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')
      ->willReturn($this->organization(PlanId::fromString(self::PREVIOUS_PLAN_ID)));
    $organizationRepository->expects(self::once())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $event): bool => $event instanceof OrganizationPlanChangedEvent
        && [] === $event->overQuotaResources));

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $this->quotaWithUsage(members: 10, facilities: 2),
      notificationPort: $notificationPort,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));
  }

  #[Test]
  public function testSamePlanReapplyIsExemptFromTheUsageGuard(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')
      ->willReturn($this->organization(PlanId::fromString(self::PLAN_ID)));
    $organizationRepository->expects(self::once())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(true));

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('getUsage');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    $handler = $this->handler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $quota,
      notificationPort: $notificationPort,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));

    self::assertSame(self::PLAN_ID, $result->planId);
  }

  private function handler(
    OrganizationRepositoryPort $organizationRepository,
    PlanRepositoryPort $planRepository,
    ?OrganizationQuotaPort $quota = null,
    ?NotificationPort $notificationPort = null,
    ?LoggerPort $logger = null,
    ?TransactionManagerPort $transactionManager = null,
    ?EventDispatcherPort $eventDispatcher = null,
    ?UserRepositoryPort $userRepository = null,
  ): ChangeOrganizationPlanHandler {
    return new ChangeOrganizationPlanHandler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $quota ?? $this->createStub(OrganizationQuotaPort::class),
      userRepository: $userRepository ?? $this->createStub(UserRepositoryPort::class),
      notificationPort: $notificationPort ?? $this->createStub(NotificationPort::class),
      logger: $logger ?? $this->createStub(LoggerPort::class),
      transactionManager: $transactionManager ?? $this->createStub(TransactionManagerPort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }

  private function userRepositoryWithOwner(bool $exists): UserRepositoryPort
  {
    $userRepository = $this->createStub(UserRepositoryPort::class);
    $userRepository->method('findById')
      ->willReturn($exists ? UserTestFactory::createActive(self::OWNER_USER_ID) : null);

    return $userRepository;
  }

  private function quotaWithUsage(int $members = 0, int $facilities = 0, int $equipment = 0, int $inspections = 0): OrganizationQuotaPort
  {
    $quota = $this->createStub(OrganizationQuotaPort::class);
    $quota->method('getUsage')
      ->willReturnCallback(static fn (string $organizationId, OrganizationQuotaResource $resource): int => match ($resource) {
        OrganizationQuotaResource::MEMBERS => $members,
        OrganizationQuotaResource::FACILITIES => $facilities,
        OrganizationQuotaResource::EQUIPMENT => $equipment,
        OrganizationQuotaResource::INSPECTIONS => $inspections,
      });

    return $quota;
  }

  private function sentNotification(): SentNotification
  {
    return new SentNotification(
      id: '550e8400-e29b-41d4-a716-446655441924',
      type: NotificationType::ORGANIZATION_PLAN_OVER_QUOTA,
      subject: 'Fireguard Test exceeds its new plan limits',
      body: 'Usage exceeds the new plan limits.',
      channels: ['email', 'mercure'],
      payload: [],
      channelDelivery: ['email' => true, 'mercure' => true],
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      recipientUserId: self::OWNER_USER_ID,
    );
  }

  private function organization(?PlanId $planId = null): Organization
  {
    return Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      createdByUserId: self::OWNER_USER_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      planId: $planId,
    );
  }

  private function plan(bool $isActive): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50, 'facilities' => 3],
      isActive: $isActive,
    );
  }
}
