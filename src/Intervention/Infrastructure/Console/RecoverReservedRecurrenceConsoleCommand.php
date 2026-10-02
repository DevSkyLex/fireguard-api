<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Console;

use Intervention\Application\UseCase\Command\Recurrence\RecoverReservedRecurrence\RecoverReservedRecurrenceCommand;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;

/**
 * Class RecoverReservedRecurrenceConsoleCommand
 *
 * Offers explicit recovery after an operator has reconciled any pre-atomic legacy draft.
 *
 * @category Console Command
 */
#[AsCommand(name: 'app:intervention:recover-reserved-recurrence', description: 'Link a verified existing draft or explicitly skip a legacy reserved occurrence')]
final class RecoverReservedRecurrenceConsoleCommand extends Command
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param CommandBusPort $commands the application command bus
   *
   * @return void
   */
  public function __construct(private readonly CommandBusPort $commands)
  {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure
   *
   * @access protected
   *
   * @return void
   */
  protected function configure(): void
  {
    $this->addArgument('run-id', InputArgument::REQUIRED, 'Legacy reservation identifier')
      ->addOption('intervention-id', null, InputOption::VALUE_REQUIRED, 'Verified complete existing draft; no new draft is created')
      ->addOption('failure-reason', null, InputOption::VALUE_REQUIRED, 'Explicitly skip this occurrence after reconciling any partial draft');
  }

  /**
   * Method execute
   *
   * @access protected
   *
   * @param InputInterface $input explicit operator choice
   * @param OutputInterface $output recovery confirmation
   *
   * @return int successful command status
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $runId = $input->getArgument('run-id');
    $interventionId = $input->getOption('intervention-id');
    $reason = $input->getOption('failure-reason');
    $this->commands->dispatch(new RecoverReservedRecurrenceCommand(
      is_string($runId) ? $runId : '',
      is_string($interventionId) ? $interventionId : null,
      is_string($reason) ? $reason : null,
    ));
    new SymfonyStyle($input, $output)->success('Legacy occurrence resolved; its schedule and outcome committed together.');

    return self::SUCCESS;
  }
  // #endregion
}
