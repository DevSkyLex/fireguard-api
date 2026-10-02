<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function is_numeric;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class InspectWorkerQueuesCommand
 *
 * Checks durable queue age and dead-letter counts without loading or logging payloads.
 * Both connections are explicitly wired, so main-owned work cannot silently query auth.
 *
 * @category Console
 */
#[AsCommand(name: 'app:workers:queues', description: 'Check durable queue age and failures without exposing messages')]
final class InspectWorkerQueuesCommand extends Command
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param Connection $authConnection auth-owned Messenger queues
   * @param Connection $mainConnection main-owned Messenger queues
   */
  public function __construct(private readonly Connection $authConnection, private readonly Connection $mainConnection)
  {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure
   *
   * @return void
   */
  protected function configure(): void
  {
    $this->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Maximum overdue queue age in seconds', '900');
  }

  /**
   * Method execute
   *
   * Fails if a table is missing, a failed queue contains messages, or due work exceeds the threshold.
   * Future delayed retries do not count as overdue work.
   *
   * @param InputInterface $input operator threshold
   * @param OutputInterface $output bounded queue metadata
   *
   * @return int check status
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $maxAge = $input->getOption('max-age');
    if (!is_numeric($maxAge) || (int) $maxAge < 1) {
      return Command::INVALID;
    }
    $healthy = true;

    try {
      foreach (['auth' => $this->authConnection, 'main' => $this->mainConnection] as $name => $connection) {
        $queues = $connection->fetchAllAssociative('SELECT queue_name, COUNT(*) AS messages, COALESCE(MAX(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - available_at))) FILTER (WHERE delivered_at IS NULL AND available_at <= CURRENT_TIMESTAMP), 0) AS overdue_seconds FROM messenger_messages GROUP BY queue_name ORDER BY queue_name');
        foreach ($queues as $queue) {
          if (!is_numeric($queue['messages']) || !is_numeric($queue['overdue_seconds'])) {
            throw new RuntimeException('Unexpected durable queue observation shape.');
          }
          $failed = 'failed' === $queue['queue_name'] || 'main_failed' === $queue['queue_name'];
          $healthy = $healthy && !($failed && (int) $queue['messages'] > 0) && (float) $queue['overdue_seconds'] <= (int) $maxAge;
        }
        $output->writeln(json_encode(['database' => $name, 'queues' => $queues], JSON_THROW_ON_ERROR));
      }
    } catch (Throwable) {
      $output->writeln('Durable queue observation failed; check transport initialization and connectivity.');

      return Command::FAILURE;
    }

    return $healthy ? Command::SUCCESS : Command::FAILURE;
  }
  // #endregion
}
