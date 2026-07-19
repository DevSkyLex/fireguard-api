<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function array_keys;
use function dirname;
use function file_get_contents;
use function in_array;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_ends_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test WorkerTransportDocumentationTest.
 *
 * A Messenger transport with no consumer does NOT error. It silently
 * accumulates messages that are never processed, which is indistinguishable
 * from "the feature does not work" and produces no log line to investigate.
 * Forgetting one when the worker command is written is therefore a
 * production-only, silent failure — the kind this codebase has already been
 * bitten by (the documented command lagged behind the `webhook` and
 * `assistant` transports, which would have left every outbound webhook
 * undelivered and every assistant question answered by nothing at all).
 *
 * `OPERATIONS.md` used to carry the prose instruction "keep this list in sync".
 * That is exactly the kind of instruction that rots. This test enforces it
 * instead: every transport declared in `config/packages/messenger.yaml`, and
 * every `scheduler_<name>` implied by an `#[AsSchedule('<name>')]` provider,
 * must appear in the documented `messenger:consume` command.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WorkerTransportDocumentationTest extends TestCase
{
  // #region Constants
  /**
   * Transports a long-running worker must NOT consume.
   *
   * `sync` dispatches in-process and has no queue. `failed` is the
   * dead-letter store, drained deliberately with `messenger:failed:retry`
   * after a human has looked at it — a worker consuming it would silently
   * replay poison messages in a loop.
   */
  private const array NON_WORKER_TRANSPORTS = ['sync', 'failed'];
  // #endregion

  // #region Methods
  /**
   * Method testEveryTransportAppearsInTheDocumentedWorkerCommand.
   *
   * @return void no return value
   */
  #[Test]
  public function testEveryTransportAppearsInTheDocumentedWorkerCommand(): void
  {
    $command = self::documentedWorkerCommand();

    foreach (self::declaredTransports() as $transport) {
      self::assertTrue(str_contains($command, $transport), sprintf(
        'Transport "%s" is declared in config/packages/messenger.yaml but missing from the '
        . '`messenger:consume` command documented in OPERATIONS.md. A transport with no consumer '
        . 'does not error — it silently queues messages nobody ever processes. Add it to the '
        . 'documented command (and to whatever supervises the worker in production), or add it to '
        . 'NON_WORKER_TRANSPORTS with a reason if it is deliberately not consumed.',
        $transport,
      ));
    }
  }

  /**
   * Method testEverySchedulerAppearsInTheDocumentedWorkerCommand.
   *
   * @return void no return value
   */
  #[Test]
  public function testEverySchedulerAppearsInTheDocumentedWorkerCommand(): void
  {
    $command = self::documentedWorkerCommand();

    foreach (self::declaredSchedulerTransports() as $transport) {
      self::assertTrue(str_contains($command, $transport), sprintf(
        'Scheduler transport "%s" is implied by an #[AsSchedule] provider but missing from the '
        . '`messenger:consume` command documented in OPERATIONS.md. Consuming `async` alone runs '
        . 'routed commands but never fires the schedule ticks, so the sweep simply never happens.',
        $transport,
      ));
    }
  }

  /**
   * Method documentedWorkerCommand.
   *
   * @static
   *
   * @return string the `messenger:consume` invocation documented in OPERATIONS.md
   */
  private static function documentedWorkerCommand(): string
  {
    $operations = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'OPERATIONS.md');
    self::assertIsString($operations, 'OPERATIONS.md must be readable.');

    // The command may be wrapped over several lines with trailing backslashes.
    // Two things this pattern has to get right: the continuation alternative
    // must come FIRST (otherwise `[^\n]` eats the trailing backslash and the
    // match stops at the newline), and it must tolerate CRLF — this repository
    // is developed on Windows, so the byte after the backslash is `\r`.
    $matched = preg_match('/messenger:consume((?:\\\\\r?\n|[^\r\n])*)/', $operations, $matches);
    self::assertSame(1, $matched, 'OPERATIONS.md must document a `messenger:consume` worker command.');

    return $matches[1];
  }

  /**
   * Method declaredTransports.
   *
   * @static
   *
   * @return list<string> the transports a worker is expected to consume
   */
  private static function declaredTransports(): array
  {
    $config = Yaml::parseFile(
      dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'messenger.yaml',
    );

    self::assertIsArray($config, 'messenger.yaml must parse to an array.');
    $framework = $config['framework'] ?? null;
    self::assertIsArray($framework, 'messenger.yaml must declare a `framework` section.');
    $messenger = $framework['messenger'] ?? null;
    self::assertIsArray($messenger, 'messenger.yaml must declare a `framework.messenger` section.');
    $transports = $messenger['transports'] ?? null;
    self::assertIsArray($transports, 'messenger.yaml must declare transports.');

    $names = [];
    foreach ($transports as $name => $_definition) {
      if (!in_array($name, self::NON_WORKER_TRANSPORTS, true)) {
        $names[] = (string) $name;
      }
    }

    self::assertNotEmpty($names, 'At least one worker transport must be declared.');

    return $names;
  }

  /**
   * Method declaredSchedulerTransports.
   *
   * @static
   *
   * Every `#[AsSchedule('<name>')]` provider exposes a `scheduler_<name>`
   * transport that the worker must consume for its ticks to fire.
   *
   * @return list<string> the implied scheduler transport names
   */
  private static function declaredSchedulerTransports(): array
  {
    $sourceRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    $names = [];

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
        continue;
      }

      $contents = file_get_contents($file->getPathname());
      if (false === $contents) {
        continue;
      }

      preg_match_all("/AsSchedule\(\s*'([A-Za-z0-9_]+)'/", $contents, $matches);

      foreach ($matches[1] as $schedule) {
        $names['scheduler_' . $schedule] = true;
      }
    }

    self::assertNotEmpty($names, sprintf(
      'Expected at least one #[AsSchedule] provider under %s.',
      $sourceRoot,
    ));

    return array_keys($names);
  }
  // #endregion
}
