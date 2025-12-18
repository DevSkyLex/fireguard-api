<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ModuleCollection, ArchitectureLayer};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Test ConsoleNamingTest
 * @final
 *
 * Ensures console command classes follow naming conventions:
 * - Commands must end with "Command"
 *
 * @category Architecture Unit Tests
 * @package App\Tests\Architecture\Unit
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConsoleNamingTest extends TestCase
{
  //#region Constants
  /**
   * Constant CONSOLE_DIR
   *
   * Directory name for console commands.
   *
   * @access private
   *
   * @var string
   */
  private const string CONSOLE_DIR = 'Console';

  /**
   * Constant COMMAND_SUFFIX
   *
   * Required suffix for command classes.
   *
   * @access private
   *
   * @var string
   */
  private const string COMMAND_SUFFIX = 'Command';
  //#endregion

  //#region Methods
  /**
   * Method testConsoleCommandsEndWithCommandSuffix
   *
   * Ensures every class in Infrastructure/Console/ ends with "Command".
   *
   * @access public
   *
   * @return void No return value.
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
        new RecursiveDirectoryIterator($consoleDir)
      );

      foreach ($iterator as $file) {
        if (!$file instanceof \SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || $file->getExtension() !== 'php') {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with($shortName, self::COMMAND_SUFFIX)) {
          $violations[] = sprintf(
            '%s\\Infrastructure\\Console\\%s must end with suffix "%s".',
            $module->namespace,
            $shortName,
            self::COMMAND_SUFFIX
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every class in Infrastructure/Console/ must end with suffix "Command".'
    );
  }
  //#endregion
}
