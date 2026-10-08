<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Service\NotificationService;
use Notification\Application\UseCase\Command\Notification\SendNotification\SendNotificationHandler;
use Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter;
use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{ChangeOrganizationPlanCommand, ChangeOrganizationPlanHandler};
use Organization\Domain\Event\Plan\OrganizationPlanChangedEvent;
use Organization\Domain\Model\Organization\{Organization, RestoredOrganizationCore};
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Tests\Support\Factory\UserTestFactory;
use Tests\Support\Notification\EmailPipelineTestTrait;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

#[CoversClass(ChangeOrganizationPlanHandler::class)]
#[CoversClass(NotificationService::class)]
#[CoversClass(SendNotificationHandler::class)]
#[CoversClass(EmailNotificationChannelAdapter::class)]
final class ChangeOrganizationPlanEmailTest extends TestCase
{
  use EmailPipelineTestTrait;

  #[Test]
  public function organizationMarkupIsTextInRenderedOverQuotaEmail(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655440102';
    $planId = '550e8400-e29b-41d4-a716-446655440103';
    $name = '<a href="https://untrusted.invalid">Review & "confirm"</a>';
    $plainBody = 'The plan applied to ' . $name . ' has lower limits than its current usage (members 60/50). Existing data is preserved, but new creations stay blocked until usage fits the plan.';
    $organization = Organization::reconstitute(core: new RestoredOrganizationCore(
      id: new OrganizationId($organizationId),
      name: new OrganizationName($name),
      createdByUserId: $ownerUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));
    $plan = Plan::create(
      id: new PlanId($planId),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50],
    );
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->with(new OrganizationId($organizationId))->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save')->with($organization);
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->expects(self::once())->method('findById')->with(new PlanId($planId))->willReturn($plan);
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())->method('getUsage')->with($organizationId, OrganizationQuotaResource::MEMBERS)->willReturn(60);
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())->method('findById')->with(new UserId($ownerUserId))
      ->willReturn(UserTestFactory::createActive($ownerUserId, 'owner@example.com'));
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')
      ->with(self::callback(static fn (OrganizationPlanChangedEvent $event): bool => $organizationId === $event->organizationId
        && $planId === $event->planId
        && null === $event->previousPlanId
        && ['members'] === $event->overQuotaResources));
    $handler = new ChangeOrganizationPlanHandler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $quota,
      userRepository: $userRepository,
      notificationPort: $this->emailPipeline($ownerUserId, 'owner@example.com', 'organization', $name . ' exceeds its new plan limits', resolveEmail: false),
      logger: $logger,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler(new ChangeOrganizationPlanCommand(
      organizationId: $organizationId,
      planId: $planId,
      acknowledgeOveruse: true,
    ));

    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($planId, $result->planId);
    self::assertSame($planId, (string) $organization->planId());
    $this->assertEmailPipelinePreservesPlainText(
      NotificationType::ORGANIZATION_PLAN_OVER_QUOTA,
      $plainBody,
      ['organizationId' => $organizationId, 'overQuotaResources' => ['members']],
      $ownerUserId,
      $organizationId,
    );
  }
}
