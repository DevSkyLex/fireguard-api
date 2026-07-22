<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Console;

use Messaging\Application\UseCase\Command\Message\BackfillMessageLinks\{BackfillMessageLinksCommand as BackfillBatch, BackfillMessageLinksResult};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function filter_var;
use function is_string;
use function sprintf;

use const FILTER_VALIDATE_INT;

/** Console BackfillMessageLinksCommand. */
#[AsCommand(
  name: 'app:messaging:backfill-links',
  description: 'Extract links from historical messaging bodies in resumable batches',
)]
final class BackfillMessageLinksCommand extends Command
{
  public function __construct(
    private readonly CommandBusPort $commandBus,
  ) {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this
      ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Messages processed per batch (1-500)', '100')
      ->addOption('after', null, InputOption::VALUE_REQUIRED, 'Resume after this exclusive message-id cursor')
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count extracted links without writing');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $batchSize = filter_var($input->getOption('batch-size'), FILTER_VALIDATE_INT);
    if (false === $batchSize || $batchSize < 1 || $batchSize > 500) {
      $io->error('The --batch-size option must be an integer between 1 and 500.');

      return Command::INVALID;
    }

    $after = $input->getOption('after');
    $cursor = is_string($after) && '' !== $after ? $after : null;
    $dryRun = (bool) $input->getOption('dry-run');
    $processed = 0;
    $extracted = 0;

    try {
      do {
        $result = $this->commandBus->dispatch(new BackfillBatch($cursor, $batchSize, $dryRun));
        if (!$result instanceof BackfillMessageLinksResult) {
          $io->error('The messaging link backfill returned an unexpected result.');

          return Command::FAILURE;
        }

        $processed += $result->processedMessages;
        $extracted += $result->extractedLinks;
        $cursor = $result->nextCursor;

        if ($output->isVerbose() && $result->processedMessages > 0) {
          $io->text(sprintf(
            'Processed %d message(s); cursor %s.',
            $result->processedMessages,
            $cursor ?? 'complete',
          ));
        }
      } while ($result->hasMore && null !== $cursor);
    } catch (Throwable $error) {
      $io->error(sprintf('Messaging link backfill failed after %d message(s): %s', $processed, $error->getMessage()));

      return Command::FAILURE;
    }

    $summary = sprintf('Processed %d message(s) and extracted %d unique link(s).', $processed, $extracted);
    $dryRun ? $io->note('Dry run: ' . $summary) : $io->success($summary);

    return Command::SUCCESS;
  }
}
