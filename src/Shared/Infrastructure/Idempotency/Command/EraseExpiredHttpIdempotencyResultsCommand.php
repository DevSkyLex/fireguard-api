<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency\Command;

use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyRetentionPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EraseExpiredHttpIdempotencyResultsCommand
 *
 * Purges elapsed result bytes independently on auth and main, keeping tombstones.
 *
 * @category Command
 */
#[AsCommand(name: 'app:cleanup:http-idempotency-results', description: 'Erase expired HTTP replay payloads while retaining durable tombstones')]
final class EraseExpiredHttpIdempotencyResultsCommand extends Command
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param IdempotencyRetentionPort $main explicit main retention adapter
   * @param IdempotencyRetentionPort $auth explicit auth retention adapter
   * @param ClockPort $clock retention cutoff clock
   * @param iterable<IdempotencyRetentionPort> $contributors owner-provided temporary-payload cleanup capabilities
   *
   * @return void
   */
  public function __construct(
    private readonly IdempotencyRetentionPort $main,
    private readonly IdempotencyRetentionPort $auth,
    private readonly ClockPort $clock,
    private readonly iterable $contributors = [],
  ) {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method execute
   *
   * @access protected
   *
   * @param InputInterface $input command arguments
   * @param OutputInterface $output non-sensitive counts only
   *
   * @return int command status
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $now = $this->clock->now();
    $main = $this->main->eraseExpiredResults($now);
    $auth = $this->auth->eraseExpiredResults($now);
    $contributed = 0;
    foreach ($this->contributors as $contributor) {
      $contributed += $contributor->eraseExpiredResults($now);
    }
    $output->writeln('Erased HTTP replay payloads: main=' . $main . ', auth=' . $auth . ', contributed=' . $contributed . '. Durable tombstones retained.');

    return self::SUCCESS;
  }
  // #endregion
}
