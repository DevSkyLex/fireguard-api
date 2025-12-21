<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ArchitectureNamespace, ModuleCollection};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * Test PortNamingTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PortNamingTest extends TestCase
{
  // #region Constants
  /**
   * Constant PORT_SUFFIX.
   *
   * @var string
   */
  private const string PORT_SUFFIX = 'Port';
  // #endregion

  // #region Methods
  /**
   * Method testAllPortsEndWithPortSuffix.
   *
   * Ensures every port class ends with
   * the "Port" suffix
   *
   * @return void no return value
   */
  public function testAllPortsEndWithPortSuffix(): void
  {
    $modules = ModuleCollection::all();
    $violations = [];

    foreach ($modules as $module) {
      if (!$module->hasLayer(ArchitectureLayer::APPLICATION)) {
        continue;
      }

      $applicationPath = $module->layerPath(ArchitectureLayer::APPLICATION);
      $portDir = $applicationPath . DIRECTORY_SEPARATOR . ArchitectureNamespace::PORT->value;

      if (!is_dir($portDir)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(directory: $portDir),
      );

      foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with(haystack: $shortName, needle: self::PORT_SUFFIX)) {
          $pathname = $file->getPathname();
          $violations[] = sprintf(
            '%s must end with suffix %s.',
            $module->namespace . '\\' . ArchitectureLayer::APPLICATION->value . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', substr($pathname, strlen($applicationPath) + 1, -4)),
            self::PORT_SUFFIX,
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every port class must end with suffix "Port".',
    );
  }
}
