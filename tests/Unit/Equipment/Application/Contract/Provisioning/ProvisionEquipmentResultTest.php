<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Contract\Provisioning;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentResult, ProvisionOutcome};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ProvisionEquipmentResultTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProvisionEquipmentResult::class)]
final class ProvisionEquipmentResultTest extends TestCase
{
  #[Test]
  public function itExposesCreatedOutcome(): void
  {
    $result = new ProvisionEquipmentResult(ProvisionOutcome::CREATED, 'equip-1');

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertSame('equip-1', $result->resourceId);
    self::assertNull($result->message);
  }

  #[Test]
  public function itExposesFailureOutcome(): void
  {
    $result = new ProvisionEquipmentResult(ProvisionOutcome::QUOTA_EXCEEDED, null, 'Quota reached');

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
    self::assertNull($result->resourceId);
    self::assertSame('Quota reached', $result->message);
  }
}
