<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use DateTimeImmutable;
use Notification\Application\Contract\Event\NotificationDeliveryRequestedEvent;
use Notification\Application\Contract\Notification\{NotificationChannel, NotificationType};
use Organization\Application\Contract\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Application\Contract\Exception\OrganizationNotFoundException;
use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\Internal\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{ChangeOrganizationPlanCommand, ChangeOrganizationPlanHandler};
use Organization\Domain\Exception\{OrganizationPlanUsageExceededException, PlanNotAvailableException, PlanNotFoundException};
use Organization\Domain\Model\Organization\{Organization, RestoredOrganizationCore, RestoredOrganizationProfile};
use Organization\Domain\Model\Plan\{Plan, PlanCreationOptions};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

#[CoversClass(ChangeOrganizationPlanHandler::class)]
final class ChangeOrganizationPlanHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  private const string PREVIOUS_PLAN_ID = '11111111-1111-4111-8111-111111111111';

  #[Test]
  public function persistsAndRecordsTheEventInsideItsOwningTransaction(): void
  {
    $state = new class () {
      public bool $active = false;

      public bool $saved = false;
    };
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());
    $organizations->expects(self::once())->method('save')->willReturnCallback(static function (Organization $organization) use ($state): void {
      self::assertTrue($state->active);
      self::assertSame(self::PLAN_ID, (string) $organization->planId());
      $state->saved = true;
    });
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::once())->method('transactional')->willReturnCallback(static function (callable $operation) use ($state): mixed {
      $state->active = true;

      try {
        return $operation();
      } finally {
        $state->active = false;
      }
    });
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->willReturnCallback(static function (object $event) use ($state): void {
      self::assertTrue($state->active);
      self::assertTrue($state->saved);
      self::assertInstanceOf(OrganizationPlanChangedEvent::class, $event);
      self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
      self::assertSame(self::PLAN_ID, $event->planId);
      self::assertNull($event->previousPlanId);
    });
    $result = $this->handler($organizations, $transactions, $events)(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID));
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function acknowledgedOveruseRecordsItsImmutableNotificationWithoutReadingAuthOrSendingChannels(): void
  {
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization(self::PREVIOUS_PLAN_ID));
    $organizations->expects(self::once())->method('save');
    $captured = [];
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (object $event) use (&$captured): void { $captured[] = $event; });
    $result = $this->handler($organizations, $this->transactions(), $events, usage: 60)(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID, true));
    self::assertSame(self::PLAN_ID, $result->planId);
    self::assertInstanceOf(OrganizationPlanChangedEvent::class, $captured[0]);
    self::assertSame(self::PREVIOUS_PLAN_ID, $captured[0]->previousPlanId);
    self::assertSame(['members'], $captured[0]->overQuotaResources);
    self::assertInstanceOf(NotificationDeliveryRequestedEvent::class, $captured[1]);
    $request = $captured[1]->request;
    self::assertSame(NotificationType::ORGANIZATION_PLAN_OVER_QUOTA, $request->type);
    self::assertSame(self::OWNER_USER_ID, $request->recipientUserId);
    self::assertNull($request->recipientEmail);
    self::assertSame([NotificationChannel::MERCURE, NotificationChannel::EMAIL], $request->channels);
    self::assertSame(self::ORGANIZATION_ID, $request->organizationId);
    self::assertSame('Fireguard Test', $request->payload['organizationName']);
    self::assertSame(['members' => ['usage' => 60, 'limit' => 50]], $request->payload['violations']);
  }

  #[Test]
  public function refusesUnacknowledgedOveruseBeforeOpeningATransaction(): void
  {
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization(self::PREVIOUS_PLAN_ID));
    $organizations->expects(self::never())->method('save');
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::never())->method('transactional');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException(OrganizationPlanUsageExceededException::class);
    $this->expectExceptionMessage('members 60/50');
    $this->handler($organizations, $transactions, $events, usage: 60)(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID));
  }

  #[Test]
  public function reapplyingTheSamePlanDoesNotMeasureUsageOrEmitConsequences(): void
  {
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization(self::PLAN_ID));
    $organizations->expects(self::once())->method('save');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('getUsage');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->handler($organizations, $this->transactions(), $events, quota: $quota)(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID));
  }

  #[Test]
  public function refusesAMissingOrganization(): void
  {
    $this->expectException(OrganizationNotFoundException::class);
    $this->handler($this->createStub(OrganizationRepositoryPort::class), $this->transactions(), $this->createStub(EventDispatcherPort::class))(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID));
  }

  #[Test]
  public function refusesAMissingPlan(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());
    $this->expectException(PlanNotFoundException::class);
    $this->handler($organizations, $this->transactions(), $this->createStub(EventDispatcherPort::class), plans: $this->createStub(PlanRepositoryPort::class))(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID));
  }

  #[Test]
  public function refusesAnInactivePlan(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());
    $plans = $this->createStub(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($this->plan(false));
    $this->expectException(PlanNotAvailableException::class);
    $this->handler($organizations, $this->transactions(), $this->createStub(EventDispatcherPort::class), plans: $plans)(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID));
  }

  #[Test]
  public function failsTheMutationWhenItsDurableEnqueueFails(): void
  {
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($this->organization());
    $organizations->expects(self::once())->method('save');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->willThrowException(new RuntimeException('Outbox unavailable'));
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Outbox unavailable');
    $this->handler($organizations, $this->transactions(), $events)(new ChangeOrganizationPlanCommand(self::ORGANIZATION_ID, self::PLAN_ID));
  }

  private function handler(OrganizationRepositoryPort $organizations, TransactionManagerPort $transactions, EventDispatcherPort $events, int $usage = 0, ?OrganizationQuotaPort $quota = null, ?PlanRepositoryPort $plans = null): ChangeOrganizationPlanHandler
  {
    if (null === $plans) {
      $plans = $this->createStub(PlanRepositoryPort::class);
      $plans->method('findById')->willReturn($this->plan());
    }
    if (null === $quota) {
      $quota = $this->createStub(OrganizationQuotaPort::class);
      $quota->method('getUsage')->willReturnCallback(static fn (string $id, OrganizationQuotaResource|string $resource): int => (OrganizationQuotaResource::MEMBERS === $resource || 'members' === $resource) ? $usage : 0);
    }

    return new ChangeOrganizationPlanHandler($organizations, $plans, $quota, $transactions, $events, quotaCatalog: \Tests\Support\OrganizationCatalogTestFactory::quota());
  }

  private function transactions(): TransactionManagerPort
  {
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());

    return $transactions;
  }

  private function organization(?string $planId = null): Organization
  {
    return Organization::reconstitute(
      core: new RestoredOrganizationCore(OrganizationId::fromString(self::ORGANIZATION_ID), new OrganizationName('Fireguard Test'), self::OWNER_USER_ID, true, new DateTimeImmutable('2026-01-01')),
      profile: new RestoredOrganizationProfile(planId: null === $planId ? null : PlanId::fromString($planId)),
    );
  }

  private function plan(bool $active = true): Plan
  {
    return Plan::create(PlanId::fromString(self::PLAN_ID), new PlanKey('pro'), 'Pro', ['members' => 50], new PlanCreationOptions(isActive: $active));
  }
}
