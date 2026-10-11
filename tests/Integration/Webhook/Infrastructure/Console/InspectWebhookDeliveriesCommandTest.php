<?php

declare(strict_types=1);

namespace Tests\Integration\Webhook\Infrastructure\Console;

use Doctrine\DBAL\{Connection, DriverManager};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Webhook\Infrastructure\Console\InspectWebhookDeliveriesCommand;

use function is_float;
use function is_int;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Class InspectWebhookDeliveriesCommandTest
 *
 * Real PostgreSQL projections distinguish active leases, expired work and terminal generations.
 *
 * @category Integration Test
 */
final class InspectWebhookDeliveriesCommandTest extends TestCase
{
  // #region Constants
  /**
   * Constant SUBSCRIPTION. Owned regression rows are removed after each observation.
   */
  private const string SUBSCRIPTION = 'bd200000-0000-4000-8000-000000000001';
  // #endregion

  // #region Properties
  /**
   * Property connection. Uses an independent main connection outside DAMA.
   */
  private Connection $connection;
  // #endregion

  // #region Methods
  /**
   * Method setUp
   *
   * Opens the isolated test database without an enclosing transaction.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->connection = DriverManager::getConnection(['url' => $url]);
    $this->connection->executeStatement('DELETE FROM webhook_deliveries WHERE subscription_id = ?', [self::SUBSCRIPTION]);
    $this->connection->executeStatement('DELETE FROM webhook_subscriptions WHERE id = ?', [self::SUBSCRIPTION]);
  }

  /**
   * Method tearDown
   *
   * Removes only this test's deliveries and subscription, including schemas
   * assembled for tests without the migration's cascading foreign key.
   *
   * @access protected
   *
   * @return void
   */
  protected function tearDown(): void
  {
    $this->connection->executeStatement('DELETE FROM webhook_deliveries WHERE subscription_id = ?', [self::SUBSCRIPTION]);
    $this->connection->executeStatement('DELETE FROM webhook_subscriptions WHERE id = ?', [self::SUBSCRIPTION]);
    $this->connection->close();
    parent::tearDown();
  }

  /**
   * Method testCommittedGenerationsAreObservedWithoutChangingTheirStateOrExposingPayloads
   *
   * Future and active work remain distinct from recovery failures and explicit terminal outcomes.
   *
   * @access public
   *
   * @return void
   */
  public function testCommittedGenerationsAreObservedWithoutChangingTheirStateOrExposingPayloads(): void
  {
    $baseline = $this->metrics();
    $organization = $this->connection->fetchOne('SELECT id FROM organizations ORDER BY id LIMIT 1');
    self::assertIsString($organization);
    $this->connection->executeStatement('INSERT INTO webhook_subscriptions (id, organization_id, url, secret_ciphertext, event_types, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [self::SUBSCRIPTION, $organization, 'https://private-destination.test/hook', 'private-secret', '["webhook.ping"]']);
    foreach ([['pending', '90 seconds', false], ['pending', '-600 seconds', false], ['pending', null, false], ['failed', null, true], ['failed', null, false]] as $index => [$status, $lease, $quarantined]) {
      $id = sprintf('bd200000-0000-4000-8000-%012d', $index + 2);
      $this->connection->executeStatement(
        'INSERT INTO webhook_deliveries (id, subscription_id, organization_id, event_type, event_id, payload, status, attempts, lease_until, quarantined, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, CASE WHEN CAST(? AS VARCHAR) IS NULL THEN NULL ELSE CURRENT_TIMESTAMP + CAST(? AS INTERVAL) END, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        [$id, self::SUBSCRIPTION, $organization, 'webhook.ping', $id, '{"private-payload":"customer-data"}', $status, $lease, $lease, $quarantined],
        [9 => 'boolean'],
      );
    }
    $states = $this->connection->fetchAllAssociative('SELECT id, status, lease_until, quarantined FROM webhook_deliveries WHERE subscription_id = ? ORDER BY id', [self::SUBSCRIPTION]);
    $tester = new CommandTester(new InspectWebhookDeliveriesCommand($this->connection));
    self::assertSame(1, $tester->execute([]));
    $observed = $this->metrics($tester);
    self::assertSame($baseline['pending_deliveries'] + 3, $observed['pending_deliveries']);
    self::assertSame($baseline['expired_leases'] + 1, $observed['expired_leases']);
    self::assertSame($baseline['quarantined_generations'] + 1, $observed['quarantined_generations']);
    self::assertSame($baseline['failed_generations'] + 2, $observed['failed_generations']);
    // TIMESTAMP(0) rounds the stored deadline while CURRENT_TIMESTAMP retains fractional seconds.
    self::assertGreaterThanOrEqual(599, $observed['oldest_expired_lease_seconds']);
    self::assertStringNotContainsString('private-', $tester->getDisplay());
    self::assertStringNotContainsString($organization, $tester->getDisplay());
    self::assertSame($states, $this->connection->fetchAllAssociative('SELECT id, status, lease_until, quarantined FROM webhook_deliveries WHERE subscription_id = ? ORDER BY id', [self::SUBSCRIPTION]));
  }

  /**
   * Method metrics
   *
   * Reads only the bounded JSON projection emitted by the real command.
   *
   * @access private
   *
   * @param CommandTester|null $tester a command already executed, or null to execute one
   *
   * @return array<string, int|float> the aggregate observation
   */
  private function metrics(?CommandTester $tester = null): array
  {
    if (null === $tester) {
      $tester = new CommandTester(new InspectWebhookDeliveriesCommand($this->connection));
      $tester->execute([]);
    }
    $data = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($data);
    $metrics = $data['webhook'] ?? null;
    self::assertIsArray($metrics);
    $observed = [];
    foreach ($metrics as $name => $value) {
      self::assertIsString($name);
      if (is_int($value) || is_float($value)) {
        $observed[$name] = $value;
      } else {
        self::fail('Operational metrics must be numeric and bounded.');
      }
    }

    return $observed;
  }
  // #endregion
}
