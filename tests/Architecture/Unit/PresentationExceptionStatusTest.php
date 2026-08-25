<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_key_exists;
use function array_keys;
use function count;
use function dirname;
use function explode;
use function file;
use function implode;
use function ksort;
use function min;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function strrchr;
use function substr;

use const DIRECTORY_SEPARATOR;
use const FILE_IGNORE_NEW_LINES;

/**
 * Test PresentationExceptionStatusTest.
 *
 * `.claude/rules/presentation.md` states that a domain exception is mapped to
 * an HTTP status **centrally in an EventSubscriber, never try/catch at each
 * call site**. 258 Presentation files still do the opposite, across 419
 * `catch`-shaped mapping sites — while several modules (Approval, Webhook,
 * Assistant, Compliance, Import, Maintenance, Messaging, Calendar,
 * Intervention) already centralize their own in a mapper trait built on
 * `match (true) { $x instanceof … }`. Both shapes are read here.
 *
 * Measured across all 92 exceptions, the mapping is **consistent**: each
 * resolves to one status everywhere. That consistency is a property of the
 * authors' discipline, not of the code — and it has already failed once.
 * `PATCH /organizations/{id}/roles/{roleId}` answered 400 where its four
 * sibling operations answered 404 for the identical case; MODULE.md documented
 * the divergence instead of fixing it, and a functional test pinned it
 * (corrected in PR #114).
 *
 * This ratchet turns the convention into a check. It is step 0 of FG-035: the
 * baseline below is the map the centralized mapping must reproduce exactly, so
 * that migrating it later can be proven to change no status at all.
 *
 * It is deliberately a snapshot of what the code does **today**, including the
 * anomalies noted in ANOMALIES. Correcting those changes a published status and
 * belongs to its own decision, not to the test that freezes the baseline.
 *
 * @category Architecture Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresentationExceptionStatusTest extends TestCase
{
  // #region Constants
  /**
   * Wrappers, not domain exceptions.
   *
   * `MessengerRuntimeException` is what `MessengerCommandBusAdapter` throws
   * around every handler failure, and `Throwable` is a deliberate catch-all.
   * The status they map to depends on what they *contain*, so several statuses
   * per class is correct here and means nothing about consistency.
   *
   * @var list<string>
   */
  private const array WRAPPERS = [
    'MessengerRuntimeException',
    'Throwable',
    'Exception',
  ];

  /**
   * The two places where the frozen baseline records something questionable.
   *
   * Kept as data rather than as prose so the next reader meets them, and so
   * that a fix flips an entry here instead of quietly editing BASELINE.
   *
   * @var array<string, string>
   */
  private const array ANOMALIES = [
    'InvalidArgumentException' => 'DeletePlanProcessor maps it to 409 where 77 other sites map it to 400. Either the handler should throw a dedicated conflict exception, or it is a 400.',
    'FacilityArchivedException' => 'RestoreFacilityProcessor catches it grouped with InvalidArgumentException, so it inherits 400. Its two siblings — OrganizationArchivedException, ChecklistArchivedException — are 409, and its own message says the facility "cannot be used", which is a state conflict.',
  ];

  /**
   * Exception short name => the single HTTP exception it maps to.
   *
   * Extracted from the Presentation layer on develop @ 57a8d0f9 — 92
   * exceptions, from both the `catch` sites and the `match (true)` mapper
   * traits.
   *
   * A mapping site references the caught exception. A `throw` carrying a
   * literal message is a **presentation guard** — validating a URI variable,
   * refusing an anonymous caller — and is out of scope: 827 of those exist and
   * none of them moves.
   *
   * @var array<string, string>
   */
  private const array BASELINE = [
    'ApprovalAccessDeniedException' => 'AccessDeniedHttpException',
    'ApprovalRequestNotFoundException' => 'NotFoundHttpException',
    'ApprovalRequestNotPendingException' => 'ConflictHttpException',
    'ApproverNotAuthorizedException' => 'AccessDeniedHttpException',
    'AssistantDisabledException' => 'AccessDeniedHttpException',
    'AssistantMessageIllegalStatusTransitionException' => 'ConflictHttpException',
    'AssistantThreadNotFoundException' => 'NotFoundHttpException',
    'AssistantValidationException' => 'UnprocessableEntityHttpException',
    'AttachmentNotFoundException' => 'NotFoundHttpException',
    'CalendarEventNotFoundException' => 'NotFoundHttpException',
    'CalendarEventValidationException' => 'UnprocessableEntityHttpException',
    'ChecklistArchivedException' => 'ConflictHttpException',
    'ChecklistInUseException' => 'ConflictHttpException',
    'ChecklistNotFoundException' => 'NotFoundHttpException',
    'ChecklistReferenceCodeAlreadyExistsException' => 'ConflictHttpException',
    'ComplianceAccessDeniedException' => 'AccessDeniedHttpException',
    'ComplianceExportNotEntitledException' => 'AccessDeniedHttpException',
    'ComplianceNotFoundException' => 'NotFoundHttpException',
    'DeferredActionNoLongerApplicableException' => 'ConflictHttpException',
    'EquipmentAlreadyDecommissionedException' => 'ConflictHttpException',
    'EquipmentNotFoundException' => 'NotFoundHttpException',
    'EquipmentSerialNumberAlreadyExistsException' => 'ConflictHttpException',
    'FacilityArchivedException' => 'BadRequestHttpException',
    'FacilityAttachmentNotAncestorException' => 'ConflictHttpException',
    'FacilityAttachmentNotFloorPlanException' => 'ConflictHttpException',
    'FacilityAttachmentNotFoundException' => 'NotFoundHttpException',
    'FacilityCodeAlreadyExistsException' => 'ConflictHttpException',
    'FacilityHierarchyException' => 'BadRequestHttpException',
    'FacilityMetadataFieldKeyAlreadyExistsException' => 'ConflictHttpException',
    'FacilityMetadataFieldLimitExceededException' => 'UnprocessableEntityHttpException',
    'FacilityMetadataFieldNotFoundException' => 'NotFoundHttpException',
    'FacilityNotFoundException' => 'NotFoundHttpException',
    'FacilitySubtreeSourceArchivedException' => 'ConflictHttpException',
    'FacilitySubtreeTooLargeException' => 'UnprocessableEntityHttpException',
    'FloorPlanAttachmentNotFoundException' => 'NotFoundHttpException',
    'ImportAccessDeniedException' => 'AccessDeniedHttpException',
    'ImportJobNotFoundException' => 'NotFoundHttpException',
    'InspectionAlreadyCancelledException' => 'ConflictHttpException',
    'InspectionAlreadyClosedException' => 'ConflictHttpException',
    'InspectionAlreadySubmittedException' => 'ConflictHttpException',
    'InspectionNotFoundException' => 'NotFoundHttpException',
    'InspectionNotSubmittedException' => 'ConflictHttpException',
    'InterventionAccessDeniedException' => 'AccessDeniedHttpException',
    'InterventionBlockedException' => 'UnprocessableEntityHttpException',
    'InterventionConflictException' => 'ConflictHttpException',
    'InterventionExportTooLargeException' => 'UnprocessableEntityHttpException',
    'InterventionNotFoundException' => 'NotFoundHttpException',
    'InterventionPreconditionFailedException' => 'PreconditionFailedHttpException',
    'InterventionPreconditionRequiredException' => 'PreconditionRequiredHttpException',
    'InterventionResourceNotFoundException' => 'NotFoundHttpException',
    'InterventionValidationException' => 'UnprocessableEntityHttpException',
    'InvalidArgumentException' => 'BadRequestHttpException',
    'InvalidAttachmentException' => 'UnprocessableEntityHttpException',
    'InvalidValueException' => 'BadRequestHttpException',
    'LogicException' => 'ConflictHttpException',
    'MaintenanceAccessDeniedException' => 'AccessDeniedHttpException',
    'MaintenanceNotFoundException' => 'NotFoundHttpException',
    'MaintenanceValidationException' => 'UnprocessableEntityHttpException',
    'MessagingAccessDeniedException' => 'AccessDeniedHttpException',
    'MessagingAttachmentNotFoundException' => 'NotFoundHttpException',
    'MessagingConflictException' => 'ConflictHttpException',
    'MessagingNotFoundException' => 'NotFoundHttpException',
    'MessagingSubjectNotFoundException' => 'NotFoundHttpException',
    'MessagingValidationException' => 'UnprocessableEntityHttpException',
    'NonConformityAlreadyResolvedException' => 'ConflictHttpException',
    'NonConformityNotFoundException' => 'NotFoundHttpException',
    'OrganizationAccessDeniedException' => 'AccessDeniedHttpException',
    'OrganizationArchivedException' => 'ConflictHttpException',
    'OrganizationDeletionConfirmationMismatchException' => 'UnprocessableEntityHttpException',
    'OrganizationInvitationNotFoundException' => 'NotFoundHttpException',
    'OrganizationLastAdminException' => 'ConflictHttpException',
    'OrganizationMemberNotFoundException' => 'NotFoundHttpException',
    'OrganizationNotFoundException' => 'NotFoundHttpException',
    'OrganizationOwnerCannotLeaveException' => 'ConflictHttpException',
    'OrganizationOwnershipUnchangedException' => 'ConflictHttpException',
    'OrganizationPlanUsageExceededException' => 'ConflictHttpException',
    'OrganizationRoleNotFoundException' => 'NotFoundHttpException',
    'OrganizationSlugAlreadyExistsException' => 'ConflictHttpException',
    'PlanKeyAlreadyExistsException' => 'ConflictHttpException',
    'PlanNotFoundException' => 'NotFoundHttpException',
    'PublicationNotFoundException' => 'NotFoundHttpException',
    'SelfApprovalNotAllowedException' => 'AccessDeniedHttpException',
    'TagNotFoundException' => 'NotFoundHttpException',
    'TeamMemberNotFoundException' => 'NotFoundHttpException',
    'TeamNameAlreadyExistsException' => 'ConflictHttpException',
    'TeamNotFoundException' => 'NotFoundHttpException',
    'ValueError' => 'BadRequestHttpException',
    'WebhookDeliveryNotFoundException' => 'NotFoundHttpException',
    'WebhookSubscriptionNotFoundException' => 'NotFoundHttpException',
    'WebhookValidationException' => 'UnprocessableEntityHttpException',
  ];
  // #endregion

  // #region Tests
  /**
   * Method testEveryDomainExceptionMapsToOneStatus.
   *
   * The property FG-035 exists to protect: one exception, one status, whatever
   * the endpoint.
   */
  #[Test]
  public function testEveryDomainExceptionMapsToOneStatus(): void
  {
    $divergent = [];

    foreach (self::collectMappings() as $exception => $statuses) {
      // A recorded anomaly is still a divergence; it is simply one that has
      // been looked at and written down. It fails testAnomaliesStayVisible if
      // the record is ever dropped without the code being fixed.
      if (array_key_exists($exception, self::ANOMALIES)) {
        continue;
      }

      if (count($statuses) > 1) {
        $divergent[] = sprintf('%s => %s', $exception, implode(', ', array_keys($statuses)));
      }
    }

    self::assertSame(
      [],
      $divergent,
      "An exception is mapped to more than one HTTP status across the Presentation layer.\n"
      . "The same failure must read the same way on every endpoint — a caller cannot branch on a status that\n"
      . "depends on which route produced it. Pick one and make the other site agree, or introduce a distinct\n"
      . "exception if the two cases are genuinely different.\n\n"
      . implode("\n", $divergent),
    );
  }

  /**
   * Method testNoMappingLeavesTheBaseline.
   *
   * The ratchet. A new exception must be declared, and an existing one must not
   * silently change status — that change is a contract change for every client
   * already branching on it.
   */
  #[Test]
  public function testNoMappingLeavesTheBaseline(): void
  {
    $problems = [];

    foreach (self::collectMappings() as $exception => $statuses) {
      $actual = array_keys($statuses)[0];

      if (!array_key_exists($exception, self::BASELINE)) {
        $problems[] = sprintf(
          '%s is mapped to %s but is absent from BASELINE — add it, deliberately.',
          $exception,
          $actual,
        );

        continue;
      }

      if (self::BASELINE[$exception] !== $actual) {
        $problems[] = sprintf(
          '%s now maps to %s, the baseline says %s. That is a contract change for every client branching on it.',
          $exception,
          $actual,
          self::BASELINE[$exception],
        );
      }
    }

    self::assertSame([], $problems, implode("\n", $problems));
  }

  /**
   * Method testTheBaselineIsNotStale.
   *
   * An entry left behind after its last call site disappeared is a baseline
   * that no longer describes the code.
   */
  #[Test]
  public function testTheBaselineIsNotStale(): void
  {
    $mappings = self::collectMappings();
    $orphans = [];

    foreach (self::BASELINE as $exception => $_status) {
      if (!array_key_exists($exception, $mappings)) {
        $orphans[] = $exception;
      }
    }

    self::assertSame(
      [],
      $orphans,
      "BASELINE declares exceptions no Presentation file maps any more. Remove them.\n"
      . 'During the FG-035 migration this is EXPECTED: entries move out as each module stops catching. '
      . "Delete the entry in the same PR that removes the last catch.\n\n"
      . implode("\n", $orphans),
    );
  }

  /**
   * Method testAnomaliesStayVisible.
   *
   * Guards the record itself: an anomaly may only leave ANOMALIES when the code
   * stops being anomalous, and the baseline must still describe it until then.
   */
  #[Test]
  public function testAnomaliesStayVisible(): void
  {
    foreach (self::ANOMALIES as $exception => $why) {
      self::assertArrayHasKey(
        $exception,
        self::BASELINE,
        sprintf('%s is recorded as an anomaly but has no baseline entry: %s', $exception, $why),
      );
    }
  }
  // #endregion

  // #region Helpers
  /**
   * Method collectMappings.
   *
   * Walks the Presentation layer and pairs every `catch` with the first
   * `throw new …HttpException` that references the caught variable.
   *
   * A throw carrying a literal message is a presentation guard — validating a
   * URI variable, refusing an anonymous caller — and is not a mapping. Those
   * stay where they are and are excluded here.
   *
   * @return array<string, array<string, int>> statuses per exception short name
   */
  private static function collectMappings(): array
  {
    $sourceRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
    $mappings = [];

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
        continue;
      }

      $path = str_replace('\\', '/', $file->getPathname());
      if (!str_contains($path, '/Presentation/')) {
        continue;
      }

      $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
      if (false === $lines) {
        continue;
      }

      self::collectFromLines($lines, $mappings);
      self::collectFromMatchArms($lines, $mappings);
      self::collectFromFinderMethods($lines, $mappings);
    }

    ksort($mappings);

    return $mappings;
  }

  /**
   * Method collectFromLines.
   *
   * @param list<string> $lines the file's lines
   * @param array<string, array<string, int>> $mappings the accumulator, by reference
   */
  private static function collectFromLines(array $lines, array &$mappings): void
  {
    $total = count($lines);

    foreach ($lines as $index => $line) {
      if (1 !== preg_match('/catch \(([A-Za-z0-9_|\\\\]+)\s+\$(\w+)\)/', $line, $caughtMatch)) {
        continue;
      }

      $variable = $caughtMatch[2];
      $status = self::findMappedStatus($lines, $index, $variable, $total);
      if (null === $status) {
        continue;
      }

      foreach (explode('|', $caughtMatch[1]) as $class) {
        $short = substr((string) strrchr('\\' . $class, '\\'), 1);
        if (self::isWrapper($short)) {
          continue;
        }

        $mappings[$short][$status] = ($mappings[$short][$status] ?? 0) + 1;
      }
    }
  }

  /**
   * Method collectFromMatchArms.
   *
   * Reads the `match (true) { $x instanceof A => new …HttpException }` shape.
   *
   * `Approval` and `Webhook` already centralize their own mapping in a mapper
   * trait built that way — unwrap by walking `getPrevious()`, then map by
   * `instanceof`. That is the FG-035 target, done per module. Missing it here
   * would leave the ratchet blind to the two modules that already got it right.
   *
   * Several `instanceof` arms may share one result, so pending names accumulate
   * until the arm that carries the `=>`.
   *
   * @param list<string> $lines the file's lines
   * @param array<string, array<string, int>> $mappings the accumulator, by reference
   */
  private static function collectFromMatchArms(array $lines, array &$mappings): void
  {
    $pending = [];

    foreach ($lines as $line) {
      if (1 !== preg_match('/instanceof ([A-Za-z0-9_\\\\]+)/', $line, $matched)) {
        $pending = [];

        continue;
      }

      $pending[] = substr((string) strrchr('\\' . $matched[1], '\\'), 1);

      if (1 !== preg_match('/=> new (\w*HttpException)\(/', $line, $thrown)) {
        continue;
      }

      foreach ($pending as $short) {
        if (!self::isWrapper($short)) {
          $mappings[$short][$thrown[1]] = ($mappings[$short][$thrown[1]] ?? 0) + 1;
        }
      }

      $pending = [];
    }
  }

  /**
   * Method collectFromFinderMethods.
   *
   * Reads the third shape: `catch (Throwable)` plus a private finder.
   *
   * ```php
   * } catch (Throwable $exception) {
   *   $notFound = $this->findSessionNotFound($exception);
   *   if (null !== $notFound) {
   *     throw new NotFoundHttpException($notFound->getMessage(), $exception);
   *   }
   * ```
   *
   * 128 Presentation files do this, and the catch names only `Throwable` — so
   * the two other collectors see a wrapper and skip the file entirely. Missing
   * it is not cosmetic: the exceptions mapped this way would have no entry in
   * `exception_to_status`, and retiring their catch would turn a 404 into a 500.
   *
   * The exception's identity comes from the finder's **return type**, which is
   * the one place it is stated unambiguously.
   *
   * @param list<string> $lines the file's lines
   * @param array<string, array<string, int>> $mappings the accumulator, by reference
   */
  private static function collectFromFinderMethods(array $lines, array &$mappings): void
  {
    $returnTypes = [];
    $assignments = [];

    // Two passes on purpose: the private finders are declared BELOW the throws
    // that use them, so a single forward pass never knows their return type by
    // the time it meets the throw.
    foreach ($lines as $line) {
      if (1 === preg_match('/private function (\w+)\([^)]*\)\s*:\s*\?\s*([A-Za-z0-9_\\\\]*Exception)\b/', $line, $declared)) {
        $returnTypes[$declared[1]] = substr((string) strrchr('\\' . $declared[2], '\\'), 1);
      }

      if (1 === preg_match('/\$(\w+)\s*=\s*\$this->(\w+)\(/', $line, $assigned)) {
        $assignments[$assigned[1]] = $assigned[2];
      }
    }

    foreach ($lines as $line) {
      if (1 !== preg_match('/throw new (\w*HttpException)\(\$(\w+)->getMessage\(\)/', $line, $thrown)) {
        continue;
      }

      $finder = $assignments[$thrown[2]] ?? null;
      $short = null === $finder ? null : ($returnTypes[$finder] ?? null);

      if (null === $short || self::isWrapper($short)) {
        continue;
      }

      $mappings[$short][$thrown[1]] = ($mappings[$short][$thrown[1]] ?? 0) + 1;
    }
  }

  /**
   * Method findMappedStatus.
   *
   * @param list<string> $lines the file's lines
   * @param int $index the line the catch sits on
   * @param string $variable the caught variable name
   * @param int $total the file's line count
   *
   * @return string|null the HTTP exception short name, or null when the block maps nothing
   */
  private static function findMappedStatus(array $lines, int $index, string $variable, int $total): ?string
  {
    for ($cursor = $index + 1; $cursor < min($index + 40, $total); ++$cursor) {
      $line = $lines[$cursor];

      // The block ended before anything was mapped.
      if (1 === preg_match('/^\s*\} catch \(/', $line) || 1 === preg_match('/^\s*\}\s*$/', $line)) {
        return null;
      }

      if (
        1 === preg_match('/throw new (\w*HttpException)\(\$' . $variable . '\b/', $line, $thrown)
        || 1 === preg_match('/throw new (\w*HttpException)\(\$\w+->getMessage\(\)/', $line, $thrown)
      ) {
        return $thrown[1];
      }
    }

    return null;
  }

  /**
   * Method isWrapper.
   *
   * @param string $shortName the caught class short name
   *
   * @return bool true when the class is a bus wrapper or a catch-all
   */
  private static function isWrapper(string $shortName): bool
  {
    foreach (self::WRAPPERS as $wrapper) {
      if ($wrapper === $shortName) {
        return true;
      }
    }

    return false;
  }
  // #endregion
}
