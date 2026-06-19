<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Model\Plan;

use DateTimeImmutable;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationQuotaResource, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(Plan::class)]
final class PlanTest extends TestCase
{
  private const string PLAN_ID = '22222222-2222-4222-8222-222222222221';

  #[Test]
  public function testCreateExposesLimitsAndDefaults(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50, 'facilities' => 25],
      description: 'Advanced plan',
    );

    self::assertSame(self::PLAN_ID, (string) $plan->id());
    self::assertSame('pro', (string) $plan->key());
    self::assertSame('Pro', $plan->name());
    self::assertSame('Advanced plan', $plan->description());
    self::assertTrue($plan->isActive());
    self::assertFalse($plan->isDefault());
    self::assertSame(['members' => 50, 'facilities' => 25], $plan->limits());
    self::assertSame(50, $plan->limitFor(OrganizationQuotaResource::MEMBERS));
    self::assertSame(25, $plan->limitFor(OrganizationQuotaResource::FACILITIES));
    self::assertNull($plan->limitFor(OrganizationQuotaResource::EQUIPMENT));
  }

  #[Test]
  public function testCreateRejectsUnknownResource(): void
  {
    $this->expectException(InvalidValueException::class);

    Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('free'),
      name: 'Free',
      limits: ['unknown' => 3],
    );
  }

  #[Test]
  public function testCreateRejectsNegativeLimit(): void
  {
    $this->expectException(InvalidValueException::class);

    Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('free'),
      name: 'Free',
      limits: ['members' => -1],
    );
  }

  #[Test]
  public function testCreateRejectsTooShortName(): void
  {
    $this->expectException(InvalidValueException::class);

    Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('free'),
      name: 'F',
      limits: [],
    );
  }

  #[Test]
  public function testChangeLimitsReplacesCaps(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('free'),
      name: 'Free',
      limits: ['members' => 5],
    );

    $plan->changeLimits(['facilities' => 2, 'inspections' => 100]);

    self::assertSame(['facilities' => 2, 'inspections' => 100], $plan->limits());
    self::assertNull($plan->limitFor(OrganizationQuotaResource::MEMBERS));
    self::assertSame(2, $plan->limitFor(OrganizationQuotaResource::FACILITIES));
  }

  #[Test]
  public function testMutationsUpdateState(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('free'),
      name: 'Free',
      limits: [],
    );

    $plan->rename('Starter', 'Entry plan');
    $plan->deactivate();
    $plan->markDefault();
    $plan->changeSortOrder(5);

    self::assertSame('Starter', $plan->name());
    self::assertSame('Entry plan', $plan->description());
    self::assertFalse($plan->isActive());
    self::assertTrue($plan->isDefault());
    self::assertSame(5, $plan->sortOrder());

    $plan->activate();
    $plan->unmarkDefault();

    self::assertTrue($plan->isActive());
    self::assertFalse($plan->isDefault());
  }

  #[Test]
  public function testReconstituteRestoresState(): void
  {
    $createdAt = new DateTimeImmutable('2026-06-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-06-02T00:00:00+00:00');

    $plan = Plan::reconstitute(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('max'),
      name: 'Max',
      limits: [],
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      isDefault: true,
      sortOrder: 3,
    );

    self::assertSame([], $plan->limits());
    self::assertNull($plan->limitFor(OrganizationQuotaResource::MEMBERS));
    self::assertTrue($plan->isDefault());
    self::assertSame(3, $plan->sortOrder());
    self::assertSame($createdAt, $plan->createdAt());
    self::assertSame($updatedAt, $plan->updatedAt());
  }
}
