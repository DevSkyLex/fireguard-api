<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Plan\DeletePlan;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Plan\DeletePlan\{
  DeletePlanCommand,
  DeletePlanHandler
};
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test DeletePlanHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DeletePlanHandlerTest extends TestCase
{
  private const string PLAN_ID = '11111111-1111-4111-8111-111111111111';

  #[Test]
  public function itDeletesANonDefaultPlanWithinATransaction(): void
  {
    $plan = $this->plan(isDefault: false);

    $repository = $this->createMock(PlanRepositoryPort::class);
    $repository->method('findById')->willReturn($plan);
    $repository->expects(self::once())
      ->method('delete')
      ->with(PlanId::fromString(self::PLAN_ID));

    $handler = new DeletePlanHandler($repository, $this->transactionManager());

    $result = $handler(new DeletePlanCommand(self::PLAN_ID));

    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function itThrowsWhenThePlanDoesNotExist(): void
  {
    $repository = $this->createMock(PlanRepositoryPort::class);
    $repository->method('findById')->willReturn(null);
    $repository->expects(self::never())->method('delete');

    $handler = new DeletePlanHandler($repository, $this->transactionManager());

    $this->expectException(PlanNotFoundException::class);

    $handler(new DeletePlanCommand(self::PLAN_ID));
  }

  #[Test]
  public function itRefusesToDeleteTheDefaultPlan(): void
  {
    $plan = $this->plan(isDefault: true);

    $repository = $this->createMock(PlanRepositoryPort::class);
    $repository->method('findById')->willReturn($plan);
    $repository->expects(self::never())->method('delete');

    $handler = new DeletePlanHandler($repository, $this->transactionManager());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The default plan cannot be deleted.');

    $handler(new DeletePlanCommand(self::PLAN_ID));
  }

  private function plan(bool $isDefault): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: [],
      isDefault: $isDefault,
    );
  }

  private function transactionManager(): TransactionManagerPort
  {
    $manager = $this->createStub(TransactionManagerPort::class);
    $manager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return $manager;
  }
}
