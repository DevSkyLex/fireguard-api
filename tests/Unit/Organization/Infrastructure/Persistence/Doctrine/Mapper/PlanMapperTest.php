<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\PlanMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\PlanRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PlanMapper.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanMapper::class)]
final class PlanMapperTest extends TestCase
{
  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testToDomainRebuildsThePlan(): void
  {
    $record = $this->record(['members' => 50]);

    $plan = PlanMapper::toDomain($record);

    self::assertSame(self::PLAN_ID, (string) $plan->id());
    self::assertSame('pro', (string) $plan->key());
    self::assertSame('Pro', $plan->name());
    self::assertSame('The professional plan', $plan->description());
    self::assertSame(['members' => 50], $plan->limits());
    self::assertTrue($plan->isActive());
    self::assertFalse($plan->isDefault());
    self::assertSame(3, $plan->sortOrder());
  }

  #[Test]
  public function testToDomainDropsUnknownAndInvalidLimits(): void
  {
    $record = $this->record([
      'members' => 50,
      'not_a_quota' => 10,
      'facilities' => -1,
      'equipment' => 'many',
    ]);

    self::assertSame(['members' => 50], PlanMapper::toDomain($record)->limits());
  }

  #[Test]
  public function testToRecordCopiesTheAggregateState(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: ['members' => 50],
    );

    $record = PlanMapper::toRecord($plan);

    self::assertSame(self::PLAN_ID, $record->id);
    self::assertSame('pro', $record->key);
    self::assertSame('Pro', $record->name);
    self::assertSame(['members' => 50], $record->limits);
    self::assertSame($plan->isActive(), $record->isActive);
    self::assertSame($plan->isDefault(), $record->isDefault);
    self::assertSame($plan->sortOrder(), $record->sortOrder);
    self::assertEquals($plan->createdAt(), $record->createdAt);
    self::assertEquals($plan->updatedAt(), $record->updatedAt);
  }

  /**
   * Builds a plan record carrying the given limits.
   *
   * @param array<string, mixed> $limits the raw persisted limits
   */
  private function record(array $limits): PlanRecord
  {
    $record = new PlanRecord();
    $record->id = self::PLAN_ID;
    $record->key = 'pro';
    $record->name = 'Pro';
    $record->description = 'The professional plan';
    $record->limits = $limits;
    $record->isActive = true;
    $record->isDefault = false;
    $record->sortOrder = 3;
    $record->createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-01T09:00:00+00:00');

    return $record;
  }
}
