<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\BackfillMessageLinks;

use Messaging\Application\Port\Outbound\{MessagingLinkRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Domain\Service\UrlExtractor;
use Shared\Application\Message\CommandHandler;

use function count;
use function max;
use function min;

/**
 * UseCase BackfillMessageLinksHandler.
 *
 * Rebuilds the satellite link rows from retained message bodies in bounded,
 * id-cursor batches. `replaceForMessage()` makes every batch idempotent.
 */
final readonly class BackfillMessageLinksHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant MAX_BATCH_SIZE.
   *
   * Caps each link backfill invocation to a bounded message batch.
   */
  private const int MAX_BATCH_SIZE = 500;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies cursor-based message reads, link replacement and URL extraction for resumable backfills.
   *
   * @access public
   *
   * @param MessagingMessageRepositoryPort $messages reads messages in cursor batches
   * @param MessagingLinkRepositoryPort $links replaces extracted link rows
   * @param UrlExtractor $urlExtractor extracts URLs from message bodies
   *
   * @return void
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingLinkRepositoryPort $links,
    private UrlExtractor $urlExtractor,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Rebuilds message link rows in bounded id-cursor batches, with optional dry-run mode.
   *
   * @access public
   *
   * @param BackfillMessageLinksCommand $command the batch cursor and execution options
   *
   * @return BackfillMessageLinksResult counts and continuation cursor for this batch
   */
  public function __invoke(BackfillMessageLinksCommand $command): BackfillMessageLinksResult
  {
    $batchSize = max(1, min(self::MAX_BATCH_SIZE, $command->batchSize));
    $candidates = $this->messages->listLinkBackfillBatch($command->afterMessageId, $batchSize);
    $extractedLinks = 0;
    $nextCursor = null;

    foreach ($candidates as $candidate) {
      $urls = $candidate->isDeleted ? [] : $this->urlExtractor->extract($candidate->body);
      $extractedLinks += count($urls);
      $nextCursor = $candidate->messageId;

      if (!$command->dryRun) {
        $this->links->replaceForMessage(
          $candidate->messageId,
          $candidate->conversationId,
          $urls,
          $candidate->extractedAt,
        );
      }
    }

    return new BackfillMessageLinksResult(
      processedMessages: count($candidates),
      extractedLinks: $extractedLinks,
      nextCursor: $nextCursor,
      hasMore: count($candidates) === $batchSize,
    );
  }
  // #endregion
}
