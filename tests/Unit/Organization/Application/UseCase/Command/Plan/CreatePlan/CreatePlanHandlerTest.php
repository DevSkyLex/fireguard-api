<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Plan\CreatePlan;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Command\Plan\CreatePlan\{
  CreatePlanCommand,
  CreatePlanHandler,
  CreatePlanResult
};
use Organization\Domain\Exception\PlanKeyAlreadyExistsException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test CreatePlanHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreatePlanHandlerTest extends TestCase
{
  private const string PLAN_ID = '11111111-1111-4111-8111-111111111111';

  private const string EXISTING_DEFAULT_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function itCreatesANonDefaultPlanAndReturnsItsIdentifier(): void
  {
    $saved = null;

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findByKey')->willReturn(null);
    $plans->expects(self::never())->method('findDefault');
    $plans->expects(self::once())
      ->method('save')
      ->willReturnCallback(static function (Plan $plan) use (&$saved): void {
        $saved = $plan;
      });

    $handler = new CreatePlanHandler($plans, $this->uuidFactory(), $this->transactionManager());

    $result = $handler(new CreatePlanCommand(
      key: 'pro',
      name: 'Pro',
      limits: ['members' => 25],
      description: 'The professional plan',
      sortOrder: 3,
    ));

    self::assertInstanceOf(CreatePlanResult::class, $result);
    self::assertSame(self::PLAN_ID, $result->planId);
    self::assertInstanceOf(Plan::class, $saved);
    self::assertSame(self::PLAN_ID, (string) $saved->id());
    self::assertSame('pro', (string) $saved->key());
    self::assertSame('Pro', $saved->name());
    self::assertSame(['members' => 25], $saved->limits());
    self::assertFalse($saved->isDefault());
  }

  #[Test]
  public function itThrowsWhenThePlanKeyAlreadyExists(): void
  {
    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findByKey')->willReturn($this->plan(self::EXISTING_DEFAULT_ID, 'pro'));
    $plans->expects(self::never())->method('save');

    $handler = new CreatePlanHandler($plans, $this->uuidFactory(), $this->transactionManager());

    $this->expectException(PlanKeyAlreadyExistsException::class);

    $handler(new CreatePlanCommand(key: 'pro', name: 'Pro'));
  }

  #[Test]
  public function itClearsThePreviousDefaultWhenCreatingANewDefaultPlan(): void
  {
    $currentDefault = $this->plan(self::EXISTING_DEFAULT_ID, 'free', isDefault: true);

    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findByKey')->willReturn(null);
    $plans->method('findDefault')->willReturn($currentDefault);
    $plans->expects(self::exactly(2))->method('save');

    $handler = new CreatePlanHandler($plans, $this->uuidFactory(), $this->transactionManager());

    $result = $handler(new CreatePlanCommand(key: 'pro', name: 'Pro', isDefault: true));

    self::assertSame(self::PLAN_ID, $result->planId);
    self::assertFalse($currentDefault->isDefault());
  }

  #[Test]
  public function itSavesOnlyTheNewPlanWhenNoDefaultExistsYet(): void
  {
    $plans = $this->createMock(PlanRepositoryPort::class);
    $plans->method('findByKey')->willReturn(null);
    $plans->method('findDefault')->willReturn(null);
    $plans->expects(self::once())->method('save');

    $handler = new CreatePlanHandler($plans, $this->uuidFactory(), $this->transactionManager());

    $result = $handler(new CreatePlanCommand(key: 'pro', name: 'Pro', isDefault: true));

    self::assertSame(self::PLAN_ID, $result->planId);
  }

  private function plan(string $id, string $key, bool $isDefault = false): Plan
  {
    return Plan::create(
      id: PlanId::fromString($id),
      key: new PlanKey($key),
      name: 'Existing',
      limits: [],
      isDefault: $isDefault,
    );
  }

  private function uuidFactory(): UuidFactory
  {
    $factory = $this->createStub(UuidFactory::class);
    $factory->method('create')->willReturn(PlanId::fromString(self::PLAN_ID));

    return $factory;
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
