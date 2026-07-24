<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Contract\Action;

use Approval\Application\Contract\Action\ApprovalActionTypes;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalActionTypes.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalActionTypes::class)]
final class ApprovalActionTypesTest extends TestCase
{
  #[Test]
  public function testConstantsHoldExpectedValues(): void
  {
    self::assertSame('nc_waiver', ApprovalActionTypes::NC_WAIVER);
    self::assertSame('equipment_decommission', ApprovalActionTypes::EQUIPMENT_DECOMMISSION);
  }

  #[Test]
  public function testAllReturnsEverySupportedType(): void
  {
    self::assertSame(['nc_waiver', 'equipment_decommission'], ApprovalActionTypes::all());
  }

  #[Test]
  public function testLabelResolvesKnownTypes(): void
  {
    self::assertSame('Non-conformity waiver', ApprovalActionTypes::label('nc_waiver'));
    self::assertSame('Equipment decommission', ApprovalActionTypes::label('equipment_decommission'));
  }

  #[Test]
  public function testLabelFallsBackToRawValue(): void
  {
    self::assertSame('unknown_action', ApprovalActionTypes::label('unknown_action'));
  }
}
