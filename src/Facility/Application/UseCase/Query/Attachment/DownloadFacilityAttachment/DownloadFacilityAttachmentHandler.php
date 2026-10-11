<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\DownloadFacilityAttachment;

use Facility\Application\Port\Outbound\Internal\FacilityAttachmentRepositoryPort;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextQuery;
use Facility\Domain\Exception\FacilityAttachmentNotFoundException;
use Facility\Domain\ValueObject\FacilityAttachmentId;
use Shared\Application\Contract\Storage\FileStorageException;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{FileStoragePort, LoggerPort};

/**
 * Handler DownloadFacilityAttachmentHandler.
 *
 * Authorizes attachment ownership before reading private stored bytes.
 *
 * @category Handler
 */
final readonly class DownloadFacilityAttachmentHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(
    private QueryBusPort $queries,
    private FacilityAttachmentRepositoryPort $attachments,
    private FileStoragePort $storage,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   */
  public function __invoke(DownloadFacilityAttachmentQuery $query): DownloadFacilityAttachmentResult
  {
    $this->queries->ask(new GetFacilityMediaContextQuery($query->userId, attachmentId: $query->attachmentId));
    $attachment = $this->attachments->findById(FacilityAttachmentId::fromString($query->attachmentId));
    if (null === $attachment) {
      throw new FacilityAttachmentNotFoundException('Attachment not found.');
    }

    try {
      $contents = $this->storage->read($attachment->file()->storagePath);
    } catch (FileStorageException $exception) {
      $this->logger->error('Facility attachment record found but its stored file is missing.', ['attachmentId' => $query->attachmentId, 'exception' => $exception->getMessage()]);

      throw new FacilityAttachmentNotFoundException('Attachment content not found.', previous: $exception);
    }

    return new DownloadFacilityAttachmentResult($contents, $attachment->file()->fileName, $attachment->file()->mimeType);
  }
  // #endregion
}
