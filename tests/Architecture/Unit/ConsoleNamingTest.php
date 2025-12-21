<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ModuleCollection};
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
 * Test ConsoleNamingTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConsoleNamingTest extends TestCase
{
  // #region Constants
  /**
   * Constant CONSOLE_DIR.
   *
   * Directory name for console commands.
   *
   * @var string
   */
  private const string CONSOLE_DIR = 'Console';

  /**
   * Constant COMMAND_SUFFIX.
   *
   * Required suffix for command classes.
   *
   * @var string
   */
  private const string COMMAND_SUFFIX = 'Command';
  // #endregion

  // #region Methods
  /**
   * Method testConsoleCommandsEndWithCommandSuffix.
   *
   * Ensures every class in Infrastructure/Console/ ends with "Command".
   *
   * @return void no return value
   */
  #[Test]
  public function testConsoleCommandsEndWithCommandSuffix(): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::INFRASTRUCTURE)) {
        continue;
      }

      $consoleDir = $module->layerPath(ArchitectureLayer::INFRASTRUCTURE)
        . DIRECTORY_SEPARATOR . self::CONSOLE_DIR;

      if (!is_dir($consoleDir)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($consoleDir),
      );

      foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with($shortName, self::COMMAND_SUFFIX)) {
          $violations[] = sprintf(
            '%s\\Infrastructure\\Console\\%s must end with suffix "%s".',
            $module->namespace,
            $shortName,
            self::COMMAND_SUFFIX,
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every class in Infrastructure/Console/ must end with suffix "Command".',
    );
  }
  // #endregion
}
