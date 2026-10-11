<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function is_dir;
use function ksort;
use function preg_match_all;
use function scandir;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Rejects every foreign Domain import. The complementary Deptrac module gate
 * covers dependencies across all private layers, including fully qualified references.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CrossModuleDomainBoundaryTest extends TestCase
{
  /**
   * The retired debt baseline stays empty. New imports use published capabilities.
   */
  private const array BASELINE = [];

  /**
   * Fails when a module imports another module's private Domain namespace.
   */
  #[Test]
  public function testCrossModuleDomainImportsDoNotGrow(): void
  {
    self::assertSame([], self::countCrossModuleDomainImports(), 'Cross-module Domain dependencies must use owner-published ports and contracts.');
  }

  /**
   * Keeps the completed migration closed by refusing new baseline entries.
   */
  #[Test]
  public function testBaselineHasNoStaleEntries(): void
  {
    self::assertSame([], self::BASELINE, 'Do not reopen the repaired Domain-import baseline.');
  }

  /**
   * Method countCrossModuleDomainImports.
   *
   * @static
   *
   * Counts `use <OtherModule>\Domain\…` statements per consumer/provider pair.
   * Shared Domain is the public kernel; self-imports are not cross-module.
   *
   * @return array<string, int> the import count keyed `Consumer => Provider`
   */
  private static function countCrossModuleDomainImports(): array
  {
    $sourceRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
    $modules = [];
    foreach (scandir($sourceRoot) ?: [] as $entry) {
      if ('.' !== $entry && '..' !== $entry && is_dir($sourceRoot . DIRECTORY_SEPARATOR . $entry)) {
        $modules[$entry] = true;
      }
    }

    $counts = [];

    foreach ($modules as $module => $_ignored) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot . DIRECTORY_SEPARATOR . $module, RecursiveDirectoryIterator::SKIP_DOTS),
      );

      /** @var SplFileInfo $file */
      foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
          continue;
        }

        $contents = file_get_contents($file->getPathname());
        if (false === $contents) {
          continue;
        }

        preg_match_all('/^use\s+([A-Za-z0-9_]+)\\\\Domain\\\\/m', $contents, $matches);

        foreach ($matches[1] as $provider) {
          if ($provider === $module || 'Shared' === $provider || !isset($modules[$provider])) {
            continue;
          }

          $pair = $module . ' => ' . $provider;
          $counts[$pair] = ($counts[$pair] ?? 0) + 1;
        }
      }
    }

    ksort($counts);

    return $counts;
  }
  // #endregion
}
