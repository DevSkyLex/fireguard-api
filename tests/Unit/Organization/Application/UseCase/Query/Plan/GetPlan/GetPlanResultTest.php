<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Plan\GetPlan;

use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TranslationPort;

/**
 * Test GetPlanResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPlanResult::class)]
final class GetPlanResultTest extends TestCase
{
  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testFromDomainResolvesTaglineAndPerksForACatalogedPlan(): void
  {
    $result = GetPlanResult::fromDomain($this->plan('pro'), $this->translator());

    self::assertSame(self::PLAN_ID, $result->id);
    self::assertSame('pro', $result->key);
    self::assertSame('Pro', $result->name);
    self::assertSame('translated:plan.pro.tagline', $result->tagline);
    self::assertNotSame([], $result->perks);
    foreach ($result->perks as $perk) {
      self::assertStringStartsWith('translated:', $perk);
    }
  }

  #[Test]
  public function testFromDomainLeavesTheTaglineNullForAnUncatalogedPlanKey(): void
  {
    $result = GetPlanResult::fromDomain($this->plan('custom-enterprise'), $this->translator());

    self::assertNull($result->tagline);
    self::assertSame([], $result->perks);
  }

  #[Test]
  public function testFromDomainProjectsEveryQuotaResource(): void
  {
    $result = GetPlanResult::fromDomain($this->plan('pro'), $this->translator());

    self::assertCount(4, $result->quotas);

    $byResource = [];
    foreach ($result->quotas as $quota) {
      $byResource[$quota['resource']] = $quota;
    }

    self::assertSame('Members', $byResource['members']['label']);
    self::assertSame(50, $byResource['members']['limit']);
    self::assertSame('Up to 50 team members', $byResource['members']['summary']);
    self::assertNull($byResource['facilities']['limit']);
    self::assertSame('Unlimited facilities', $byResource['facilities']['summary']);
  }

  #[Test]
  public function testFromDomainCopiesThePlanFlagsAndTimestamps(): void
  {
    $plan = $this->plan('pro');

    $result = GetPlanResult::fromDomain($plan, $this->translator());

    self::assertSame($plan->isActive(), $result->isActive);
    self::assertSame($plan->isDefault(), $result->isDefault);
    self::assertSame($plan->sortOrder(), $result->sortOrder);
    self::assertSame($plan->description(), $result->description);
    self::assertSame(['members' => 50], $result->limits);
    self::assertEquals($plan->createdAt(), $result->createdAt);
    self::assertEquals($plan->updatedAt(), $result->updatedAt);
  }

  /**
   * Builds a translator stub prefixing every id.
   */
  private function translator(): TranslationPort
  {
    $translator = $this->createStub(TranslationPort::class);
    $translator->method('translate')->willReturnCallback(
      static fn (string $id): string => 'translated:' . $id,
    );

    return $translator;
  }

  /**
   * Builds a plan carrying the given key.
   */
  private function plan(string $key): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey($key),
      name: 'Pro',
      limits: ['members' => 50],
      description: 'The professional plan',
    );
  }
}
