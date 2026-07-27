<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Plan;

use InvalidArgumentException;
use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Plan\CreatePlan\{CreatePlanCommand, CreatePlanHandler};
use Organization\Application\UseCase\Command\Plan\DeletePlan\{DeletePlanCommand, DeletePlanHandler};
use Organization\Application\UseCase\Command\Plan\UpdatePlan\{UpdatePlanCommand, UpdatePlanHandler};
use Organization\Domain\Exception\{PlanKeyAlreadyExistsException, PlanNotFoundException};
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};

/**
 * Test PlanUseCaseHandlersTest.
 *
 * Covers the plan write handlers, in particular the "only one default
 * plan" invariant each of them maintains inside its transaction.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreatePlanHandler::class)]
#[CoversClass(UpdatePlanHandler::class)]
#[CoversClass(DeletePlanHandler::class)]
final class PlanUseCaseHandlersTest extends TestCase
{
  // #region Constants
  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  private const string OTHER_PLAN_ID = '33333333-3333-4333-8333-333333333333';
  // #endregion

  // #region Methods
  #[Test]
  public function testCreateSavesTheNewPlanAndReturnsItsGeneratedId(): void
  {
    /** @var PlanRepositoryPort&MockObject $planRepository */
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->method('findByKey')->willReturn(null);
    $planRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Plan $plan): bool => 'pro' === (string) $plan->key()
        && 'Pro' === $plan->name()));

    $handler = new CreatePlanHandler(
      planRepository: $planRepository,
      uuidFactory: $this->uuidFactory(),
      transactionManager: $this->transactionManager(),
    );

    $result = $handler(new CreatePlanCommand(key: 'pro', name: 'Pro', limits: ['members' => 50]));

    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function testCreateRejectsAnAlreadyUsedKey(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findByKey')->willReturn($this->plan());

    $handler = new CreatePlanHandler(
      planRepository: $planRepository,
      uuidFactory: $this->uuidFactory(),
      transactionManager: $this->transactionManager(),
    );

    $this->expectException(PlanKeyAlreadyExistsException::class);

    $handler(new CreatePlanCommand(key: 'pro', name: 'Pro', limits: []));
  }

  #[Test]
  public function testCreatingADefaultPlanClearsTheExistingDefault(): void
  {
    $existingDefault = $this->plan(id: self::OTHER_PLAN_ID, isDefault: true);

    /** @var PlanRepositoryPort&MockObject $planRepository */
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->method('findByKey')->willReturn(null);
    $planRepository->method('findDefault')->willReturn($existingDefault);
    $planRepository->expects(self::exactly(2))->method('save');

    $handler = new CreatePlanHandler(
      planRepository: $planRepository,
      uuidFactory: $this->uuidFactory(),
      transactionManager: $this->transactionManager(),
    );

    $handler(new CreatePlanCommand(key: 'pro', name: 'Pro', limits: [], isDefault: true));

    self::assertFalse($existingDefault->isDefault());
  }

  #[Test]
  public function testCreatingADefaultPlanLeavesTheDefaultAloneWhenThereIsNone(): void
  {
    /** @var PlanRepositoryPort&MockObject $planRepository */
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->method('findByKey')->willReturn(null);
    $planRepository->method('findDefault')->willReturn(null);
    $planRepository->expects(self::once())->method('save');

    $handler = new CreatePlanHandler(
      planRepository: $planRepository,
      uuidFactory: $this->uuidFactory(),
      transactionManager: $this->transactionManager(),
    );

    $handler(new CreatePlanCommand(key: 'pro', name: 'Pro', limits: [], isDefault: true));
  }

  #[Test]
  public function testUpdateAppliesEveryProvidedField(): void
  {
    $plan = $this->plan();

    /** @var PlanRepositoryPort&MockObject $planRepository */
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($plan);
    $planRepository->expects(self::once())->method('save')->with($plan);

    $handler = new UpdatePlanHandler(
      planRepository: $planRepository,
      transactionManager: $this->transactionManager(),
    );

    $result = $handler(new UpdatePlanCommand(
      planId: self::PLAN_ID,
      name: 'Pro renamed',
      description: 'Renamed tier',
      limits: ['members' => 80],
      isActive: false,
      isDefault: false,
      sortOrder: 9,
    ));

    self::assertSame(self::PLAN_ID, $result->planId);
    self::assertSame('Pro renamed', $plan->name());
    self::assertSame('Renamed tier', $plan->description());
    self::assertSame(['members' => 80], $plan->limits());
    self::assertFalse($plan->isActive());
    self::assertFalse($plan->isDefault());
    self::assertSame(9, $plan->sortOrder());
  }

  #[Test]
  public function testUpdateLeavesUntouchedFieldsAlone(): void
  {
    $plan = $this->plan();

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($plan);

    $handler = new UpdatePlanHandler(
      planRepository: $planRepository,
      transactionManager: $this->transactionManager(),
    );

    $handler(new UpdatePlanCommand(planId: self::PLAN_ID));

    self::assertSame('Pro', $plan->name());
    self::assertTrue($plan->isActive());
  }

  #[Test]
  public function testUpdatePromotingToDefaultClearsTheExistingDefault(): void
  {
    $plan = $this->plan();
    $existingDefault = $this->plan(id: self::OTHER_PLAN_ID, isDefault: true);

    /** @var PlanRepositoryPort&MockObject $planRepository */
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($plan);
    $planRepository->method('findDefault')->willReturn($existingDefault);
    $planRepository->expects(self::exactly(2))->method('save');

    $handler = new UpdatePlanHandler(
      planRepository: $planRepository,
      transactionManager: $this->transactionManager(),
    );

    $handler(new UpdatePlanCommand(planId: self::PLAN_ID, isDefault: true));

    self::assertTrue($plan->isDefault());
    self::assertFalse($existingDefault->isDefault());
  }

  #[Test]
  public function testUpdateThrowsWhenThePlanIsUnknown(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn(null);

    $handler = new UpdatePlanHandler(
      planRepository: $planRepository,
      transactionManager: $this->transactionManager(),
    );

    $this->expectException(PlanNotFoundException::class);

    $handler(new UpdatePlanCommand(planId: self::PLAN_ID));
  }

  #[Test]
  public function testDeleteRemovesANonDefaultPlan(): void
  {
    /** @var PlanRepositoryPort&MockObject $planRepository */
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan());
    $planRepository->expects(self::once())->method('delete');

    $handler = new DeletePlanHandler(
      planRepository: $planRepository,
      transactionManager: $this->transactionManager(),
    );

    $result = $handler(new DeletePlanCommand(self::PLAN_ID));

    self::assertSame(self::PLAN_ID, $result->planId);
  }

  #[Test]
  public function testDeleteRefusesToRemoveTheDefaultPlan(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(isDefault: true));

    $handler = new DeletePlanHandler(
      planRepository: $planRepository,
      transactionManager: $this->transactionManager(),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The default plan cannot be deleted.');

    $handler(new DeletePlanCommand(self::PLAN_ID));
  }

  #[Test]
  public function testDeleteThrowsWhenThePlanIsUnknown(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn(null);

    $handler = new DeletePlanHandler(
      planRepository: $planRepository,
      transactionManager: $this->transactionManager(),
    );

    $this->expectException(PlanNotFoundException::class);

    $handler(new DeletePlanCommand(self::PLAN_ID));
  }

  private function uuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn(self::PLAN_ID);

    return new UuidFactory($generator);
  }

  private function transactionManager(): TransactionManagerPort
  {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transactionManager;
  }

  private function plan(string $id = self::PLAN_ID, bool $isDefault = false): Plan
  {
    return Plan::create(
      id: PlanId::fromString($id),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50],
      description: 'Pro tier',
      isActive: true,
      isDefault: $isDefault,
      sortOrder: 3,
    );
  }
  // #endregion
}
