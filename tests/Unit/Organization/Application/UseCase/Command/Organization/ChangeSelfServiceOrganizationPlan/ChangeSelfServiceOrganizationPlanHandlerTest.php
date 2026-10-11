<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ChangeSelfServiceOrganizationPlan;

use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\Internal\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{ChangeOrganizationPlanCommand, ChangeOrganizationPlanResult};
use Organization\Application\UseCase\Command\Organization\ChangeSelfServiceOrganizationPlan\{ChangeSelfServiceOrganizationPlanCommand, ChangeSelfServiceOrganizationPlanHandler};
use Organization\Domain\Exception\{PaidOrganizationPlanRequiresBillingException, PlanNotFoundException};
use Organization\Domain\Model\Plan\{Plan, PlanCreationOptions};
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

#[CoversClass(ChangeSelfServiceOrganizationPlanHandler::class)]
final class ChangeSelfServiceOrganizationPlanHandlerTest extends TestCase
{
  private const string PLAN = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function permissionDenialPrecedesPlanLookupAndAnyMutation(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->with('actor', 'organization', 'organization.settings.write')->willReturn(false);
    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->expects(self::never())->method('findById');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(OrganizationAccessDeniedException::class);
    new ChangeSelfServiceOrganizationPlanHandler($authorization, $plans, $commands)(new ChangeSelfServiceOrganizationPlanCommand('organization', self::PLAN, 'actor'));
  }

  #[Test]
  public function aPaidPlanCannotReachTheTrustedAssignment(): void
  {
    $plans = $this->createStub(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($this->plan(false));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(PaidOrganizationPlanRequiresBillingException::class);
    new ChangeSelfServiceOrganizationPlanHandler($this->authorization(), $plans, $commands)(new ChangeSelfServiceOrganizationPlanCommand('organization', self::PLAN, 'actor'));
  }

  #[Test]
  public function anUnknownPlanRetainsThePublicNotFoundMessage(): void
  {
    $this->expectException(PlanNotFoundException::class);
    $this->expectExceptionMessage('Plan not found.');
    new ChangeSelfServiceOrganizationPlanHandler($this->authorization(), $this->createStub(PlanRepositoryPort::class), $this->createStub(CommandBusPort::class))(new ChangeSelfServiceOrganizationPlanCommand('organization', self::PLAN, 'actor'));
  }

  #[Test]
  public function aFreePlanForwardsAcknowledgementToTheValidatedMutation(): void
  {
    $plans = $this->createStub(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($this->plan(true));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(new ChangeOrganizationPlanCommand('organization', self::PLAN, true))->willReturn(new ChangeOrganizationPlanResult('organization', self::PLAN));
    $result = new ChangeSelfServiceOrganizationPlanHandler($this->authorization(), $plans, $commands)(new ChangeSelfServiceOrganizationPlanCommand('organization', self::PLAN, 'actor', true));
    self::assertSame('organization', $result->organizationId);
    self::assertSame(self::PLAN, $result->planId);
  }

  private function authorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return $authorization;
  }

  private function plan(bool $default): Plan
  {
    return Plan::create(new PlanId(self::PLAN), new PlanKey($default ? 'free' : 'pro'), 'Example', [], new PlanCreationOptions(isDefault: $default));
  }
}
