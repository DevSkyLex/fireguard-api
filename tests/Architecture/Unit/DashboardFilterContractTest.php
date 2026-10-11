<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use BackedEnum;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Test DashboardFilterContractTest.
 *
 * Prevents published owner filter values from drifting from persisted lifecycle values
 * or changing the ordering of existing HTTP validation messages.
 *
 * @category Architecture Unit Tests
 */
final class DashboardFilterContractTest extends TestCase
{
  /**
   * Method testPublishedFilterValuesPreserveTheOwnerWireContract.
   *
   * @param class-string<BackedEnum> $domain the internal owner lifecycle enum
   * @param class-string<BackedEnum> $contract the published owner filter enum
   *
   * @return void
   */
  #[Test]
  #[DataProvider('ownerFilterContracts')]
  public function testPublishedFilterValuesPreserveTheOwnerWireContract(string $domain, string $contract): void
  {
    self::assertSame(array_column($domain::cases(), 'value'), array_column($contract::cases(), 'value'));
    foreach ($domain::cases() as $case) {
      self::assertSame($case->value, $contract::from($case->value)->value);
    }
  }

  /**
   * Method ownerFilterContracts.
   *
   * @return iterable<string, array{class-string<BackedEnum>, class-string<BackedEnum>}>
   */
  public static function ownerFilterContracts(): iterable
  {
    yield 'equipment status' => [\Equipment\Domain\ValueObject\EquipmentStatus::class, \Equipment\Application\Contract\Dashboard\Filter\EquipmentStatusFilter::class];
    yield 'equipment type' => [\Equipment\Domain\ValueObject\EquipmentType::class, \Equipment\Application\Contract\Dashboard\Filter\EquipmentTypeFilter::class];
    yield 'facility type' => [\Facility\Domain\ValueObject\FacilityType::class, \Facility\Application\Contract\Dashboard\Filter\FacilityTypeFilter::class];
    yield 'inspection status' => [\Inspection\Domain\ValueObject\InspectionStatus::class, \Inspection\Application\Contract\Dashboard\Filter\InspectionStatusFilter::class];
    yield 'inspection result' => [\Inspection\Domain\ValueObject\InspectionResult::class, \Inspection\Application\Contract\Dashboard\Filter\InspectionResultFilter::class];
    yield 'inspector type' => [\Inspection\Domain\ValueObject\InspectorType::class, \Inspection\Application\Contract\Dashboard\Filter\InspectorTypeFilter::class];
    yield 'non-conformity status' => [\Inspection\Domain\ValueObject\NonConformityStatus::class, \Inspection\Application\Contract\Dashboard\Filter\NonConformityStatusFilter::class];
    yield 'non-conformity severity' => [\Inspection\Domain\ValueObject\NonConformitySeverity::class, \Inspection\Application\Contract\Dashboard\Filter\NonConformitySeverityFilter::class];
  }
}
