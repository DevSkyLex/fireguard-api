<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ModuleCollection};
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test ModuleStructureTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ModuleStructureTest extends TestCase
{
  // #region Constants
  /**
   * Constant LAYERS.
   *
   * Layers to check
   *
   * @var array<string> LAYERS
   */
  /** @var list<ArchitectureLayer> */
  public const array LAYERS = [
    ArchitectureLayer::DOMAIN,
    ArchitectureLayer::APPLICATION,
    ArchitectureLayer::INFRASTRUCTURE,
  ];
  // #endregion

  // #region Methods
  /**
   * Method testEachModuleContainsRequiredLayers.
   *
   * Ensures every module contains Domain,
   * Application and Infrastructure directories
   */
  public function testEachModuleContainsRequiredLayers(): void
  {
    $modules = ModuleCollection::all();
    $missingLayers = [];

    foreach ($modules as $module) {
      foreach (self::LAYERS as $layer) {
        if ($module->hasLayer($layer)) {
          continue;
        }

        $missingLayers[] = sprintf('%s is missing layer %s', $module->namespace, $layer->value);
      }
    }

    self::assertSame(
      expected: [],
      actual: $missingLayers,
      message: 'Every module must expose Domain, Application and Infrastructure directories.',
    );
  }
  // #endregion
}
