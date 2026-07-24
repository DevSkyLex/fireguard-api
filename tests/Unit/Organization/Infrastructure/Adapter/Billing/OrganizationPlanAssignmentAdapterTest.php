<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Billing;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\ChangeOrganizationPlanCommand;
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Organization\Infrastructure\Adapter\Billing\OrganizationPlanAssignmentAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

#[CoversClass(OrganizationPlanAssignmentAdapter::class)]
final class OrganizationPlanAssignmentAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  private const string PLAN_KEY = 'pro';

  #[Test]
  public function testAssignPlanByKeyDispatchesAcknowledgedChangePlanCommand(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findByKey')->willReturn($this->plan());

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof ChangeOrganizationPlanCommand
        && self::ORGANIZATION_ID === $command->organizationId
        && self::PLAN_ID === $command->planId
        && true === $command->acknowledgeOveruse));

    $adapter = new OrganizationPlanAssignmentAdapter(
      commandBus: $commandBus,
      planRepository: $planRepository,
    );

    $adapter->assignPlanByKey(self::ORGANIZATION_ID, self::PLAN_KEY);
  }

  #[Test]
  public function testAssignPlanByKeyThrowsWhenPlanKeyIsUnknown(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findByKey')->willReturn(null);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $adapter = new OrganizationPlanAssignmentAdapter(
      commandBus: $commandBus,
      planRepository: $planRepository,
    );

    $this->expectException(PlanNotFoundException::class);

    $adapter->assignPlanByKey(self::ORGANIZATION_ID, self::PLAN_KEY);
  }

  private function plan(): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey(self::PLAN_KEY),
      name: 'Pro',
      limits: ['members' => 50],
    );
  }
}
