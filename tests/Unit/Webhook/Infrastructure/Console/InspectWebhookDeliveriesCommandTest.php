<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Webhook\Infrastructure\Console\InspectWebhookDeliveriesCommand;

use function array_replace;
use function str_contains;

/**
 * Class InspectWebhookDeliveriesCommandTest
 *
 * Operational failures remain visible without reading delivery payloads.
 *
 * @category Unit Test
 */
final class InspectWebhookDeliveriesCommandTest extends TestCase
{
  // #region Methods
  /**
   * Method testLeaseGraceAndTerminalFailuresDetermineTheOperationalStatus
   *
   * Confirms recovery grace and terminal failures while rejecting payload observation.
   *
   * @access public
   *
   * @param array<string, int|string> $metrics aggregate projection
   * @param int $expected the expected process status
   *
   * @return void
   */
  #[DataProvider('observations')]
  public function testLeaseGraceAndTerminalFailuresDetermineTheOperationalStatus(array $metrics, int $expected): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')->with(self::callback(static fn (string $sql): bool => str_contains($sql, 'webhook_deliveries') && !str_contains($sql, 'payload') && !str_contains($sql, 'organization_id') && !str_contains($sql, 'url')))->willReturn($metrics + ['payload' => 'private-payload']);
    $tester = new CommandTester(new InspectWebhookDeliveriesCommand($connection));

    self::assertSame($expected, $tester->execute([]));
    self::assertStringContainsString('"database":"main"', $tester->getDisplay());
    self::assertStringNotContainsString('private-payload', $tester->getDisplay());
    self::assertStringContainsString('"expired_leases":', $tester->getDisplay());
  }

  /**
   * Method observations
   *
   * Provides recovery-boundary and terminal-generation projections.
   *
   * @access public
   *
   * @return array<string, array{array<string, int|string>, int}> bounded projections
   */
  public static function observations(): array
  {
    $empty = ['pending_deliveries' => 0, 'oldest_pending_seconds' => '0', 'expired_leases' => 0, 'oldest_expired_lease_seconds' => '0', 'quarantined_generations' => 0, 'failed_generations' => 0];

    return [
      'empty healthy' => [$empty, 0],
      'recoverable lease within grace' => [array_replace($empty, ['pending_deliveries' => 1, 'expired_leases' => 1, 'oldest_expired_lease_seconds' => '180']), 0],
      'expired lease beyond grace' => [array_replace($empty, ['pending_deliveries' => 1, 'expired_leases' => 1, 'oldest_expired_lease_seconds' => '181']), 1],
      'technical quarantine' => [array_replace($empty, ['quarantined_generations' => 1]), 1],
      'HTTP exhaustion' => [array_replace($empty, ['failed_generations' => 1]), 1],
    ];
  }

  /**
   * Method testInvalidThresholdDoesNotReadTheDatabase
   *
   * Invalid operational thresholds cannot trigger database observation.
   *
   * @access public
   *
   * @return void
   */
  public function testInvalidThresholdDoesNotReadTheDatabase(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::never())->method('fetchAssociative');
    $tester = new CommandTester(new InspectWebhookDeliveriesCommand($connection));
    foreach (['0', '-1', '1.5', 'invalid'] as $threshold) {
      self::assertSame(2, $tester->execute(['--max-lease-age' => $threshold]));
    }
  }

  /**
   * Method testUnreadableProjectionFailsWithoutExposingItsData
   *
   * Malformed observations remain unhealthy without exposing their values.
   *
   * @access public
   *
   * @return void
   */
  public function testUnreadableProjectionFailsWithoutExposingItsData(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['pending_deliveries' => 'private-invalid-value']);
    $tester = new CommandTester(new InspectWebhookDeliveriesCommand($connection));
    self::assertSame(1, $tester->execute([]));
    self::assertStringNotContainsString('private-invalid-value', $tester->getDisplay());
  }

  /**
   * Method testDatabaseFailureIsUnhealthyWithoutLeakingItsMessage
   *
   * Connection diagnostics never reach the public operational projection.
   *
   * @access public
   *
   * @return void
   */
  public function testDatabaseFailureIsUnhealthyWithoutLeakingItsMessage(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willThrowException(new RuntimeException('private-connection-details'));
    $tester = new CommandTester(new InspectWebhookDeliveriesCommand($connection));
    self::assertSame(1, $tester->execute([]));
    self::assertStringNotContainsString('private-connection-details', $tester->getDisplay());
  }
  // #endregion
}
