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
  private const int MAX_BATCH_SIZE = 500;

  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingLinkRepositoryPort $links,
    private UrlExtractor $urlExtractor,
  ) {
  }

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
}
