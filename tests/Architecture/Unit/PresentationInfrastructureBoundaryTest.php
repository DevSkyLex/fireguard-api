<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_diff_key;
use function array_keys;
use function array_map;
use function dirname;
use function explode;
use function file_get_contents;
use function implode;
use function is_dir;
use function ksort;
use function preg_match_all;
use function scandir;
use function sprintf;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test PresentationInfrastructureBoundaryTest.
 *
 * Complements PHPat and both Deptrac gates with a zero-debt foreign Infrastructure
 * import check. Authentication uses its published principal contract; foreign
 * persistence Records and framework security implementations have no exemption.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresentationInfrastructureBoundaryTest extends TestCase
{
  // #region Constants
  /**
   * Existing debt is empty; new foreign Infrastructure imports are regressions.
   */
  private const array BASELINE = [];
  // #endregion

  // #region Methods
  /**
   * Method testPresentationInfrastructureImportsDoNotGrow.
   *
   * Fails when a module's `Presentation` starts importing another module's
   * `Infrastructure` more than it already did, or reaches into one it never
   * touched.
   *
   * @return void no return value
   */
  #[Test]
  public function testPresentationInfrastructureImportsDoNotGrow(): void
  {
    $actual = self::countPresentationInfrastructureImports();
    /** @var array<string, int> $baseline an empty constant narrows to array{}, which cannot be indexed */
    $baseline = self::BASELINE;
    $regressions = [];

    foreach ($actual as $pair => $count) {
      $allowed = $baseline[$pair] ?? 0;

      if ($count > $allowed) {
        $regressions[$pair] = sprintf('%d import(s), baseline allows %d', $count, $allowed);
      }
    }

    // One assertion, always executed: with an empty baseline the per-pair
    // loop would run zero times and the test would report "no assertions"
    // rather than a green ratchet.
    self::assertSame([], $regressions, sprintf(
      'Foreign Infrastructure dependencies require owner-published capabilities: %s.',
      implode("\n", array_map(
        static fn (string $pair, string $detail): string => sprintf('  - %s: %s', $pair, $detail),
        array_keys($regressions),
        $regressions,
      )),
    ));
  }

  /**
   * Method testBaselineHasNoStaleEntries.
   *
   * Keeps the baseline honest: once a pair is cleaned up, its entry must go, so
   * the list always reflects real debt rather than accumulating fiction.
   *
   * @return void no return value
   */
  #[Test]
  public function testBaselineHasNoStaleEntries(): void
  {
    $stale = array_keys(array_diff_key(self::BASELINE, self::countPresentationInfrastructureImports()));

    self::assertSame([], $stale, sprintf(
      'Baseline entries no longer match any import — those pairs were cleaned up: %s. '
      . 'Remove them so the baseline keeps reflecting real debt.',
      implode(', ', $stale),
    ));
  }

  /**
   * Method countPresentationInfrastructureImports.
   *
   * @static
   *
   * Counts `use <OtherModule>\Infrastructure\…` statements found under each
   * module's `Presentation` directory, per consumer/provider pair. `Shared` is
   * not a module boundary, self-imports are not cross-module, and the framework
   * idioms listed in EXEMPT_IMPORTS are skipped.
   *
   * @return array<string, int> the import count keyed `Consumer => Provider`
   */
  private static function countPresentationInfrastructureImports(): array
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
      $presentation = $sourceRoot . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Presentation';
      if (!is_dir($presentation)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($presentation, RecursiveDirectoryIterator::SKIP_DOTS),
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

        preg_match_all('/^use\s+([A-Za-z0-9_]+\\\\Infrastructure\\\\[A-Za-z0-9_\\\\]*)/m', $contents, $matches);

        foreach ($matches[1] as $import) {
          $provider = explode('\\', $import)[0];
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
