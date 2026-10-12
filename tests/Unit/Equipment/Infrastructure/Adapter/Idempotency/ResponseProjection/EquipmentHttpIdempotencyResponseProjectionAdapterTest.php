<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Idempotency\ResponseProjection;

use Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment\PatchCanonicalEquipmentCommand;
use Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\SetEquipmentPlanPositionCommand;
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery;
use Equipment\Infrastructure\Adapter\Idempotency\ResponseProjection\EquipmentHttpIdempotencyResponseProjectionAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;

/**
 * Test EquipmentHttpIdempotencyResponseProjectionAdapterTest.
 *
 * Preserves the canonical actor scope and organization-scoped plan response queries.
 *
 * @category Unit Tests
 */
#[CoversClass(EquipmentHttpIdempotencyResponseProjectionAdapter::class)]
final class EquipmentHttpIdempotencyResponseProjectionAdapterTest extends TestCase
{
  #[Test]
  public function itConstructsActorScopedCanonicalAndOrganizationScopedPlanDetailQueries(): void
  {
    $factory = new EquipmentHttpIdempotencyResponseProjectionAdapter();
    self::assertEquals([new GetEquipmentDetailQuery('equipment', userId: 'actor')], $factory->queries('canonical_equipment_patch', new PatchCanonicalEquipmentCommand('equipment', 2, userId: 'actor'), new VoidResult()));
    self::assertEquals([new GetEquipmentDetailQuery('equipment', 'organization')], $factory->queries('equipment_plan_position_set', new SetEquipmentPlanPositionCommand('organization', 'equipment', null, null, null), new VoidResult()));
    self::assertFalse($factory->supports('canonical_equipment_delete'));
  }
}
