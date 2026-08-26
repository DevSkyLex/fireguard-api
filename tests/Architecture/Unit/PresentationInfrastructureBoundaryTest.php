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
use function is_dir;
use function ksort;
use function preg_match_all;
use function scandir;
use function sprintf;
use function str_ends_with;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test PresentationInfrastructureBoundaryTest.
 *
 * A module's `Presentation` layer must never import ANOTHER module's
 * `Infrastructure`. `CLAUDE.md` rule 5 already forbids it — cross-module access
 * goes through `Application\Port` and `Application\Contract` only — but nothing
 * enforced it: deptrac's collectors are layer-shaped, not module-shaped, so a
 * `Presentation → Infrastructure` edge BETWEEN two modules satisfies every rule
 * it declares (`deptrac.yaml` permits `Presentation → Infrastructure` outright),
 * and `CrossModuleDomainBoundaryTest` only counts `Domain` imports.
 *
 * This is the narrowest of the three cross-module boundary questions, and the
 * only one where every current occurrence is a defect:
 *
 *  - `Infrastructure → Infrastructure` (354 imports) is mostly a Doctrine
 *    `Record` naming a sibling `Record` it has a real ORM association with —
 *    every business module lives on the `main` database and the foreign keys
 *    exist. That is a schema decision, not sloppiness, and is NOT pinned here.
 *  - `X → Auth\Infrastructure\Security\User\SecurityUser` (255 imports) is the
 *    object `Security::getUser()` returns. It is the framework's own idiom,
 *    reached from every processor and provider in the repo, and is EXEMPT
 *    below rather than counted.
 *  - `Presentation → sibling Infrastructure` is what remains: 10 imports across
 *    8 files, and all ten name a Doctrine `Record`. Each one is a processor or
 *    provider doing `$entityManager->find(<Sibling>Record::class, …)` — a raw
 *    read of another module's table from the layer that is supposed to only
 *    translate HTTP.
 *
 * Like its sibling ratchets this pins rather than fixes: the eight files stay,
 * and a ninth cannot appear. Lowering a number after cleaning a pair up is
 * expected — the baseline is a ratchet, not a target.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresentationInfrastructureBoundaryTest extends TestCase
{
  // #region Constants
  /**
   * Imports that are the framework's idiom rather than module coupling.
   *
   * `SecurityUser` is what `Symfony\Bundle\SecurityBundle\Security::getUser()`
   * returns, so every authenticated endpoint in every module narrows to it.
   * Counting those 255 imports would bury the 10 that are real.
   */
  private const array EXEMPT_IMPORTS = [
    'Auth\\Infrastructure\\Security\\User\\SecurityUser',
  ];

  /**
   * Known `Presentation → sibling Infrastructure` imports, keyed
   * `Consumer => Provider`.
   *
   * Captured 2026-08-25 at ten; lowered to seven on 2026-08-26.
   *
   * The three that went were never lookups at all. They were
   * `$record->organization instanceof OrganizationRecord` — a check on a
   * property the ORM already types `?OrganizationRecord`, so it could only ever
   * be false when null. Replacing it with `null === $record->organization` is
   * exactly equivalent and needs no import. Nothing was moved, nothing was
   * abstracted; a redundant `instanceof` was the only thing holding those three
   * imports in place.
   *
   * The seven that remain are real: `$entityManager->find(<Sibling>Record::class, …)`.
   * Those need the class name, so closing them needs the owning module to
   * publish a lookup port — Organization has none today. Note that removing
   * them would NOT remove the coupling underneath: `EquipmentRecord`,
   * `FacilityRecord` and `InspectionRecord` each declare
   * `#[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]`, which is
   * schema-level and outlives any Presentation cleanup.
   *
   * The original ten, all naming a Doctrine `Record`:
   *
   *  - `Equipment => Facility` — `CanonicalEquipmentMutationProcessor` reads
   *    `FacilityRecord` to validate the assignment target.
   *  - `Equipment => Organization`, `Facility => Organization`,
   *    `Inspection => Organization` — the canonical flat-surface processors and
   *    providers each resolve the owning `OrganizationRecord` through the
   *    entity manager, then permission-check against `$organization->id`.
   *  - `Inspection => Intervention` — `InspectionResponseProcessor` reads
   *    `InterventionRecord` to attach the response to its intervention.
   *
   * The fix in every case is the same shape and is NOT attempted here: publish
   * the lookup as an `Application\Port\Inbound` port on the owning module and
   * move the surrounding decision into a handler. `Organization` has no such
   * port today — its `Application\Port\Inbound` exposes authorization, caller
   * membership, quota and team directory, but nothing that answers "does this
   * organization exist and what is its id".
   *
   * A pair may only shrink or disappear.
   */
  private const array BASELINE = [
    'Equipment => Facility' => 1,
    'Equipment => Organization' => 1,
    'Facility => Organization' => 1,
    'Inspection => Intervention' => 1,
    'Inspection => Organization' => 1,
  ];
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

    foreach ($actual as $pair => $count) {
      $allowed = self::BASELINE[$pair] ?? 0;

      self::assertLessThanOrEqual($allowed, $count, sprintf(
        'Presentation → sibling Infrastructure regression: "%s" now has %d import(s), baseline allows %d. '
        . 'A processor or provider must not reach into another module\'s Infrastructure — that is CLAUDE.md '
        . 'rule 5, and deptrac cannot see it (its collectors are layer-shaped, not module-shaped, and it '
        . 'permits Presentation → Infrastructure outright). Publish the lookup as an Application\Port\Inbound '
        . 'port on the owning module and put the decision in a handler. If the import is genuinely '
        . 'unavoidable, raise the baseline DELIBERATELY and say why in the module\'s MODULE.md.',
        $pair,
        $count,
        $allowed,
      ));
    }
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
    $actual = self::countPresentationInfrastructureImports();

    foreach (self::BASELINE as $pair => $allowed) {
      self::assertArrayHasKey($pair, $actual, sprintf(
        'Baseline entry "%s" no longer matches any import — the pair was cleaned up. '
        . 'Remove the entry so the baseline keeps reflecting real debt.',
        $pair,
      ));
    }
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

          if (self::isExempt($import)) {
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

  /**
   * Method isExempt.
   *
   * @static
   *
   * Tells whether an import is one of the framework idioms that are not module
   * coupling. Matched as a prefix so a grouped `use A\B\{C, D};` — which the
   * pattern above captures up to the brace — still resolves.
   *
   * @param string $import the fully qualified import prefix
   *
   * @return bool true when the import must not be counted
   */
  private static function isExempt(string $import): bool
  {
    foreach (self::EXEMPT_IMPORTS as $exempt) {
      if (str_starts_with($exempt, $import) || str_starts_with($import, $exempt)) {
        return true;
      }
    }

    return false;
  }
  // #endregion
}
