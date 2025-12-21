<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ModuleCollection};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function implode;
use function is_dir;
use function sprintf;
use function str_ends_with;

/**
 * Test ApplicationNamingTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApplicationNamingTest extends TestCase
{
  // #region Constants
  /**
   * Constant USE_CASE_DIR.
   *
   * Directory name for use cases.
   *
   * @var string
   */
  private const string USE_CASE_DIR = 'UseCase';
  // #endregion

  // #region Methods
  /**
   * Method testCommandsEndWithCommandSuffix.
   *
   * Ensures every command class ends with "Command".
   *
   * @return void no return value
   */
  #[Test]
  public function testCommandsEndWithCommandSuffix(): void
  {
    $this->assertUseCaseNaming('Command', 'Command', ['Handler']);
  }

  /**
   * Method testQueriesEndWithQuerySuffix.
   *
   * Ensures every query class ends with "Query".
   *
   * @return void no return value
   */
  #[Test]
  public function testQueriesEndWithQuerySuffix(): void
  {
    $this->assertUseCaseNaming('Query', 'Query', ['Handler']);
  }

  /**
   * Method testHandlersEndWithHandlerSuffix.
   *
   * Ensures every handler class ends with "Handler".
   *
   * @return void no return value
   */
  #[Test]
  public function testHandlersEndWithHandlerSuffix(): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::APPLICATION)) {
        continue;
      }

      $useCaseDir = $module->layerPath(ArchitectureLayer::APPLICATION)
        . DIRECTORY_SEPARATOR . self::USE_CASE_DIR;

      if (!is_dir($useCaseDir)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($useCaseDir),
      );

      foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
          continue;
        }

        $shortName = $file->getBasename('.php');

        // Only check files that are handlers (contain "Handler" in path or name suggests it)
        if (
          !str_ends_with($shortName, 'Handler')
          && !str_ends_with($shortName, 'Command')
          && !str_ends_with($shortName, 'Query')
          && !str_ends_with($shortName, 'Result')
        ) {
          $violations[] = sprintf(
            '%s\\Application\\UseCase\\...\\%s should end with "Command", "Query", "Handler", or "Result".',
            $module->namespace,
            $shortName,
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every class in Application/UseCase/ must end with "Command", "Query", "Handler", or "Result".',
    );
  }
  // #endregion

  // #region Helper Methods
  /**
   * Method assertUseCaseNaming.
   *
   * Asserts that files in a UseCase subdirectory follow the naming convention.
   *
   * @param string $directory the subdirectory name (Command or Query)
   * @param string $suffix the required suffix for the main class
   * @param array<string> $allowedOtherSuffixes other allowed suffixes in that directory
   *
   * @return void no return value
   */
  private function assertUseCaseNaming(string $directory, string $suffix, array $allowedOtherSuffixes): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::APPLICATION)) {
        continue;
      }

      $targetDir = implode(DIRECTORY_SEPARATOR, [
        $module->layerPath(ArchitectureLayer::APPLICATION),
        self::USE_CASE_DIR,
        $directory,
      ]);

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

        // Check if it ends with the main suffix or one of the allowed suffixes
        $hasValidSuffix = str_ends_with($shortName, $suffix);

        if (!$hasValidSuffix) {
          foreach ($allowedOtherSuffixes as $otherSuffix) {
            if (str_ends_with($shortName, $otherSuffix)) {
              $hasValidSuffix = true;
              break;
            }
          }
        }

        // Also allow "Result" suffix for return types
        if (!$hasValidSuffix && str_ends_with($shortName, 'Result')) {
          $hasValidSuffix = true;
        }

        if (!$hasValidSuffix) {
          $violations[] = sprintf(
            '%s\\Application\\UseCase\\%s\\...\\%s must end with suffix "%s", "Handler", or "Result".',
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
      message: sprintf('Every class in UseCase/%s/ must end with "%s", "Handler", or "Result".', $directory, $suffix),
    );
  }
  // #endregion
}
