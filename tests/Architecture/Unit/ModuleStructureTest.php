<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\TestCase;
use App\Tests\Architecture\Support\ModuleCollection;
use function sprintf;

/**
 * Test ModuleStructureTest
 * @final
 *
 * Ensures every module exposes the required layers
 *
 * @category Architecture Unit Tests
 * @package App\Tests\Architecture\Unit
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ModuleStructureTest extends TestCase
{
  //#region Constants
  /**
   * Constant LAYERS
   *
   * Layers to check
   *
   * @access public
   *
   * @var array<string> LAYERS
   */
  public const array LAYERS = [
    'Domain',
    'Application',
    'Infrastructure',
  ];
  //#endregion

  //#region Methods
  /**
   * Method testEachModuleContainsRequiredLayers
   *
   * Ensures every module contains Domain,
   * Application and Infrastructure directories
   *
   * @access public
   *
   * @return void
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

        $missingLayers[] = sprintf('%s is missing layer %s', $module->namespace, $layer);
      }
    }

    self::assertSame(
      expected: [],
      actual: $missingLayers,
      message: 'Every module must expose Domain, Application and Infrastructure directories.'
    );
  }
  //#endregion
}
