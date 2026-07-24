<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Plan\UpdatePlan;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Plan\UpdatePlan\{UpdatePlanCommand, UpdatePlanHandler};
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test UpdatePlanHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdatePlanHandlerTest extends TestCase
{
  private const string PLAN_ID = '11111111-1111-4111-8111-111111111111';

  private const string OTHER_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function itThrowsWhenPlanDoesNotExist(): void
  {
    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn(null);
    $plans->expects(self::never())->method('save');

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $this->expectException(PlanNotFoundException::class);

    $handler(new UpdatePlanCommand(self::PLAN_ID, name: 'Anything'));
  }

  #[Test]
  public function itRenamesAndReturnsTheUpdatedPlanId(): void
  {
    $plan = $this->makePlan(name: 'Starter', description: 'Initial description');

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $result = $handler(new UpdatePlanCommand(self::PLAN_ID, name: 'Premium', description: 'New description'));

    self::assertSame(self::PLAN_ID, $result->planId);
    self::assertSame('Premium', $plan->name());
    self::assertSame('New description', $plan->description());
  }

  #[Test]
  public function itKeepsTheDescriptionWhenOnlyTheNameIsProvided(): void
  {
    $plan = $this->makePlan(name: 'Starter', description: 'Initial description');

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, name: 'Premium'));

    self::assertSame('Premium', $plan->name());
    self::assertSame('Initial description', $plan->description());
  }

  #[Test]
  public function itChangesTheLimits(): void
  {
    $plan = $this->makePlan();

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, limits: ['members' => 5, 'facilities' => 10]));

    self::assertSame(['members' => 5, 'facilities' => 10], $plan->limits());
  }

  #[Test]
  public function itActivatesThePlan(): void
  {
    $plan = $this->makePlan(isActive: false);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, isActive: true));

    self::assertTrue($plan->isActive());
  }

  #[Test]
  public function itDeactivatesThePlan(): void
  {
    $plan = $this->makePlan(isActive: true);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, isActive: false));

    self::assertFalse($plan->isActive());
  }

  #[Test]
  public function itChangesTheSortOrder(): void
  {
    $plan = $this->makePlan(sortOrder: 0);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, sortOrder: 42));

    self::assertSame(42, $plan->sortOrder());
  }

  #[Test]
  public function itMarksDefaultAndClearsThePreviousDefault(): void
  {
    $plan = $this->makePlan(isDefault: false);
    $current = $this->makePlan(id: self::OTHER_ID, isDefault: true);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->method('findDefault')->willReturn($current);
    $plans->expects(self::exactly(2))->method('save');

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, isDefault: true));

    self::assertTrue($plan->isDefault());
    self::assertFalse($current->isDefault());
  }

  #[Test]
  public function itMarksDefaultWithoutClearingWhenItIsAlreadyTheCurrentDefault(): void
  {
    $plan = $this->makePlan(isDefault: true);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->method('findDefault')->willReturn($plan);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, isDefault: true));

    self::assertTrue($plan->isDefault());
  }

  #[Test]
  public function itMarksDefaultWhenNoDefaultExists(): void
  {
    $plan = $this->makePlan(isDefault: false);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->method('findDefault')->willReturn(null);
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, isDefault: true));

    self::assertTrue($plan->isDefault());
  }

  #[Test]
  public function itUnmarksDefaultWithoutTouchingTheCurrentDefault(): void
  {
    $plan = $this->makePlan(isDefault: true);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findById')->willReturn($plan);
    $plans->expects(self::never())->method('findDefault');
    $plans->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler($plans, $this->transactionManager());

    $handler(new UpdatePlanCommand(self::PLAN_ID, isDefault: false));

    self::assertFalse($plan->isDefault());
  }

  /**
   * @param array<string, int> $limits
   */
  private function makePlan(
    string $id = self::PLAN_ID,
    string $name = 'Starter',
    ?string $description = 'Initial description',
    array $limits = [],
    bool $isActive = true,
    bool $isDefault = false,
    int $sortOrder = 0,
  ): Plan {
    return Plan::create(
      PlanId::fromString($id),
      new PlanKey('starter'),
      $name,
      $limits,
      $description,
      $isActive,
      $isDefault,
      $sortOrder,
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
