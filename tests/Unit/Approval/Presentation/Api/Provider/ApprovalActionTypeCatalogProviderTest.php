<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Presentation\Api\Provider\ApprovalActionTypeCatalogProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Test ApprovalActionTypeCatalogProviderTest.
 *
 * The settings UI builds its "which actions require approval" toggles from
 * this catalog, so it has to expose exactly the action types the gate knows
 * about — an extra or missing row would let an organization configure a
 * gate that never fires.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalActionTypeCatalogProvider::class)]
final class ApprovalActionTypeCatalogProviderTest extends TestCase
{
  #[Test]
  public function testItExposesExactlyTheRegisteredActionTypes(): void
  {
    $rows = new ApprovalActionTypeCatalogProvider()->provide(new GetCollection());

    self::assertCount(2, $rows);
    self::assertSame(ApprovalActionTypes::all(), array_column($rows, 'value'));
  }

  #[Test]
  public function testItLabelsEveryRowFromTheCatalog(): void
  {
    $rows = new ApprovalActionTypeCatalogProvider()->provide(new GetCollection());

    self::assertSame(ApprovalActionTypes::NC_WAIVER, $rows[0]->value);
    self::assertSame('Non-conformity waiver', $rows[0]->label);
    self::assertSame(ApprovalActionTypes::EQUIPMENT_DECOMMISSION, $rows[1]->value);
    self::assertSame('Equipment decommission', $rows[1]->label);
  }

  #[Test]
  public function testItIgnoresUriVariablesAndContext(): void
  {
    // A static reference catalog is neither scoped nor filtered.
    $provider = new ApprovalActionTypeCatalogProvider();

    self::assertEquals(
      $provider->provide(new GetCollection()),
      $provider->provide(new GetCollection(), ['organizationId' => 'ignored'], ['filters' => ['value' => 'nope']]),
    );
  }
}
