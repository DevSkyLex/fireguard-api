<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function explode;
use function file_get_contents;
use function implode;
use function is_dir;
use function scandir;
use function sort;
use function sprintf;
use function str_contains;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test ScopeBeforePermissionTest.
 *
 * A Presentation surface that loads a record by GLOBAL id and then gates on
 * `hasPermission()` alone cannot tell "no such record" from "it exists, in a
 * tenant you are not in". The first answers 404, the second 403, and the
 * difference lets an outsider enumerate ids.
 *
 * `OrganizationAuthorizationPort::resolveAccess()` exists to separate them:
 * `OUTSIDE_SCOPE` answers 404, `MISSING_PERMISSION` answers 403.
 *
 * This is not hypothetical. The oracle was live on three Messaging handlers
 * (2026-08-25) and on six Inspection gates (2026-08-26), each proven by a
 * functional test that failed with `403 is not identical to 404` before the
 * fix. Twenty-five sibling surfaces already did it right, which is what made
 * the stragglers findable — and what makes a ratchet worth having, since the
 * next one would be just as invisible.
 *
 * **The rule this enforces is narrow on purpose.** `hasPermission()` alone is
 * perfectly correct where the organization id comes from the URI and no
 * separate existence lookup precedes it: a missing organization and a foreign
 * one both fail the same check and both answer 403, so there is nothing to
 * distinguish. Organization's own processors are all like that. The defect
 * needs BOTH an existence lookup that answers 404 AND a permission gate that
 * answers 403.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ScopeBeforePermissionTest extends TestCase
{
  // There is deliberately no exemption list. Every surface matching this shape
  // today is a defect, and an empty allow-list is a place for the next one to
  // hide. If a genuine false positive appears, add the list back WITH the
  // reasoning for why its 404 and its 403 cannot describe the same record.

  // #region Methods
  /**
   * Method testNoSurfaceCombinesAnExistenceLookupWithAPermissionOnlyGate.
   *
   * @return void no return value
   */
  #[Test]
  public function testNoSurfaceCombinesAnExistenceLookupWithAPermissionOnlyGate(): void
  {
    $offenders = self::collectOffenders();

    self::assertSame([], $offenders, sprintf(
      "These Presentation files load a record by global id and gate on hasPermission() alone:\n  %s\n\n"
      . 'A caller outside the record\'s organization gets 403 while an absent id gets 404, which confirms '
      . 'the id exists in another tenant. Use resolveAccess() instead: isOutsideScope() throws the SAME '
      . 'NotFoundHttpException, with the same message, that a missing record throws; only isGranted() '
      . 'false answers 403. See CanonicalFacilityProvider for the shape.',
      implode("\n  ", $offenders),
    ));
  }

  /**
   * Method collectOffenders.
   *
   * @static
   *
   * A file offends when it performs an existence lookup that answers 404
   * (`entityManager->find()` next to a `NotFoundHttpException`), gates on
   * `hasPermission()`, and never calls `resolveAccess()`. All three are
   * required: the first two alone are how a correctly scoped surface looks
   * before the permission split, and the third is the fix.
   *
   * @return list<string> the offending paths, repo-relative
   */
  private static function collectOffenders(): array
  {
    $sourceRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
    $offenders = [];

    foreach (scandir($sourceRoot) ?: [] as $module) {
      if ('.' === $module || '..' === $module) {
        continue;
      }
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

        if (
          !str_contains($contents, 'entityManager->find(')
          || !str_contains($contents, 'NotFoundHttpException')
          || !str_contains($contents, 'hasPermission(')
          || str_contains($contents, 'resolveAccess(')
        ) {
          continue;
        }

        $offenders[] = 'src' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR
          . 'Presentation' . explode('Presentation', $file->getPathname())[1];
      }
    }

    sort($offenders);

    return $offenders;
  }
  // #endregion
}
