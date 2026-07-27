<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Infrastructure\Adapter\Organization;

use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Infrastructure\Adapter\Organization\ApprovalActionTypeCatalogAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Test ApprovalActionTypeCatalogAdapterTest.
 *
 * The Organization module reads the regulated action types through this
 * adapter to build its approval settings, so the catalog it exposes has to
 * stay aligned with the shared contract rather than drift into its own list.
 *
 * @category Adapter Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalActionTypeCatalogAdapter::class)]
final class ApprovalActionTypeCatalogAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testValuesMirrorsTheSharedCatalog(): void
  {
    self::assertSame(ApprovalActionTypes::all(), new ApprovalActionTypeCatalogAdapter()->values());
  }

  #[Test]
  public function testDescriptorsPairEveryValueWithItsLabel(): void
  {
    $descriptors = new ApprovalActionTypeCatalogAdapter()->descriptors();

    self::assertSame(ApprovalActionTypes::all(), array_column($descriptors, 'value'));

    foreach ($descriptors as $descriptor) {
      self::assertSame(ApprovalActionTypes::label($descriptor['value']), $descriptor['label']);
      self::assertNotSame('', $descriptor['label']);
    }
  }

  #[Test]
  public function testDescriptorsExposeTheHumanReadableLabels(): void
  {
    $labels = array_column(new ApprovalActionTypeCatalogAdapter()->descriptors(), 'label', 'value');

    self::assertSame('Non-conformity waiver', $labels[ApprovalActionTypes::NC_WAIVER]);
    self::assertSame('Equipment decommission', $labels[ApprovalActionTypes::EQUIPMENT_DECOMMISSION]);
  }
  // #endregion
}
