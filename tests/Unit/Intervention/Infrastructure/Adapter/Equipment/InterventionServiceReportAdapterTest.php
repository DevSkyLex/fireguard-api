<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Equipment;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Equipment\InterventionServiceReportAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionChangeRecord;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test InterventionServiceReportAdapterTest.
 *
 * The service report may only list equipment: the LIKE filter in the query
 * is a prefix match, so nested resources such as
 * `/api/equipment/{id}/attachments/{id}` still reach the entry builder and
 * must be dropped there — otherwise the equipment id would be a path
 * fragment.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionServiceReportAdapter::class)]
final class InterventionServiceReportAdapterTest extends TestCase
{
  // #region Methods
  /**
   * @return iterable<string, array{string}>
   */
  public static function unsupportedResourceProvider(): iterable
  {
    yield 'another module' => ['/api/facilities/550e8400-e29b-41d4-a716-446655446010'];

    yield 'nested equipment sub-resource' => ['/api/equipment/550e8400-e29b-41d4-a716-446655446011/attachments/1'];

    yield 'collection rather than an item' => ['/api/equipment/'];

    yield 'free text' => ['equipment'];
  }

  #[Test]
  #[DataProvider('unsupportedResourceProvider')]
  public function testAChangeThatIsNotASingleEquipmentItemProducesNoEntry(string $resource): void
  {
    $change = new InterventionChangeRecord();
    $change->id = '550e8400-e29b-41d4-a716-446655446020';
    $change->resource = $resource;

    $adapter = new InterventionServiceReportAdapter($this->createStub(EntityManagerInterface::class));

    $entry = new ReflectionMethod($adapter, 'toServicedEquipmentEntry')->invoke($adapter, $change);

    self::assertNull($entry);
  }
  // #endregion
}
