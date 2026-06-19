<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan\{
  ChangeOrganizationPlanCommand,
  ChangeOrganizationPlanHandler,
  ChangeOrganizationPlanResult
};
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

#[CoversClass(ChangeOrganizationPlanHandler::class)]
final class ChangeOrganizationPlanHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

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

    $handler = new ChangeOrganizationPlanHandler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      transactionManager: $transactionManager,
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
  public function testThrowsWhenPlanNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());
    $organizationRepository->expects(self::never())->method('save');

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn(null);

    $handler = new ChangeOrganizationPlanHandler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      transactionManager: $this->createStub(TransactionManagerPort::class),
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

    $handler = new ChangeOrganizationPlanHandler(
      organizationRepository: $organizationRepository,
      planRepository: $planRepository,
      transactionManager: $this->createStub(TransactionManagerPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ChangeOrganizationPlanCommand(
      organizationId: self::ORGANIZATION_ID,
      planId: self::PLAN_ID,
    ));
  }

  private function organization(): Organization
  {
    return Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function plan(bool $isActive): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50],
      isActive: $isActive,
    );
  }
}
