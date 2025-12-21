<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ModuleCollection};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function basename;
use function glob;
use function is_dir;
use function sprintf;
use function str_ends_with;
use function str_replace;

/**
 * Test AdapterNamingTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AdapterNamingTest extends TestCase
{
  // #region Constants
  /**
   * Constant ADAPTER_SUFFIX.
   *
   * Required suffix for adapter classes.
   *
   * @var string
   */
  private const string ADAPTER_SUFFIX = 'Adapter';

  /**
   * Constant ADAPTER_DIR.
   *
   * Directory name for adapters.
   *
   * @var string
   */
  private const string ADAPTER_DIR = 'Adapter';
  // #endregion

  // #region Methods
  /**
   * Method testAllAdaptersEndWithAdapterSuffix.
   *
   * Ensures every class in Infrastructure/Adapter directories
   * ends with the "Adapter" suffix.
   *
   * @return void no return value
   */
  #[Test]
  public function testAllAdaptersEndWithAdapterSuffix(): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::INFRASTRUCTURE)) {
        continue;
      }

      $adapterDir = $module->layerPath(ArchitectureLayer::INFRASTRUCTURE)
        . DIRECTORY_SEPARATOR . self::ADAPTER_DIR;

      if (!is_dir($adapterDir)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($adapterDir),
      );

      foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with($shortName, self::ADAPTER_SUFFIX)) {
          $pathName = $file->getPathname();
          $relativePath = str_replace(
            $module->path . DIRECTORY_SEPARATOR,
            '',
            $pathName,
          );

          $classPath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
          $classPath = str_replace('.php', '', $classPath);

          $violations[] = sprintf(
            '%s\\%s must end with suffix "%s".',
            $module->namespace,
            $classPath,
            self::ADAPTER_SUFFIX,
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every adapter class must end with suffix "Adapter".',
    );
  }

  /**
   * Method testAdaptersAreInSubdirectory.
   *
   * Ensures adapters are organized in subdirectories
   * (e.g., Adapter/Symfony/, Adapter/League/), not directly in Adapter/.
   *
   * @return void no return value
   */
  #[Test]
  public function testAdaptersAreInSubdirectory(): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::INFRASTRUCTURE)) {
        continue;
      }

      $adapterDir = $module->layerPath(ArchitectureLayer::INFRASTRUCTURE)
        . DIRECTORY_SEPARATOR . self::ADAPTER_DIR;

      if (!is_dir($adapterDir)) {
        continue;
      }

      // Check for PHP files directly in Adapter/ (should be in a subdirectory)
      $directFiles = glob($adapterDir . DIRECTORY_SEPARATOR . '*.php') ?: [];

      foreach ($directFiles as $file) {
        $shortName = basename($file, '.php');

        $violations[] = sprintf(
          '%s\\Infrastructure\\Adapter\\%s should be in a subdirectory (e.g., Symfony/, League/, Doctrine/).',
          $module->namespace,
          $shortName,
        );
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Adapters should be organized in subdirectories based on the framework/library they use.',
    );
  }
  // #endregion
}
