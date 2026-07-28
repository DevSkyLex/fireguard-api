<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Dto\Output\Plan;

use DateTimeImmutable;
use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Organization\Presentation\Api\Dto\Output\Plan\{PlanOutput};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PlanOutput.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanOutput::class)]
final class PlanOutputTest extends TestCase
{
  #[Test]
  public function testFromResultCopiesEveryScalarField(): void
  {
    $output = PlanOutput::fromResult($this->planResult());

    self::assertSame('22222222-2222-4222-8222-222222222222', $output->id);
    self::assertSame('pro', $output->key);
    self::assertSame('Pro', $output->name);
    self::assertSame('The professional plan', $output->description);
    self::assertSame('Room to grow', $output->tagline);
    self::assertSame(['Export', 'Messaging'], $output->perks);
    self::assertSame(['members' => 50], $output->limits);
    self::assertTrue($output->isActive);
    self::assertFalse($output->isDefault);
    self::assertSame(3, $output->sortOrder);
  }

  #[Test]
  public function testFromResultFormatsTimestampsAsIso8601(): void
  {
    $output = PlanOutput::fromResult($this->planResult());

    self::assertSame('2026-01-01T09:00:00+00:00', $output->createdAt);
    self::assertSame('2026-02-01T09:00:00+00:00', $output->updatedAt);
  }

  #[Test]
  public function testFromResultMapsQuotasIntoQuotaOutputs(): void
  {
    $output = PlanOutput::fromResult($this->planResult());

    self::assertCount(2, $output->quotas);
    self::assertSame('members', $output->quotas[0]->resource);
    self::assertSame('Members', $output->quotas[0]->label);
    self::assertSame(50, $output->quotas[0]->limit);
    self::assertSame('Up to 50 team members', $output->quotas[0]->summary);
    self::assertNull($output->quotas[1]->limit);
    self::assertSame('Unlimited facilities', $output->quotas[1]->summary);
  }

  #[Test]
  public function testFromResultKeepsOptionalFieldsNull(): void
  {
    $result = new GetPlanResult(
      id: '22222222-2222-4222-8222-222222222222',
      key: 'custom',
      name: 'Custom',
      limits: [],
      quotas: [],
      isActive: false,
      isDefault: true,
      sortOrder: 0,
      createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
    );

    $output = PlanOutput::fromResult($result);

    self::assertNull($output->description);
    self::assertNull($output->tagline);
    self::assertSame([], $output->perks);
    self::assertSame([], $output->quotas);
    self::assertFalse($output->isActive);
    self::assertTrue($output->isDefault);
  }

  /**
   * Builds a fully populated plan result.
   */
  private function planResult(): GetPlanResult
  {
    return new GetPlanResult(
      id: '22222222-2222-4222-8222-222222222222',
      key: 'pro',
      name: 'Pro',
      limits: ['members' => 50],
      quotas: [
        ['resource' => 'members', 'label' => 'Members', 'limit' => 50, 'summary' => 'Up to 50 team members'],
        ['resource' => 'facilities', 'label' => 'Facilities', 'limit' => null, 'summary' => 'Unlimited facilities'],
      ],
      isActive: true,
      isDefault: false,
      sortOrder: 3,
      createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-02-01T09:00:00+00:00'),
      description: 'The professional plan',
      tagline: 'Room to grow',
      perks: ['Export', 'Messaging'],
    );
  }
}
