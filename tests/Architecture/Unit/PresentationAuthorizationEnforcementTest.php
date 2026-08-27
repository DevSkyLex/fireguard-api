<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function implode;
use function sprintf;
use function str_contains;

use const DIRECTORY_SEPARATOR;

/**
 * Test PresentationAuthorizationEnforcementTest.
 *
 * The Presentation-layer counterpart of
 * {@see InterventionAuthorizationEnforcementTest}. Intervention decides access
 * inside its use-case handlers, so its net scans Application/UseCase; the
 * modules covered here decide access in their providers and processors, so the
 * same invariant has to be guarded where it actually lives.
 *
 * The invariant: a provider or processor that injects
 * OrganizationAuthorizationPort must decide through a scope-aware method.
 * The flat hasPermission() returns a boolean, which cannot separate "no active
 * membership in this organization" from "member, but not entitled" — and a
 * caller that maps that single denial to 403 confirms to someone outside the
 * owning organization that the record exists. These call sites resolve their
 * organization from a URI variable or from a record they just loaded, which is
 * exactly the position where the 403/404 difference becomes an existence
 * oracle.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresentationAuthorizationEnforcementTest extends TestCase
{
  // #region Constants
  /**
   * Constant AUTHORIZATION_PORT.
   *
   * The port whose presence makes a file subject to this rule.
   *
   * @var string
   */
  private const string AUTHORIZATION_PORT = 'OrganizationAuthorizationPort';

  /**
   * Constant FLAT_METHOD.
   *
   * The boolean check that cannot carry the scope/entitlement distinction.
   *
   * @var string
   */
  private const string FLAT_METHOD = '->hasPermission(';

  /**
   * Constant SCOPE_AWARE_METHODS.
   *
   * The port methods that can tell "outside the organization" (404) from
   * "member without the permission" (403).
   *
   * @var list<string>
   */
  private const array SCOPE_AWARE_METHODS = ['resolveAccess(', 'isMemberOf('];
  // #endregion

  // #region Methods
  /**
   * Method modules.
   *
   * The modules whose Presentation layer is held to the convention.
   *
   * @static
   *
   * @return iterable<string, array{string}> the module names
   */
  public static function modules(): iterable
  {
    yield 'Facility' => ['Facility'];

    yield 'Equipment' => ['Equipment'];
  }

  /**
   * Method testProvidersAndProcessorsDecideAccessWithScopeAwareMethods.
   *
   * @param string $module the module name
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('modules')]
  public function testProvidersAndProcessorsDecideAccessWithScopeAwareMethods(string $module): void
  {
    $apiDir = dirname(__DIR__, 3)
      . DIRECTORY_SEPARATOR . 'src'
      . DIRECTORY_SEPARATOR . $module
      . DIRECTORY_SEPARATOR . 'Presentation'
      . DIRECTORY_SEPARATOR . 'Api';

    self::assertDirectoryExists($apiDir, $module . ' Presentation/Api directory is missing.');

    $violations = [];
    $checkedCount = 0;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($apiDir));
    foreach ($iterator as $file) {
      if (!$file instanceof SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
        continue;
      }
      $shortName = $file->getBasename('.php');
      $contents = file_get_contents($file->getPathname());
      if (false === $contents || !str_contains($contents, self::AUTHORIZATION_PORT)) {
        continue;
      }
      ++$checkedCount;

      if (str_contains($contents, self::FLAT_METHOD)) {
        $violations[] = sprintf(
          '%s still calls the flat hasPermission(): a boolean cannot separate "outside the organization" (404) '
          . 'from "member without the permission" (403), and the 403 it returns instead confirms to an outsider '
          . 'that the record exists. Use resolveAccess() and map OUTSIDE_SCOPE to the same not-found response an '
          . 'unknown identifier produces.',
          $shortName,
        );

        continue;
      }

      $isScopeAware = false;
      foreach (self::SCOPE_AWARE_METHODS as $method) {
        if (str_contains($contents, $method)) {
          $isScopeAware = true;

          break;
        }
      }
      if (!$isScopeAware) {
        $violations[] = sprintf(
          '%s injects %s but decides access through none of %s.',
          $shortName,
          self::AUTHORIZATION_PORT,
          '`' . implode('` / `', self::SCOPE_AWARE_METHODS) . '`',
        );
      }
    }

    self::assertGreaterThan(0, $checkedCount, 'No ' . $module . ' provider or processor was discovered.');
    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Every ' . $module . ' provider and processor must decide organization access with a scope-aware method.',
    );
  }
  // #endregion
}
