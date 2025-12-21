<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\ModuleCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function sprintf;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test PresentationNamingTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresentationNamingTest extends TestCase
{
  // #region Constants
  /**
   * Constant PRESENTATION_LAYER.
   *
   * Presentation layer name.
   *
   * @var string
   */
  private const string PRESENTATION_LAYER = 'Presentation';

  /**
   * Constant API_DIR.
   *
   * API directory name.
   *
   * @var string
   */
  private const string API_DIR = 'Api';
  // #endregion

  // #region Methods
  /**
   * Method testProcessorsEndWithProcessorSuffix.
   *
   * Ensures every class in Presentation/Api/Processor/
   * ends with "Processor".
   *
   * @return void no return value
   */
  #[Test]
  public function testProcessorsEndWithProcessorSuffix(): void
  {
    $this->assertPresentationNaming(
      directory: 'Processor',
      suffix: 'Processor',
    );
  }

  /**
   * Test testProvidersEndWithProviderSuffix.
   *
   * Ensures every class in Presentation/Api/Provider/
   * ends with "Provider".
   *
   * @return void no return value
   */
  #[Test]
  public function testProvidersEndWithProviderSuffix(): void
  {
    $this->assertPresentationNaming(
      directory: 'Provider',
      suffix: 'Provider',
    );
  }
  // #endregion

  // #region Helper Methods
  /**
   * Method assertPresentationNaming.
   *
   * Asserts that all files in a presentation subdirectory
   * follow the naming convention.
   *
   * @param string $directory the subdirectory name under Presentation/Api/
   * @param string $suffix the required suffix
   */
  private function assertPresentationNaming(string $directory, string $suffix): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      $presentationDir = $module->path
        . DIRECTORY_SEPARATOR . self::PRESENTATION_LAYER;

      if (!is_dir($presentationDir)) {
        continue;
      }

      $targetDir = $presentationDir
        . DIRECTORY_SEPARATOR . self::API_DIR
        . DIRECTORY_SEPARATOR . $directory;

      if (!is_dir($targetDir)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetDir),
      );

      foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with($shortName, $suffix)) {
          $violations[] = sprintf(
            '%s\\Presentation\\Api\\%s\\%s must end with suffix "%s".',
            $module->namespace,
            $directory,
            $shortName,
            $suffix,
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: sprintf('Every class in Presentation/Api/%s/ must end with suffix "%s".', $directory, $suffix),
    );
  }
  // #endregion
}
