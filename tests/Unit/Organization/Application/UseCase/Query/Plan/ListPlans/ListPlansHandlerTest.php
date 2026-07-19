<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Plan\ListPlans;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Query\Plan\ListPlans\{ListPlansHandler, ListPlansQuery};
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TranslationPort;

#[CoversClass(ListPlansHandler::class)]
final class ListPlansHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeResolvesTaglineAndPerksForEveryPlan(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findAllActive')->willReturn([$this->freePlan(), $this->proPlan()]);

    $translator = $this->createStub(TranslationPort::class);
    $translator->method('translate')->willReturnCallback(
      static fn (string $id): string => match ($id) {
        'plan.free.tagline' => 'Get started for free',
        'plan.pro.tagline' => 'Room to grow',
        default => $id,
      },
    );

    $handler = new ListPlansHandler($planRepository, $translator);

    $result = $handler(new ListPlansQuery(activeOnly: true));

    self::assertCount(2, $result->plans);
    self::assertSame('Get started for free', $result->plans[0]->tagline);
    self::assertSame('Room to grow', $result->plans[1]->tagline);
  }

  #[Test]
  public function testInvokeUsesFindAllWhenActiveOnlyIsFalse(): void
  {
    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->expects(self::once())->method('findAll')->willReturn([$this->freePlan()]);
    $planRepository->expects(self::never())->method('findAllActive');

    $translator = $this->createStub(TranslationPort::class);
    $translator->method('translate')->willReturn('translated');

    $handler = new ListPlansHandler($planRepository, $translator);

    $result = $handler(new ListPlansQuery(activeOnly: false));

    self::assertCount(1, $result->plans);
  }

  private function freePlan(): Plan
  {
    return Plan::create(
      id: PlanId::fromString('22222222-2222-4222-8222-222222222221'),
      key: new PlanKey('free'),
      name: 'Free',
      limits: ['members' => 5],
    );
  }

  private function proPlan(): Plan
  {
    return Plan::create(
      id: PlanId::fromString('22222222-2222-4222-8222-222222222222'),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50],
    );
  }
}
