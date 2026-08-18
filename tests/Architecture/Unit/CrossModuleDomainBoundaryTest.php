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
use function in_array;
use function is_dir;
use function ksort;
use function preg_match_all;
use function scandir;
use function sprintf;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test CrossModuleDomainBoundaryTest.
 *
 * `ARCHITECTURE.md` states that a module may depend on another module's **Port
 * and `Application\Contract` types only** — never on its `Domain`. deptrac
 * CANNOT enforce this: its collectors match a LAYER directory pattern across
 * every module, so an `Application → Application` or `Application → Domain`
 * edge BETWEEN two modules satisfies every declared rule and reports zero
 * violations.
 *
 * The codebase currently violates the rule in 115 places across 41 module
 * pairs. Fixing that is a deliberate, repo-wide refactor (promote a stable
 * access-denied contract, expose permission dependencies through
 * `Application\Contract`, publish event contracts) and is NOT attempted here.
 *
 * What this test does instead is turn an unbounded, invisible debt into a
 * bounded, visible one: it pins the current count per module pair and fails
 * when a pair grows or a new pair appears. Existing debt is tolerated; new
 * debt is not. Lowering a number after cleaning a pair up is expected — the
 * baseline is a ratchet, not a target.
 *
 * `Audit` is exempt as a CONSUMER: it is the hash-chained ledger that fans in
 * every module's domain events by design (`AuditEventSubscriber` subscribes to
 * ~45 event names across 8 modules). Its imports are the architecture working
 * as intended, not debt.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CrossModuleDomainBoundaryTest extends TestCase
{
  // #region Constants
  /**
   * Consumer modules whose cross-module `Domain` imports are by design.
   */
  private const array EXEMPT_CONSUMERS = ['Audit'];

  /**
   * Known cross-module `Domain` imports, keyed `Consumer => Provider`.
   *
   * Captured 2026-07-19. A pair may only shrink or disappear.
   * Refreshed 2026-08-10: every `=> Notification` pair was cleaned up by
   * intervening commits and removed; `Intervention => Organization` was
   * raised 4 → 5 for the reviewer-notification services added by 44da9e06 —
   * see `src/Intervention/MODULE.md` (Architecture debt).
   * Refreshed 2026-08-18: `Facility => Organization` was raised 5 → 7 for the
   * subtree duplication added by 32c1141b — see `src/Facility/MODULE.md`
   * (Architecture debt).
   * `Inspection => Organization` raised 2 → 3: the gated non-conformity
   * waiver must answer 403, not 500, when the caller lacks
   * `organization.approvals.request`, and `ApprovalGate` signals that by
   * throwing `OrganizationAccessDeniedException` — Approval's own
   * `ApprovalExceptionMapperTrait` catches the very same class for the very
   * same reason (`Approval => Organization`, baseline 4). Mapping it at the
   * Presentation boundary is the established treatment here; see
   * `src/Inspection/MODULE.md` (Architecture debt).
   */
  private const array BASELINE = [
    'Approval => Organization' => 4,
    'Assistant => Organization' => 1,
    'Auth => Otp' => 9,
    'Auth => User' => 6,
    'Authorization => User' => 1,
    'Automation => Inspection' => 1,
    'Calendar => Organization' => 1,
    'Compliance => Organization' => 9,
    'Equipment => Approval' => 1,
    'Equipment => Intervention' => 8,
    'Equipment => Messaging' => 1,
    'Equipment => Organization' => 4,
    'Facility => Intervention' => 5,
    'Facility => Messaging' => 1,
    'Facility => Organization' => 7,
    'Import => Organization' => 2,
    'Inspection => Approval' => 1,
    'Inspection => Intervention' => 6,
    'Inspection => Messaging' => 1,
    'Inspection => Organization' => 3,
    'Intervention => Messaging' => 1,
    'Intervention => Organization' => 5,
    'Maintenance => Organization' => 2,
    'Messaging => Organization' => 3,
    'OAuth => Auth' => 4,
    'Onboarding => Organization' => 2,
    'Organization => Equipment' => 2,
    'Organization => Facility' => 2,
    'Organization => Inspection' => 2,
    'Organization => User' => 6,
    'User => Authorization' => 2,
    'Webhook => Equipment' => 1,
    'Webhook => Facility' => 1,
    'Webhook => Inspection' => 2,
    'Webhook => Intervention' => 1,
    'Webhook => Maintenance' => 1,
    'Webhook => Organization' => 1,
  ];
  // #endregion

  // #region Methods
  /**
   * Method testCrossModuleDomainImportsDoNotGrow.
   *
   * Fails when a module starts importing another module's `Domain` namespace
   * more than it already did, or starts importing one it never touched.
   *
   * @return void no return value
   */
  #[Test]
  public function testCrossModuleDomainImportsDoNotGrow(): void
  {
    $actual = self::countCrossModuleDomainImports();

    foreach ($actual as $pair => $count) {
      $allowed = self::BASELINE[$pair] ?? 0;

      self::assertLessThanOrEqual($allowed, $count, sprintf(
        'Cross-module Domain boundary regression: "%s" now has %d import(s), baseline allows %d. '
        . 'ARCHITECTURE.md permits depending on another module\'s Port and Application\Contract types only, '
        . 'never its Domain. deptrac cannot catch this (its collectors are layer-shaped, not module-shaped), '
        . 'which is why this ratchet exists. Introduce a Port or a Contract type instead of importing the '
        . 'foreign Domain class. If the import is genuinely unavoidable, raise the baseline DELIBERATELY and '
        . 'say why in the module\'s MODULE.md.',
        $pair,
        $count,
        $allowed,
      ));
    }
  }

  /**
   * Method testBaselineHasNoStaleEntries.
   *
   * Keeps the baseline honest: once a pair is cleaned up, its entry must go,
   * so the list always reflects real debt rather than accumulating fiction.
   *
   * @return void no return value
   */
  #[Test]
  public function testBaselineHasNoStaleEntries(): void
  {
    $actual = self::countCrossModuleDomainImports();

    foreach (self::BASELINE as $pair => $allowed) {
      self::assertArrayHasKey($pair, $actual, sprintf(
        'Baseline entry "%s" no longer matches any import — the pair was cleaned up. '
        . 'Remove the entry so the baseline keeps reflecting real debt.',
        $pair,
      ));
    }
  }

  /**
   * Method countCrossModuleDomainImports.
   *
   * @static
   *
   * Counts `use <OtherModule>\Domain\…` statements per consumer/provider pair.
   * `Shared` is not a module boundary, self-imports are not cross-module, and
   * exempt consumers are skipped.
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
      if (in_array($module, self::EXEMPT_CONSUMERS, true)) {
        continue;
      }

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
