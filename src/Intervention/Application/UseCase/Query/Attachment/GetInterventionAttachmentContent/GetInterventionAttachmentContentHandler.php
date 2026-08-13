<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\GetInterventionAttachmentContent;

use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetInterventionAttachmentContentHandler.
 *
 * Serves `GET /intervention-attachments/{id}/download`. Enforces the flat
 * `organization.interventions.read` permission — the same READ gate
 * `ListInterventionAttachmentsHandler` / `GetInterventionWorkflowHandler`
 * already enforce for other path-id records in this module — deliberately
 * WITHOUT the phase-based write restriction `AddInterventionAttachmentHandler`
 * applies: a published intervention's evidence must stay downloadable.
 *
 * Mirrors `Messaging\...\DownloadMessageAttachmentHandler`: the storage read
 * is attempted directly rather than probed with `exists()` first, letting
 * `Shared\Infrastructure\Exception\FileStorageException` propagate to
 * `DownloadInterventionAttachmentController`, which logs the data-integrity
 * signal (a persisted row whose bytes are gone) and maps it to 404.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionAttachmentContentHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private InterventionAttachmentRepositoryPort $attachmentRepository,
    private InterventionResourceGatewayPort $resources,
    private OrganizationAuthorizationPort $authorization,
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(GetInterventionAttachmentContentQuery $query): GetInterventionAttachmentContentResult
  {
    try {
      $attachmentId = InterventionAttachmentId::fromString($query->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);
    if (null === $attachment) {
      throw InterventionAttachmentNotFoundException::withId($query->attachmentId);
    }

    $context = $this->resources->interventionAssignmentContext($attachment->interventionId());
    if (null === $context) {
      throw InterventionNotFoundException::withId($attachment->interventionId());
    }

    if (!$this->authorization->hasPermission($query->userId, $context->organizationId, 'organization.interventions.read')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    return new GetInterventionAttachmentContentResult(
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      contents: $this->fileStorage->read($attachment->storagePath()),
    );
  }
  // #endregion
}
