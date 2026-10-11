<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use UnexpectedValueException;

use function ctype_digit;
use function is_numeric;
use function is_string;
use function json_encode;
use function str_ends_with;

use const JSON_THROW_ON_ERROR;

/**
 * Class InspectWebhookDeliveriesCommand
 *
 * Observes bounded delivery counts and ages without loading payloads, destinations or customer identifiers.
 *
 * @category Console
 */
#[AsCommand(name: 'app:webhook:deliveries', description: 'Check webhook leases and terminal delivery failures without exposing messages')]
final class InspectWebhookDeliveriesCommand extends Command
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Keeps aggregate observation scoped to the main database.
   *
   * @access public
   *
   * @param Connection $connection the explicitly wired main database
   *
   * @return void
   */
  public function __construct(private readonly Connection $connection)
  {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure
   *
   * Sets the grace period available to the one-minute recovery sweep.
   *
   * @access protected
   *
   * @return void
   */
  protected function configure(): void
  {
    $this->addOption('max-lease-age', null, InputOption::VALUE_REQUIRED, 'Maximum seconds since an owned lease expired', '180');
  }

  /**
   * Method execute
   *
   * Allows the minute recovery sweep to repair interrupted attempts before alerting.
   * Quarantined or HTTP-exhausted generations require an explicit operator decision.
   *
   * @access protected
   *
   * @param InputInterface $input the operational age threshold
   * @param OutputInterface $output bounded aggregate metadata
   *
   * @return int the health check status
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $maxAge = $input->getOption('max-lease-age');
    if (!is_string($maxAge) || !ctype_digit($maxAge) || (int) $maxAge < 1) {
      return Command::INVALID;
    }

    try {
      $row = $this->connection->fetchAssociative(<<<'SQL'
        SELECT COUNT(*) FILTER (WHERE status = 'pending') AS pending_deliveries,
          COALESCE(MAX(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - created_at))) FILTER (WHERE status = 'pending'), 0) AS oldest_pending_seconds,
          COUNT(*) FILTER (WHERE status = 'pending' AND lease_until <= CURRENT_TIMESTAMP) AS expired_leases,
          COALESCE(MAX(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - lease_until))) FILTER (WHERE status = 'pending' AND lease_until <= CURRENT_TIMESTAMP), 0) AS oldest_expired_lease_seconds,
          COUNT(*) FILTER (WHERE quarantined = TRUE) AS quarantined_generations,
          COUNT(*) FILTER (WHERE status = 'failed') AS failed_generations
        FROM webhook_deliveries
        SQL);
      if (false === $row) {
        throw new UnexpectedValueException('Missing webhook aggregate observation.');
      }
      $metrics = [];
      foreach (['pending_deliveries', 'oldest_pending_seconds', 'expired_leases', 'oldest_expired_lease_seconds', 'quarantined_generations', 'failed_generations'] as $name) {
        if (!is_numeric($row[$name] ?? null)) {
          throw new UnexpectedValueException('Unexpected webhook aggregate observation shape.');
        }
        $metrics[$name] = str_ends_with($name, '_seconds') ? (float) $row[$name] : (int) $row[$name];
      }
      $output->writeln(json_encode(['database' => 'main', 'webhook' => $metrics], JSON_THROW_ON_ERROR));

      return $metrics['oldest_expired_lease_seconds'] > (int) $maxAge || $metrics['quarantined_generations'] > 0 || $metrics['failed_generations'] > 0 ? Command::FAILURE : Command::SUCCESS;
    } catch (Throwable) {
      $output->writeln('Webhook delivery observation failed; check migrations and main connectivity.');

      return Command::FAILURE;
    }
  }
  // #endregion
}
