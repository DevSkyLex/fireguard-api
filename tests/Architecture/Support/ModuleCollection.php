<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Support;

use RuntimeException;
use function sprintf;
use function scandir;
use function is_dir;
use function usort;

/**
 * Class ModuleCollection
 * @final
 *
 * Utility class to represent a
 * collection of modules
 *
 * @category Architecture Tests Support
 * @package App\Tests\Architecture\Support
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ModuleCollection
{
  //#region Properties
  /**
   * Property modules
   * @static
   *
   * List of modules
   *
   * @access private
   *
   * @var list<Module> | null $modules
   */
  private static ?array $modules = null;
  //#endregion

  //#region Methods
  /**
   * Method all
   *
   * Get all modules
   *
   * @access public
   *
   * @return list<Module> The list of modules
   */
  public static function all(): array
  {
    if (self::$modules !== null) {
      return self::$modules;
    }

    $srcDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
    $modules = [];

    foreach (scandir(directory: $srcDir) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }

      $path = $srcDir . DIRECTORY_SEPARATOR . $entry;

      if (!is_dir(filename: $path)) {
        continue;
      }

      if (!is_dir(filename: $path . DIRECTORY_SEPARATOR . 'Domain')) {
        continue;
      }

      $modules[] = new Module(
        namespace: $entry === 'Shared' ? 'Shared' : sprintf('App\\%s', $entry),
        path: $path,
      );
    }

    usort(
      $modules,
      static fn(
        Module $first,
        Module $second
      ): int => $first->namespace <=> $second->namespace
    );

    if ($modules === []) {
      throw new RuntimeException(message: 'No module namespaces detected under src/.');
    }

    return self::$modules = $modules;
  }
  //#endregion
}
