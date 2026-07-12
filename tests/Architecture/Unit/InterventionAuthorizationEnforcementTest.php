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
use function sprintf;
use function str_contains;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test InterventionAuthorizationEnforcementTest.
 *
 * Guards the security invariant that every Intervention use-case handler that
 * acts on behalf of a user enforces an organization permission check through
 * the OrganizationAuthorizationPort. Without this net, a newly added handler
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
   * The port every user-facing handler must depend on.
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
   *
   * @var list<string>
   */
  private const array EXEMPT_HANDLERS = ['ExecutePublicationHandler'];
  // #endregion

  // #region Methods
  /**
   * Method testUserFacingHandlersEnforceAuthorization.
   *
   * Ensures every Intervention use-case handler depends on the authorization
   * port unless it is an explicitly exempt internal handler.
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
      if (false === $contents || !str_contains($contents, self::AUTHORIZATION_PORT)) {
        $violations[] = sprintf(
          '%s must depend on %s (or be added to EXEMPT_HANDLERS with a justification).',
          $shortName,
          self::AUTHORIZATION_PORT,
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
  // #endregion
}
