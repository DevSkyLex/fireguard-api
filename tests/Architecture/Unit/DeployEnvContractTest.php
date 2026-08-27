<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function dirname;
use function file_get_contents;
use function implode;
use function is_array;
use function is_string;
use function preg_match_all;
use function sort;
use function sprintf;
use function str_starts_with;

/**
 * Test DeployEnvContractTest.
 *
 * The production image ships no `.env` — `.dockerignore` excludes `.env` and
 * `.env.*` and keeps only `.env.example`, which symfony/dotenv never loads. So
 * every key the application reads has to arrive as a real environment variable,
 * and the deploy has exactly one place to get it from: the env block of
 * `.github/workflows/deploy-vps.yml`.
 *
 * Nothing linked those two files. A key added to `.env.example` and forgotten in
 * the workflow renders empty; a key removed from `.env.example` and left in the
 * workflow is a value nobody reads. The first shape is how production spent
 * three months undeployable with 23 keys missing.
 *
 * This test is the link. It recomputes the required set the way
 * `ansible/deploy.yml` does — `.env.example`, plus the `${VAR:?...}` entries of
 * `compose.prod.yaml`, minus what compose pins in its own `environment:` blocks
 * — and demands the workflow declare exactly that.
 *
 * @category Architecture Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DeployEnvContractTest extends TestCase
{
  // #region Constants
  /**
   * Written by the template rather than supplied by the workflow, so none of
   * these is ever declared in the env block. FIREGUARD_IMAGE comes from the build
   * job; the two connection URLs are composed from the POSTGRES_* parts so that
   * the password has one source and cannot disagree with itself.
   *
   * @var list<string>
   */
  private const array COMPUTED_KEYS = ['FIREGUARD_IMAGE', 'AUTH_DATABASE_URL', 'MAIN_DATABASE_URL'];
  // #endregion

  // #region Tests
  /**
   * Method testTheWorkflowSuppliesEveryKeyTheDeployWillRequire.
   *
   * A key missing here renders as `KEY=` in the generated file — and an empty
   * value is not a missing value, it is a working one that happens to be wrong.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheWorkflowSuppliesEveryKeyTheDeployWillRequire(): void
  {
    $missing = array_values(array_diff($this->requiredKeys(), $this->workflowKeys()));
    sort($missing);

    self::assertSame(
      [],
      $missing,
      sprintf(
        "These keys are required at deploy time and the workflow supplies none of them:\n  %s\n"
        . 'Add them to the env block of .github/workflows/deploy-vps.yml, as vars.* if they are '
        . 'readable configuration and secrets.* if they are not.',
        implode("\n  ", $missing),
      ),
    );
  }

  /**
   * Method testTheWorkflowDeclaresNothingTheDeployWillNotRead.
   *
   * The reverse drift, and the quieter one: a key left in the workflow after
   * `.env.example` stopped declaring it, or after `compose.prod.yaml` started
   * pinning it, is a value nothing reads. It looks configured and is inert.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheWorkflowDeclaresNothingTheDeployWillNotRead(): void
  {
    $orphans = array_values(array_diff($this->workflowKeys(), $this->requiredKeys()));
    sort($orphans);

    self::assertSame(
      [],
      $orphans,
      sprintf(
        "The workflow declares these, and the deploy reads none of them:\n  %s\n"
        . 'Either .env.example stopped declaring the key, or compose.prod.yaml now pins it in an '
        . '`environment:` block, which wins over `env_file:`.',
        implode("\n  ", $orphans),
      ),
    );
  }

  /**
   * Method testAKeyWithADefaultIsNotAlsoDemandedOfProduction.
   *
   * `config/packages/env_defaults.yaml` exists so that a constant cannot go
   * missing. Listing the same key in `.env.example` would undo that: the deploy
   * would demand on the VPS a value the application already knows, and the two
   * could then disagree.
   *
   * @return void no return value
   */
  #[Test]
  public function testAKeyWithADefaultIsNotAlsoDemandedOfProduction(): void
  {
    $both = array_values(array_diff(
      $this->defaultedKeys(),
      array_diff($this->defaultedKeys(), $this->requiredKeys()),
    ));
    sort($both);

    self::assertSame(
      [],
      $both,
      sprintf(
        'These have a default in config/packages/env_defaults.yaml and are still required of '
        . "production:\n  %s\nRemove them from .env.example and from the workflow — the "
        . 'application supplies them.',
        implode("\n  ", $both),
      ),
    );
  }
  // #endregion

  // #region Helpers
  /**
   * Method requiredKeys.
   *
   * Recomputed exactly as `ansible/deploy.yml` does it.
   *
   * @return list<string> the keys the production env file must carry
   */
  private function requiredKeys(): array
  {
    $root = dirname(__DIR__, 3);
    $compose = (string) file_get_contents($root . '/compose.prod.yaml');

    preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', (string) file_get_contents($root . '/.env.example'), $declared);
    preg_match_all('/\$\{([A-Z][A-Z0-9_]*):\?/', $compose, $mandatory);

    $keys = array_unique(array_merge($declared[1], $mandatory[1]));

    return array_values(array_diff($keys, $this->composePinnedKeys($compose), self::COMPUTED_KEYS));
  }

  /**
   * Method composePinnedKeys.
   *
   * Keys a compose service hardcodes in its own `environment:` block. Those win
   * over `env_file:`, so the env file never has to carry them. An interpolated
   * value such as `${FRANKENPHP_CONFIG:-}` is not a pin and does not count.
   *
   * @param string $compose the raw compose.prod.yaml contents
   *
   * @return list<string> the pinned keys
   */
  private function composePinnedKeys(string $compose): array
  {
    /** @var array{services?: array<string, array{environment?: array<string, mixed>}>} $parsed */
    $parsed = Yaml::parse($compose);
    $pinned = [];

    foreach ($parsed['services'] ?? [] as $service) {
      $environment = $service['environment'] ?? null;

      if (!is_array($environment)) {
        continue;
      }

      foreach ($environment as $key => $value) {
        if (is_string($value) && str_starts_with($value, '${')) {
          continue;
        }

        $pinned[] = (string) $key;
      }
    }

    return array_values(array_unique($pinned));
  }

  /**
   * Method workflowKeys.
   *
   * The application keys the deploy job's env block supplies. The deployment
   * credentials and the managed-env switch are excluded: they configure the
   * deploy itself and never reach the generated file.
   *
   * @return list<string> the declared keys
   */
  private function workflowKeys(): array
  {
    $workflow = (string) file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/deploy-vps.yml');
    preg_match_all('/^ {6}([A-Z][A-Z0-9_]*): +\$\{\{ (?:vars|secrets)\./m', $workflow, $matches);

    return array_values(array_filter(
      $matches[1],
      static fn (string $key): bool => !str_starts_with($key, 'VPS_')
        && !str_starts_with($key, 'GHCR_')
        && 'FIREGUARD_MANAGED_ENV' !== $key,
    ));
  }

  /**
   * Method defaultedKeys.
   *
   * @return list<string> the keys config/packages/env_defaults.yaml defaults
   */
  private function defaultedKeys(): array
  {
    /** @var array{parameters?: array<string, mixed>} $parsed */
    $parsed = Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/env_defaults.yaml');
    $keys = [];

    foreach (array_keys($parsed['parameters'] ?? []) as $parameter) {
      if (1 === preg_match_all('/^env\(([A-Z][A-Z0-9_]*)\)$/', (string) $parameter, $matches)) {
        $keys[] = $matches[1][0];
      }
    }

    return $keys;
  }
  // #endregion
}
