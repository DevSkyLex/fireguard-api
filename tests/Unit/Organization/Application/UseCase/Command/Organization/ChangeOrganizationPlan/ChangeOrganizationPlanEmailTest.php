<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use DateTimeImmutable;
use Notification\Application\Contract\Event\NotificationDeliveryRequestedEvent;
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Service\NotificationService;
use Notification\Application\UseCase\Command\Notification\SendNotification\SendNotificationHandler;
use Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter;
use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\Internal\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{ChangeOrganizationPlanCommand, ChangeOrganizationPlanHandler};
use Organization\Domain\Model\Organization\{Organization, RestoredOrganizationCore};
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Tests\Support\Notification\EmailPipelineTestTrait;

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
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $request = null;
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (object $event) use (&$request): void {
      if ($event instanceof NotificationDeliveryRequestedEvent) {
        $request = $event->request;
      }
    });
    $handler = new ChangeOrganizationPlanHandler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      quota: $quota,
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
    self::assertInstanceOf(\Notification\Application\Contract\Notification\SendNotificationRequest::class, $request);
    $this->emailPipeline($ownerUserId, 'owner@example.com', 'organization', $name . ' exceeds its new plan limits', resolveEmail: true)->send($request);
    $this->assertEmailPipelinePreservesPlainText(
      NotificationType::ORGANIZATION_PLAN_OVER_QUOTA,
      $plainBody,
      ['organizationId' => $organizationId, 'organizationName' => $name, 'overQuotaResources' => ['members'], 'violations' => ['members' => ['usage' => 60, 'limit' => 50]]],
      $ownerUserId,
      $organizationId,
    );
  }
}
