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
use function implode;
use function in_array;
use function sprintf;
use function str_contains;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test MaintenanceAuthorizationEnforcementTest.
 *
 * Guards the security invariant that every Maintenance use-case handler that
 * acts on behalf of a user enforces an organization permission check through
 * the OrganizationAuthorizationPort. Without this net, a newly added handler
 * could silently expose maintenance data to any authenticated user, since the
 * API layer only requires ROLE_USER and delegates real authorization here.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceAuthorizationEnforcementTest extends TestCase
{
  // #region Constants
  /**
   * Constant AUTHORIZATION_PORT.
   *
   * The port every user-facing handler must depend on.
   *
   * @var string
   */
  private const string AUTHORIZATION_PORT = 'OrganizationAuthorizationPort';

  /**
   * Constant EXEMPT_HANDLERS.
   *
   * Handlers that legitimately skip the user permission check. Each entry must
   * be justified: RecomputeMaintenanceSchedulesHandler is the scheduler-driven
   * hourly sweep that reconciles, recomputes, and reminds across EVERY
   * organization — it acts as the platform (system actor, no user context),
   * and its only outward effect is a best-effort reminder to the owning
   * organization's own administrators, gated by that organization's
   * notification policy.
   *
   * @var list<string>
   */
  private const array EXEMPT_HANDLERS = ['RecomputeMaintenanceSchedulesHandler'];

  /**
   * Constant SCOPE_AWARE_METHODS.
   *
   * The port methods that can tell "not a member of this organization" from
   * "member, but not entitled". A handler that only calls the flat
   * hasPermission() collapses both into 403, and since these handlers look a
   * record up by path id before they know who owns it, that 403 confirms the
   * record exists to a caller from another organization.
   *
   * @var list<string>
   */
  private const array SCOPE_AWARE_METHODS = ['resolveAccess', 'isMemberOf'];
  // #endregion

  // #region Methods
  /**
   * Method testUserFacingHandlersEnforceAuthorization.
   *
   * Ensures every Maintenance use-case handler depends on the authorization
   * port unless it is an explicitly exempt internal handler.
   *
   * @return void no return value
   */
  #[Test]
  public function testUserFacingHandlersEnforceAuthorization(): void
  {
    $useCaseDir = dirname(__DIR__, 3)
      . DIRECTORY_SEPARATOR . 'src'
      . DIRECTORY_SEPARATOR . 'Maintenance'
      . DIRECTORY_SEPARATOR . 'Application'
      . DIRECTORY_SEPARATOR . 'UseCase';

    self::assertDirectoryExists($useCaseDir, 'Maintenance UseCase directory is missing.');

    $violations = [];
    $handlerCount = 0;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($useCaseDir));
    foreach ($iterator as $file) {
      if (!$file instanceof SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
        continue;
      }
      $shortName = $file->getBasename('.php');
      if (!str_ends_with($shortName, 'Handler')) {
        continue;
      }
      ++$handlerCount;
      if (in_array($shortName, self::EXEMPT_HANDLERS, true)) {
        continue;
      }
      $contents = file_get_contents($file->getPathname());
      if (false === $contents || !str_contains($contents, self::AUTHORIZATION_PORT)) {
        $violations[] = sprintf(
          '%s must depend on %s (or be added to EXEMPT_HANDLERS with a justification).',
          $shortName,
          self::AUTHORIZATION_PORT,
        );

        continue;
      }

      $isScopeAware = false;
      foreach (self::SCOPE_AWARE_METHODS as $method) {
        if (str_contains($contents, $method . '(')) {
          $isScopeAware = true;

          break;
        }
      }
      if (!$isScopeAware) {
        $violations[] = sprintf(
          '%s must decide access through one of %s, not the flat hasPermission() alone: a boolean cannot '
          . 'separate "outside the organization" (404) from "member without the permission" (403), and the '
          . '403 it returns instead confirms to an outsider that the record exists.',
          $shortName,
          '`' . implode('` / `', self::SCOPE_AWARE_METHODS) . '`',
        );
      }
    }

    self::assertGreaterThan(0, $handlerCount, 'No Maintenance handlers were discovered.');
    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every user-facing Maintenance handler must enforce an organization permission.',
    );
  }
  // #endregion
}
