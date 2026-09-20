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
 * Test InterventionAuthorizationEnforcementTest.
 *
 * Guards the security invariant that every Intervention use-case handler that
 * acts on behalf of a user enforces an organization permission check through
 * the OrganizationAuthorizationPort, directly or through an invoked and checked
 * application policy. Without this net, a newly added handler
 * could silently expose intervention data to any authenticated user, since the
 * API layer only requires ROLE_USER and delegates real authorization here.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAuthorizationEnforcementTest extends TestCase
{
  // #region Constants
  /**
   * Constant AUTHORIZATION_PORT.
   *
   * The port every user-facing authorization boundary must depend on.
   *
   * @var string
   */
  private const string AUTHORIZATION_PORT = 'OrganizationAuthorizationPort';

  /**
   * Constant EXEMPT_HANDLERS.
   *
   * Handlers that legitimately skip the user permission check. Each entry must
   * be justified: ExecutePublicationHandler runs asynchronously off the message
   * queue with no user context — its authorization happens upstream in
   * RequestPublicationHandler before the job is dispatched.
   * MaterializeDueRecurrencesHandler is the scheduler-driven sweep that turns
   * due recurrences into drafts: it acts as the platform (system actor, no
   * user), and the recurrence itself was authorized at creation time
   * (organization.interventions.plan in CreateInterventionRecurrenceHandler).
   * SendDueRemindersHandler is the same kind of scheduler-driven sweep (no
   * user context, system actor): it only reads interventions and sends
   * best-effort notifications to their existing responsible/participants —
   * membership itself was already authorized when those were assigned.
   *
   * @var list<string>
   */
  private const array EXEMPT_HANDLERS = ['ExecutePublicationHandler', 'MaterializeDueRecurrencesHandler', 'SendDueRemindersHandler'];

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
   * Ensures every user-facing handler checks organization scope directly or
   * invokes the required policy methods. Delegation is not an authorization exemption:
   * the policy itself must depend on the port and perform a scope-aware check.
   *
   * @return void no return value
   */
  #[Test]
  public function testUserFacingHandlersEnforceAuthorization(): void
  {
    $useCaseDir = dirname(__DIR__, 3)
      . DIRECTORY_SEPARATOR . 'src'
      . DIRECTORY_SEPARATOR . 'Intervention'
      . DIRECTORY_SEPARATOR . 'Application'
      . DIRECTORY_SEPARATOR . 'UseCase';

    self::assertDirectoryExists($useCaseDir, 'Intervention UseCase directory is missing.');

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
      if (false !== $contents && !str_contains($contents, self::AUTHORIZATION_PORT)) {
        $contents = $this->authorizationPolicySource($shortName, $contents);
      }
      if (false === $contents || null === $contents || !str_contains($contents, self::AUTHORIZATION_PORT)) {
        $violations[] = sprintf(
          '%s must enforce %s directly or through an invoked scope-aware authorization policy.',
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

    self::assertGreaterThan(0, $handlerCount, 'No Intervention handlers were discovered.');
    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every user-facing Intervention handler must enforce an organization permission.',
    );
  }

  /**
   * Rejects a merely injected policy or an incomplete delegated authorization flow.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testDelegatedPolicyRequiresEveryAuthorizationCall(): void
  {
    $dependency = 'InterventionTimeAccessPolicy $access;';
    self::assertNull($this->authorizationPolicySource('WriteTimeEntryHandler', $dependency));
    self::assertNull($this->authorizationPolicySource('WriteTimeEntryHandler', $dependency . ' $this->access->actor('));
    self::assertNull($this->authorizationPolicySource('ListTimeEntriesHandler', $dependency . ' $this->access->canManage('));
    self::assertNull($this->authorizationPolicySource('ListTimeEntriesHandler', $dependency . ' $this->access->actor('));
    self::assertNull($this->authorizationPolicySource('UnknownHandler', $dependency . ' $this->access->assertWrite('));
    self::assertNotNull($this->authorizationPolicySource('WriteTimeEntryHandler', $dependency . ' $this->access->assertWrite('));
    self::assertNotNull($this->authorizationPolicySource('ListTimeEntriesHandler', $dependency . ' $this->access->actor( $this->access->canManage('));
  }

  /**
   * Resolves the authorization source for the independent time journal.
   *
   * The write policy checks membership, permission and contributor scope; reads
   * require both actor scoping and the dedicated management-permission decision.
   * Other handlers retain the direct-port rule. Policy denial paths are tested
   * independently in InterventionTimeAccessPolicyTest and the API suite.
   *
   * @since 1.0.0
   *
   * @param string $handler discovered use-case class name
   * @param string $contents handler source inspected by the architecture guard
   *
   * @return ?string policy source, or null when the required delegation is absent
   */
  private function authorizationPolicySource(string $handler, string $contents): ?string
  {
    $requiredCalls = match ($handler) {
      'WriteTimeEntryHandler' => ['assertWrite'],
      'ListTimeEntriesHandler' => ['actor', 'canManage'],
      default => [],
    };
    if ([] === $requiredCalls || !str_contains($contents, 'InterventionTimeAccessPolicy')) {
      return null;
    }
    foreach ($requiredCalls as $method) {
      if (!str_contains($contents, '->' . $method . '(')) {
        return null;
      }
    }
    $policy = file_get_contents(dirname(__DIR__, 3) . '/src/Intervention/Application/Service/InterventionTimeAccessPolicy.php');

    return false !== $policy && str_contains($policy, self::AUTHORIZATION_PORT) ? $policy : null;
  }
  // #endregion
}
