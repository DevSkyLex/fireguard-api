<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Plan\GetPlan;

use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Application\UseCase\Query\Plan\GetPlan\{GetPlanHandler, GetPlanQuery};
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TranslationPort;

#[CoversClass(GetPlanHandler::class)]
final class GetPlanHandlerTest extends TestCase
{
  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testInvokeResolvesTaglineAndPerksThroughTheTranslator(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->proPlan());

    $translator = $this->createStub(TranslationPort::class);
    $translator->method('translate')->willReturnCallback(
      static fn (string $id): string => match ($id) {
        'plan.pro.tagline' => 'Room to grow',
        'plan.pro.perks.0' => 'Safety register export',
        'plan.pro.perks.1' => 'Team messaging channels',
        'plan.pro.perks.2' => 'Priority email support',
        default => $id,
      },
    );

    $handler = new GetPlanHandler($planRepository, $translator);

    $result = $handler(new GetPlanQuery(self::PLAN_ID));

    self::assertSame('Room to grow', $result->tagline);
    self::assertSame([
      'Safety register export',
      'Team messaging channels',
      'Priority email support',
    ], $result->perks);
  }

  #[Test]
  public function testInvokeReturnsNullTaglineAndEmptyPerksForAnUncatalogedPlanKey(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->customPlan());

    $translator = $this->createMock(TranslationPort::class);
    $translator->expects(self::never())->method('translate');

    $handler = new GetPlanHandler($planRepository, $translator);

    $result = $handler(new GetPlanQuery(self::PLAN_ID));

    self::assertNull($result->tagline);
    self::assertSame([], $result->perks);
  }

  #[Test]
  public function testInvokeThrowsWhenThePlanIsNotFound(): void
  {
    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn(null);

    $translator = $this->createStub(TranslationPort::class);

    $handler = new GetPlanHandler($planRepository, $translator);

    $this->expectException(PlanNotFoundException::class);

    $handler(new GetPlanQuery(self::PLAN_ID));
  }

  private function proPlan(): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50],
    );
  }

  private function customPlan(): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('custom-enterprise'),
      name: 'Custom Enterprise',
      limits: ['members' => 1000],
    );
  }
}
